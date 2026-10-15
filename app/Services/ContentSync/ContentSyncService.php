<?php

namespace App\Services\ContentSync;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Models\ContentSyncAdminPrefs;
use App\Models\ContentSyncContent;
use App\Models\ContentSyncOperation;
use App\Models\ContentSyncVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * موتور همگام‌سازی چندکاناله (Content Sync).
 *
 * جریان اصلی (fast mode):
 *  1. ادمین لینک یک پیام منتشرشده می‌فرستد → registerSource() → content_id ساخته می‌شود
 *  2. ادمین متن را می‌فرستد → attachText()
 *  3. تگ بر اساس قوانین بدون سؤالِ اضافی تعیین می‌شود → resolveTag()
 *  4. انتشار فوری (publishImmediate) یا صف (enqueueForTag) — فقط به کانال‌های دیگر
 *     همان تگ، کانال مبدأ مستثنی است.
 *
 * dedup: unique constraint روی (bot, source_platform, source_channel, source_message)
 * و (content, destination) — ادمین‌های هم‌زمان هم تکراری منتشر نمی‌کنند.
 */
class ContentSyncService
{
    public function __construct(
        private ChannelPosterBotService $service
    ) {
    }

    /* ------------------------------------------------------------------
     * 1) ثبت لینک مبدأ (fast mode — step 1)
     * ------------------------------------------------------------------ */

    /**
     * @return array{status: string, content: ContentSyncContent, parsed: array, duplicate: bool, source_destination_id: int|null}
     *   status ∈ registered | duplicate
     */
    public function registerSource(Bot $bot, string $rawUrl, ?string $adminChatId, string $origin): array
    {
        $parsed = PostUrlParser::parse($rawUrl);
        if (!$parsed) {
            throw new \InvalidArgumentException('invalid_post_url');
        }

        $channelId = $parsed['channel_identifier'];
        $messageId = $parsed['message_identifier'];

        $existing = ContentSyncContent::findBySource(
            $bot->id,
            $parsed['platform'],
            $channelId,
            $messageId
        );

        // مقصدی که این لینک به آن مربوط می‌شود (برای استثنی‌سازی و پیشنهاد تگ)
        $sourceDestinationId = $this->matchDestination($bot->id, $parsed);

        if ($existing) {
            ContentSyncOperation::log(
                $bot->id,
                $adminChatId,
                $origin,
                $existing->id,
                ContentSyncOperation::OP_REGISTER,
                ContentSyncOperation::RESULT_DUPLICATE,
                'source url already registered'
            );

            return [
                'status' => 'duplicate',
                'content' => $existing->load('versions'),
                'parsed' => $parsed,
                'duplicate' => true,
                'source_destination_id' => $sourceDestinationId,
            ];
        }

        $content = ContentSyncContent::create([
            'bot_id' => $bot->id,
            'content_type' => ContentSyncContent::CONTENT_TYPE_TEXT,
            'created_by_chat_id' => $adminChatId,
            'created_by_origin' => $origin,
            'source_platform' => $parsed['platform'],
            'source_channel_id' => $channelId,
            'source_message_id' => $messageId,
            'source_url' => $parsed['canonical_url'],
            'source_url_raw' => mb_substr(trim($rawUrl), 0, 500),
        ]);

        // نسخه خود پیام مبدأ (لینکی که ادمین فرستاده — واقعیت ثبت‌شده)
        ContentSyncVersion::create([
            'content_id' => $content->id,
            'bot_id' => $bot->id,
            'destination_id' => $sourceDestinationId,
            'platform' => $parsed['platform'],
            'channel_id' => $channelId,
            'channel_username' => $parsed['channel_username'] ?? null,
            'message_id' => $messageId,
            'post_url' => $parsed['canonical_url'],
            'link_status' => ContentSyncVersion::LINK_CONFIRMED,
            'publish_status' => ContentSyncVersion::STATUS_PUBLISHED,
            'chunks_count' => 1,
        ]);

        ContentSyncOperation::log(
            $bot->id,
            $adminChatId,
            $origin,
            $content->id,
            ContentSyncOperation::OP_REGISTER,
            ContentSyncOperation::RESULT_OK
        );

        return [
            'status' => 'registered',
            'content' => $content->load('versions'),
            'parsed' => $parsed,
            'duplicate' => false,
            'source_destination_id' => $sourceDestinationId,
        ];
    }

    /* ------------------------------------------------------------------
     * 2) دریافت متن (fast mode — step 2)
     * ------------------------------------------------------------------ */

    public function attachText(ContentSyncContent $content, string $text): ContentSyncContent
    {
        $text = $text === null ? '' : (string) $text;

        $content->forceFill([
            'text' => $text,
            'text_hash' => hash('sha256', $text),
        ])->save();

        ContentSyncOperation::log(
            (int) $content->bot_id,
            $content->created_by_chat_id,
            (string) $content->created_by_origin,
            $content->id,
            ContentSyncOperation::OP_ATTACH_TEXT,
            ContentSyncOperation::RESULT_OK
        );

        return $content->fresh();
    }

    /* ------------------------------------------------------------------
     * 3) تعیین تگ — بدون سؤال وقتی ابهام نیست
     * ------------------------------------------------------------------ */

    /**
     * قوانین:
     *  - تگ کانال مبدأ (اگر ثبت شده) > تگ پیشنهادی ادمین > آخرین تگ ادمین > فقط یک تگ
     *  - فقط وقتی چند تگ هست و هیچ‌کدام بالا را ندارد سؤال می‌شود.
     *
     * @return array{status: string, tag: string|null, groups: array}
     *   status ∈ ok | ask
     */
    public function resolveTag(
        int $botId,
        ?string $sourceDestinationId,
        ?string $adminHintTag = null,
        ?string $adminChatId = null,
        string $origin = 'bale'
    ): array {
        $groups = $this->service->listTagGroups($botId);

        if ($groups === []) {
            return ['status' => 'ask', 'tag' => null, 'groups' => []];
        }

        $keys = array_map(fn (array $g) => $g['key'], $groups);

        // 1) تگ کانال مبدأ
        if ($sourceDestinationId) {
            $sourceDest = ChannelPosterDestination::find($sourceDestinationId);
            if ($sourceDest && trim((string) $sourceDest->tag) !== '' && in_array(trim((string) $sourceDest->tag), $keys, true)) {
                return $this->tagOk($botId, $adminChatId, $origin, trim((string) $sourceDest->tag), $groups);
            }
        }

        // 2) فقط یک تگ
        if (count($groups) === 1) {
            return $this->tagOk($botId, $adminChatId, $origin, $keys[0], $groups);
        }

        // 3) تگ پیشنهادی ادمین (برای /sync دستی یا انتخاب بعدی)
        if ($adminHintTag !== null && $adminHintTag !== '' && in_array($adminHintTag, $keys, true)) {
            return $this->tagOk($botId, $adminChatId, $origin, $adminHintTag, $groups);
        }

        // 4) آخرین تگ این ادمین
        if ($adminChatId !== null) {
            $last = ContentSyncAdminPrefs::where('bot_id', $botId)
                ->where('admin_chat_id', $adminChatId)
                ->where('origin', $origin)
                ->value('last_tag');
            if ($last !== null && trim((string) $last) !== '' && in_array(trim((string) $last), $keys, true)) {
                return $this->tagOk($botId, $adminChatId, $origin, trim((string) $last), $groups);
            }
        }

        return ['status' => 'ask', 'tag' => null, 'groups' => $groups];
    }

    private function tagOk(int $botId, ?string $adminChatId, string $origin, string $tag, array $groups): array
    {
        if ($adminChatId !== null) {
            ContentSyncAdminPrefs::rememberTag($botId, $adminChatId, $origin, $tag);
        }

        return ['status' => 'ok', 'tag' => $tag, 'groups' => $groups];
    }

    /* ------------------------------------------------------------------
     * 4) مقصدهای قابل انتشار (کانال مبدأ + نسخه‌های موجود حذف می‌شوند)
     * ------------------------------------------------------------------ */

    public function resolveTargets(ContentSyncContent $content, string $tag): Collection
    {
        $destinations = $this->service->resolveByTag($content->bot_id, $tag);
        if ($destinations->isEmpty()) {
            $destinations = $this->service->resolveDestinations($content->bot_id, 'all');
        }

        $sourcePlatform = $content->source_platform;
        $sourceChannel = $content->source_channel_id;
        $versioned = $content->versions()->pluck('destination_id')->filter()->all();

        return $destinations
            ->reject(function (ChannelPosterDestination $dest) use ($sourcePlatform, $sourceChannel, $versioned) {
                // کانال مبدأ مستثنی است
                if ($dest->platform === $sourcePlatform && (string) $dest->channel_chat_id === (string) $sourceChannel) {
                    return true;
                }
                // قبلاً نسخه‌ای برای این مقصد ثبت شده (جلوگیری از انتشار مجدد)
                if (in_array($dest->id, $versioned, true)) {
                    return true;
                }
                return false;
            })
            ->values();
    }

    /**
     * مقصدی که یک لینک مبدأ به آن مربوط است (اگر ثبت شده باشد).
     */
    public function matchDestination(int $botId, array $parsed): ?int
    {
        $destinations = ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true)
            ->where('platform', $parsed['platform'])
            ->get();

        foreach ($destinations as $dest) {
            if (PostUrlParser::matchesDestination($parsed, $dest)) {
                return $dest->id;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------
     * 5) انتشار فوری (fast mode — step 4)
     * ------------------------------------------------------------------ */

    /**
     * @return array{results: array, any_published: bool, destinations: Collection}
     */
    public function publishImmediate(
        ContentSyncContent $content,
        string $tag,
        ChannelPosterPublisher $publisher,
        ?string $adminChatId = null,
        string $origin = 'bale'
    ): array {
        $targets = $this->resolveTargets($content, $tag);
        $chunks = TextChunker::split((string) $content->text);
        $results = [];

        foreach ($targets as $destination) {
            // سروش هنوز publisher ندارد — به‌جای ارسال اشتباه، skip با دلیل روشن
            if ($destination->platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
                $version = $this->upsertVersion($content, $destination, [
                    'publish_status' => ContentSyncVersion::STATUS_SKIPPED,
                    'last_error' => trans('bot.channel_sync_soroush_not_ready'),
                ]);
                $results[] = [
                    'destination_id' => $destination->id,
                    'platform' => $destination->platform,
                    'success' => false,
                    'skipped' => true,
                    'message_id' => null,
                ];
                continue;
            }

            $lastMessageId = null;
            $ok = true;
            $error = null;

            try {
                foreach ($chunks as $chunk) {
                    $result = $publisher->publish(
                        $destination->channel_chat_id,
                        ContentSyncContent::CONTENT_TYPE_TEXT,
                        $chunk,
                        null,
                        $destination->platform,
                        $destination->bot_token
                    );
                    if (!($result['success'] ?? false)) {
                        $ok = false;
                        $error = $result['error'] ?? trans('bot.channel_sync_publish_failed');
                        break;
                    }
                    $lastMessageId = $result['message_id'] ?? $lastMessageId;
                }
            } catch (Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }

            $urlInfo = $ok
                ? PostUrlBuilder::build($destination, $lastMessageId)
                : ['url' => null, 'status' => ContentSyncVersion::LINK_NONE];

            $version = $this->upsertVersion($content, $destination, [
                'publish_status' => $ok ? ContentSyncVersion::STATUS_PUBLISHED : ContentSyncVersion::STATUS_FAILED,
                'message_id' => $lastMessageId,
                'post_url' => $ok ? $urlInfo['url'] : null,
                'link_status' => $ok ? $urlInfo['status'] : ContentSyncVersion::LINK_NONE,
                'published_at' => $ok ? now() : null,
                'last_error' => $ok ? null : $error,
                'chunks_count' => count($chunks),
            ]);

            $this->service->logPublish(
                $content->bot_id,
                $destination->id,
                $destination->platform,
                $ok,
                $lastMessageId,
                $ok ? null : $error,
                null,
                $ok ? $urlInfo['url'] : null,
                $ok ? $urlInfo['status'] : null,
                $content->id
            );

            $results[] = [
                'destination_id' => $destination->id,
                'platform' => $destination->platform,
                'success' => $ok,
                'skipped' => false,
                'message_id' => $lastMessageId,
                'version_id' => $version->id,
            ];
        }

        ContentSyncOperation::log(
            $content->bot_id,
            $adminChatId,
            $origin,
            $content->id,
            ContentSyncOperation::OP_PUBLISH,
            $results !== [] && collect($results)->every(fn ($r) => $r['success'] || $r['skipped'])
                ? ContentSyncOperation::RESULT_OK
                : ContentSyncOperation::RESULT_FAILED
        );

        return [
            'results' => $results,
            'any_published' => collect($results)->contains(fn ($r) => $r['success']),
            'destinations' => $targets,
        ];
    }

    /* ------------------------------------------------------------------
     * 6) صف (نسخه‌های queued برای هر مقصد)
     * ------------------------------------------------------------------ */

    /**
     * @return array{queue: ChannelPosterQueue, version_count: int}
     */
    public function enqueueForTag(
        ContentSyncContent $content,
        string $tag,
        string $adminChatId,
        string $origin,
        ?Carbon $scheduledAt = null
    ): array {
        $targets = $this->resolveTargets($content, $tag);
        $slot = $scheduledAt ?? $this->service->getNextSlot($content->bot_id, $tag);

        $queue = $this->service->enqueue(
            $content->bot_id,
            $tag,
            ContentSyncContent::CONTENT_TYPE_TEXT,
            $content->text,
            null,
            false,
            $slot,
            $adminChatId,
            $origin,
            $content->id
        );

        $count = 0;
        foreach ($targets as $destination) {
            $this->upsertVersion($content, $destination, [
                'publish_status' => ContentSyncVersion::STATUS_QUEUED,
                'queue_id' => $queue->id,
                'chunks_count' => count(TextChunker::split((string) $content->text)),
            ]);
            $count++;
        }

        ContentSyncOperation::log(
            $content->bot_id,
            $adminChatId,
            $origin,
            $content->id,
            ContentSyncOperation::OP_ENQUEUE,
            $count > 0 ? ContentSyncOperation::RESULT_OK : ContentSyncOperation::RESULT_SKIPPED,
            $count > 0 ? null : 'no targets'
        );

        return ['queue' => $queue, 'version_count' => $count];
    }

    /* ------------------------------------------------------------------
     * 7) نتیجه صف → به‌روزرسانی نسخه‌ها (از ChannelPosterQueuePublishService صدا می‌شود)
     * ------------------------------------------------------------------ */

    /**
     * @param array $results لیست نتایج publishItem (destination_id, platform, success, message_id)
     */
    public function recordQueueResults(ChannelPosterQueue $item, array $results): void
    {
        $content = $item->content_id ? ContentSyncContent::find($item->content_id) : null;
        if (!$content) {
            return;
        }

        foreach ($results as $result) {
            $destinationId = $result['destination_id'] ?? null;
            if (!$destinationId) {
                continue;
            }

            $destination = ChannelPosterDestination::find($destinationId);
            if (!$destination) {
                continue;
            }

            $ok = (bool) ($result['success'] ?? false);
            $messageId = $result['message_id'] ?? null;
            $urlInfo = $ok
                ? PostUrlBuilder::build($destination, $messageId)
                : ['url' => null, 'status' => ContentSyncVersion::LINK_NONE];

            $this->upsertVersion($content, $destination, [
                'publish_status' => $ok ? ContentSyncVersion::STATUS_PUBLISHED : ContentSyncVersion::STATUS_FAILED,
                'message_id' => $messageId,
                'post_url' => $ok ? $urlInfo['url'] : null,
                'link_status' => $ok ? $urlInfo['status'] : ContentSyncVersion::LINK_NONE,
                'published_at' => $ok ? now() : null,
                'last_error' => $ok ? null : ($result['error'] ?? trans('bot.channel_sync_publish_failed')),
            ]);
        }
    }

    /* ------------------------------------------------------------------
     * 8) تکرار موارد ناموفق
     * ------------------------------------------------------------------ */

    /**
     * @return array{results: array, any_published: bool}
     */
    public function retryFailed(
        ContentSyncContent $content,
        ChannelPosterPublisher $publisher,
        ?string $adminChatId = null,
        string $origin = 'bale'
    ): array {
        $failed = $content->versions()
            ->where('publish_status', ContentSyncVersion::STATUS_FAILED)
            ->whereNotNull('destination_id')
            ->get();

        if ($failed->isEmpty()) {
            return ['results' => [], 'any_published' => false];
        }

        $results = [];
        $chunks = TextChunker::split((string) $content->text);

        foreach ($failed as $version) {
            $destination = ChannelPosterDestination::find($version->destination_id);
            if (!$destination || !$destination->is_active) {
                $results[] = ['destination_id' => $version->destination_id, 'platform' => $destination->platform, 'success' => false, 'skipped' => false, 'message_id' => null];
                continue;
            }

            if ($destination->platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
                $version->update(['publish_status' => ContentSyncVersion::STATUS_SKIPPED, 'last_error' => trans('bot.channel_sync_soroush_not_ready')]);
                $results[] = ['destination_id' => $destination->id, 'platform' => $destination->platform, 'success' => false, 'skipped' => true, 'message_id' => null];
                continue;
            }

            $lastMessageId = null;
            $ok = true;
            $error = null;

            try {
                foreach ($chunks as $chunk) {
                    $result = $publisher->publish(
                        $destination->channel_chat_id,
                        ContentSyncContent::CONTENT_TYPE_TEXT,
                        $chunk,
                        null,
                        $destination->platform,
                        $destination->bot_token
                    );
                    if (!($result['success'] ?? false)) {
                        $ok = false;
                        $error = trans('bot.channel_sync_publish_failed');
                        break;
                    }
                    $lastMessageId = $result['message_id'] ?? $lastMessageId;
                }
            } catch (Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }

            $urlInfo = $ok
                ? PostUrlBuilder::build($destination, $lastMessageId)
                : ['url' => null, 'status' => ContentSyncVersion::LINK_NONE];

            $this->upsertVersion($content, $destination, [
                'publish_status' => $ok ? ContentSyncVersion::STATUS_PUBLISHED : ContentSyncVersion::STATUS_FAILED,
                'message_id' => $lastMessageId,
                'post_url' => $ok ? $urlInfo['url'] : null,
                'link_status' => $ok ? $urlInfo['status'] : ContentSyncVersion::LINK_NONE,
                'published_at' => $ok ? now() : null,
                'last_error' => $ok ? null : $error,
                'chunks_count' => count($chunks),
            ]);

            $this->service->logPublish(
                $content->bot_id,
                $destination->id,
                $destination->platform,
                $ok,
                $lastMessageId,
                $ok ? null : $error,
                null,
                $ok ? $urlInfo['url'] : null,
                $ok ? $urlInfo['status'] : null,
                $content->id
            );

            $results[] = ['destination_id' => $destination->id, 'platform' => $destination->platform, 'success' => $ok, 'skipped' => false, 'message_id' => $lastMessageId];
        }

        ContentSyncOperation::log(
            $content->bot_id,
            $adminChatId,
            $origin,
            $content->id,
            ContentSyncOperation::OP_RETRY,
            collect($results)->contains(fn ($r) => $r['success']) ? ContentSyncOperation::RESULT_OK : ContentSyncOperation::RESULT_FAILED
        );

        return [
            'results' => $results,
            'any_published' => collect($results)->contains(fn ($r) => $r['success']),
        ];
    }

    /* ------------------------------------------------------------------
     * 9) گزارش و یافتن محتوا
     * ------------------------------------------------------------------ */

    /**
     * یافتن محتوا با id عددی یا لینک مبدأ.
     */
    public function findContent(Bot $bot, string $query): ?ContentSyncContent
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $query) === 1) {
            return ContentSyncContent::where('bot_id', $bot->id)->find((int) $query);
        }

        $parsed = PostUrlParser::parse($query);
        if ($parsed) {
            $found = ContentSyncContent::findBySource(
                $bot->id,
                $parsed['platform'],
                $parsed['channel_identifier'],
                $parsed['message_identifier']
            );
            if ($found) {
                return $found;
            }
        }

        // تطبیق روی لینک خام ذخیره‌شده
        return ContentSyncContent::where('bot_id', $bot->id)
            ->where(function ($q) use ($query) {
                $q->where('source_url_raw', $query)->orWhere('source_url', $query);
            })
            ->first();
    }

    public function lastContent(Bot $bot): ?ContentSyncContent
    {
        return ContentSyncContent::where('bot_id', $bot->id)
            ->latest('id')
            ->first();
    }

    /**
     * گزارش کامل /sync — همه نسخه‌ها + وضعیت لینک + تاریخچه عملیات.
     */
    public function buildReport(ContentSyncContent $content): string
    {
        $content->load('versions');
        $platforms = $this->platformLabels();

        $lines = [
            trans('bot.channel_sync_report_header', ['id' => $content->id]),
            trans('bot.channel_sync_source', [
                'platform' => $platforms[$content->source_platform] ?? $content->source_platform,
                'url' => $content->source_url,
            ]),
        ];

        $versions = $content->versions;
        foreach ($versions as $version) {
            if ($version->destination_id === null && $version->publish_status === ContentSyncVersion::STATUS_PUBLISHED && $version->platform === $content->source_platform) {
                continue; // نسخه مبدأ، همان خط source است
            }

            $platform = $platforms[$version->platform] ?? $version->platform;
            $statusIcon = match ($version->publish_status) {
                ContentSyncVersion::STATUS_PUBLISHED => '✅',
                ContentSyncVersion::STATUS_QUEUED => '⏳',
                ContentSyncVersion::STATUS_FAILED => '❌',
                ContentSyncVersion::STATUS_SKIPPED => '⚠️',
                default => '•',
            };

            $link = $version->post_url
                ? ' — ' . $version->post_url . ' (' . $this->linkStatusLabel($version->link_status) . ')'
                : '';

            $error = $version->last_error ? ' — ' . $version->last_error : '';

            $lines[] = "{$statusIcon} {$platform} {$error}{$link}";
        }

        if (mb_strlen(implode("\n", $lines)) > 3500) {
            $lines[] = trans('bot.channel_sync_report_truncated');
        }

        return implode("\n", $lines);
    }

    /**
     * پاسخ کوتاه fast mode: «✅ مطلب ۱۴۸ ثبت شد | بله ✅ | ... | آماده لینک بعدی»
     */
    public function buildFastConfirmation(int $contentId, array $results): string
    {
        $platforms = $this->platformLabels();
        $parts = [];

        foreach ($results as $result) {
            $label = $platforms[$result['platform']] ?? $result['platform'];
            $icon = $result['success']
                ? '✅'
                : (($result['skipped'] ?? false) ? '⚠️' : '❌');
            $parts[] = $label . ' ' . $icon;
        }

        $ready = trans('bot.channel_sync_ready');

        return trans('bot.channel_sync_registered', ['id' => $contentId])
            . (count($parts) > 0 ? ' | ' . implode(' | ', $parts) : '')
            . ' | ' . $ready;
    }

    private function linkStatusLabel(string $status): string
    {
        return match ($status) {
            ContentSyncVersion::LINK_CONFIRMED => trans('bot.channel_sync_link_confirmed'),
            ContentSyncVersion::LINK_CONSTRUCTED => trans('bot.channel_sync_link_constructed'),
            ContentSyncVersion::LINK_UNVERIFIED => trans('bot.channel_sync_link_unverified'),
            default => trans('bot.channel_sync_link_none'),
        };
    }

    public function platformLabels(): array
    {
        return [
            'bale' => trans('bot.channel_poster_sig_bale'),
            'telegram' => trans('bot.channel_poster_sig_telegram'),
            'eitaa' => trans('bot.channel_poster_sig_eitaa'),
            'soroush' => trans('bot.channel_poster_sig_soroush'),
        ];
    }

    /* ------------------------------------------------------------------
     * helpers
     * ------------------------------------------------------------------ */

    private function upsertVersion(
        ContentSyncContent $content,
        ChannelPosterDestination $destination,
        array $attributes
    ): ContentSyncVersion {
        $version = ContentSyncVersion::where('content_id', $content->id)
            ->where('destination_id', $destination->id)
            ->first();

        $base = [
            'bot_id' => $content->bot_id,
            'platform' => $destination->platform,
            'channel_id' => (string) $destination->channel_chat_id,
            'channel_username' => $destination->channel_title,
            'destination_id' => $destination->id,
        ];

        if ($version) {
            $version->forceFill(array_merge($base, $attributes))->save();

            return $version;
        }

        return ContentSyncVersion::create(array_merge($base, $attributes, [
            'content_id' => $content->id,
        ]));
    }
}
