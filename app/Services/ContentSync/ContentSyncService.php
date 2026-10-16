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

        // نسخه خود پیام مبدأ (لینکی که ادمین فرستاده — از قبل در کانال مبدأ منتشرشده)
        // وضعیت existing_source: هرگز دوباره ارسال نمی‌شود و «failed» هم نمی‌شود؛
        // فقط مرجع ارجاع با post_url + message_id اصلِ پیام.
        ContentSyncVersion::create([
            'content_id' => $content->id,
            'bot_id' => $bot->id,
            'destination_id' => $sourceDestinationId,
            'platform' => $parsed['platform'],
            'channel_id' => $channelId,
            // برای نمایش در گزارش: اگر لینک username جدا نداشته باشد (ایتا/تلگرام)،
            // خود شناسه‌ی کانال نمایش داده می‌شود.
            'channel_username' => $parsed['channel_username'] ?? ($parsed['channel_identifier'] ?? null),
            'message_id' => $messageId,
            'post_url' => $parsed['canonical_url'],
            'link_status' => ContentSyncVersion::LINK_CONFIRMED,
            'publish_status' => ContentSyncVersion::STATUS_EXISTING_SOURCE,
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
        string $origin = 'bale',
        bool $useFast = true
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

        // 4) آخرین تگ این ادمین (فقط در حالت سریع؛ در غیر این صورت دوباره سؤال می‌شود)
        if ($useFast && $adminChatId !== null) {
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
     * 4) مقصدهای قابل انتشار (قانون حذف تکراری + شناسایی دقیق کانال مبدأ)
     * ------------------------------------------------------------------ */

    /**
     * مقصدهای قابل انتشار (پشتیبانی از resolveTargetsDetailed).
     */
    public function resolveTargets(ContentSyncContent $content, string $tag): Collection
    {
        return $this->resolveTargetsDetailed($content, $tag)['targets'];
    }

    /**
     * مقصدهای قابل انتشار + مقصدهای حذف‌شده با دلیل (برای گزارش و بررسی ادمین).
     *
     * فیلتر قبل از هر فراخوانی API اعمال می‌شود (نه فقط در نمایش).
     *
     * قانون پیش‌فرض (exclude_source_channel): فقط کانالی که لینک اصلی از آن
     * آمده حذف می‌شود؛ کانال‌های دیگر حتی در همان پیام‌رسان منتشر می‌شوند.
     * حالت exclude_source_platform: همه‌ی کانال‌های همان پیام‌رسان حذف می‌شوند.
     * اگر تطبیق شناسه مبهم باشد (suspicious) آن مقصد متوقف و برای بررسی
     * ادمین به گزارش اضافه می‌شود.
     *
     * @return array{targets: Collection, excluded: array<int, array{0: ChannelPosterDestination, 1: string}>}
     */
    public function resolveTargetsDetailed(
        ContentSyncContent $content,
        string $tag,
        ?string $adminChatId = null,
        string $origin = 'bale'
    ): array {
        $destinations = $this->service->resolveByTag($content->bot_id, $tag);
        if ($destinations->isEmpty()) {
            $destinations = $this->service->resolveDestinations($content->bot_id, 'all');
        }

        $dedupMode = ContentSyncAdminPrefs::getDedupMode(
            $content->bot_id,
            (string) ($adminChatId ?? ''),
            $origin
        );
        $versioned = $content->versions()->pluck('destination_id')->filter()->all();

        $targets = [];
        $excluded = [];

        foreach ($destinations as $destination) {
            // قبلاً نسخه‌ای برای این مقصد ثبت شده → هرگز دوباره ارسال نمی‌شود
            if (in_array($destination->id, $versioned, true)) {
                $excluded[] = [$destination, 'already_published'];
                continue;
            }

            if ($destination->platform === $content->source_platform) {
                if ($dedupMode === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM) {
                    $excluded[] = [$destination, $this->sourceChannelVerdict($content, $destination) === true
                        ? 'source_channel'
                        : 'source_platform'];
                    continue;
                }

                $verdict = $this->sourceChannelVerdict($content, $destination);
                if ($verdict === true) {
                    $excluded[] = [$destination, 'source_channel'];
                    continue;
                }
                if ($verdict === null) {
                    // تطبیق مبهم — تا بررسی ادمین به این مقصد ارسال نمی‌شود
                    $excluded[] = [$destination, 'suspicious'];
                    continue;
                }
                // verdict === false: کانالِ دیگرِ همان پیام‌رسان → قابل انتشار
            }

            $targets[] = $destination;
        }

        return [
            'targets' => collect($targets)->values(),
            'excluded' => $excluded,
        ];
    }

    /**
     * آیا مقصد همان کانالِ مبدأِ لینک است؟ (تطبیق بر پایه‌ی شناسه‌ی نرمال‌شده)
     *
     * true  — مطمئن: همان کانال است (حذف می‌شود)
     * false — مطمئن: کانال دیگر است (قابل انتشار)
     * null  — مبهم: نمی‌توان اثبات کرد که یکی نیستند (متوقف + گزارش)
     */
    public function sourceChannelVerdict(ContentSyncContent $content, ChannelPosterDestination $destination): ?bool
    {
        if ($destination->platform !== $content->source_platform) {
            return false;
        }

        $sourceChannel = trim((string) $content->source_channel_id);
        $destChatId = trim((string) $destination->channel_chat_id);

        if ($sourceChannel === '' || $destChatId === '') {
            return null;
        }

        // تلگرام — کانال خصوصی (لینک t.me/c/{shortId}/...)
        if (str_starts_with($sourceChannel, 'c/')) {
            $short = ltrim(substr($sourceChannel, 2), '0') ?: '0';
            $isNumericDest = ctype_digit($destChatId)
                || (str_starts_with($destChatId, '-') && ctype_digit(substr($destChatId, 1)));
            if ($isNumericDest) {
                $normalizedDest = ltrim($destChatId, '-');
                // chat id واقعی تلگرام = -(1000000000000 + shortId)
                $expected = (string) (1000000000000 + (int) $short);
                if ($normalizedDest === $expected || $normalizedDest === $short) {
                    return true;
                }
                return false; // مقصد عددی و مشخص است → مطمئنا کانال دیگر
            }
            // مقصد با username ذخیره شده؛ short id قابل تطبیق مطمئن با username نیست
            return null;
        }

        // مقایسه عددی (بله: chat id عددی)
        $isNumericSource = ctype_digit($sourceChannel);
        $isNumericDest = ctype_digit($destChatId)
            || (str_starts_with($destChatId, '-') && ctype_digit(substr($destChatId, 1)));
        if ($isNumericSource && $isNumericDest) {
            return ltrim($sourceChannel, '0') === ltrim(ltrim($destChatId, '-'), '0');
        }

        // مقایسه username (تلگرام/ایتا/سروش) — بی‌تفاوت به uppercase/lowercase و @
        $sourceUser = ltrim($sourceChannel, '@');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,}$/', $sourceUser) === 1
            && preg_match('/^@?[A-Za-z][A-Za-z0-9_]{2,}$/', $destChatId) === 1
        ) {
            return strcasecmp(ltrim($sourceUser, '@'), ltrim($destChatId, '@')) === 0;
        }

        // فرمت‌ها با هم جور در نمی‌آیند (مثلاً username در مقابل chat id عددی)
        return null;
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
        $targets = $this->resolveTargetsDetailed($content, $tag, $adminChatId, $origin)['targets'];
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
        $targets = $this->resolveTargetsDetailed($content, $tag, $adminChatId, $origin)['targets'];
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

        $dedupMode = ContentSyncAdminPrefs::getDedupMode(
            $content->bot_id,
            (string) ($adminChatId ?? ''),
            $origin
        );
        $results = [];
        $chunks = TextChunker::split((string) $content->text);

        foreach ($failed as $version) {
            $destination = ChannelPosterDestination::find($version->destination_id);
            if (!$destination || !$destination->is_active) {
                $results[] = ['destination_id' => $version->destination_id, 'platform' => $version->platform, 'success' => false, 'skipped' => false, 'message_id' => null];
                continue;
            }

            // همان قانون dedup — هیچ retry نباید دوباره به کانال مبدأ منتشر کند
            if ($destination->platform === $content->source_platform) {
                $verdict = $this->sourceChannelVerdict($content, $destination);
                if ($dedupMode === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM || $verdict === true) {
                    $version->update([
                        'publish_status' => ContentSyncVersion::STATUS_SKIPPED,
                        'last_error' => trans($dedupMode === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM
                            ? 'bot.channel_sync_report_reason_source_platform'
                            : 'bot.channel_sync_report_reason_source_channel'),
                    ]);
                    $results[] = ['destination_id' => $destination->id, 'platform' => $destination->platform, 'success' => false, 'skipped' => true, 'message_id' => null];
                    continue;
                }
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
     * گزارش کامل /sync — همه نسخه‌ها + وضعیت لینک + مقصدهای حذف‌شده با دلیل.
     */
    public function buildReport(ContentSyncContent $content): string
    {
        return implode("\n", array_merge($this->reportLines($content), [
            $this->helpFooter(),
        ]));
    }

    /**
     * پاسخ fast mode: گزارش کامل + «آماده لینک بعدی» + فوتر راهنما.
     */
    public function buildFastConfirmation(ContentSyncContent $content): string
    {
        return implode("\n", array_merge($this->reportLines($content), [
            trans('bot.channel_sync_ready'),
            $this->helpFooter(),
        ]));
    }

    /**
     * خطوط گزارش (بدون فوتر راهنما):
     * هدر، خط مبدأ، شناسه مشترک مطلب، هر نسخه با آیکون وضعیت + جزئیات،
     * و مقصدهایی که قبل از هر فراخوانی API فیلتر شده‌اند (با دلیل).
     */
    private function reportLines(ContentSyncContent $content): array
    {
        $content->load('versions');
        $platforms = $this->platformLabels();

        $lines = [
            trans('bot.channel_sync_report_header', ['id' => $content->id]),
            trans('bot.channel_sync_source', [
                'platform' => $platforms[$content->source_platform] ?? $content->source_platform,
                'url' => $content->source_url,
            ]),
            trans('bot.channel_sync_report_content_id', ['id' => $content->id]),
        ];

        $versionedDestinationIds = [];

        foreach ($content->versions as $version) {
            $versionedDestinationIds[] = $version->destination_id;

            $platform = $platforms[$version->platform] ?? $version->platform;
            $channel = trim((string) ($version->channel_username ?? ''));
            $channelPart = $channel !== '' ? " ({$channel})" : '';

            [$icon, $statusLabel, $details] = match ($version->publish_status) {
                ContentSyncVersion::STATUS_EXISTING_SOURCE => [
                    '⏭',
                    trans('bot.channel_sync_report_status_existing_source'),
                    [trans('bot.channel_sync_report_reason_source')],
                ],
                ContentSyncVersion::STATUS_PUBLISHED => [
                    '✅',
                    trans('bot.channel_sync_report_status_published'),
                    $version->post_url
                        ? [trans('bot.channel_sync_link_detail', [
                            'url' => $version->post_url,
                            'status' => $this->linkStatusLabel($version->link_status),
                        ])]
                        : [],
                ],
                ContentSyncVersion::STATUS_QUEUED => [
                    '⏳',
                    trans('bot.channel_sync_report_status_queued'),
                    [],
                ],
                ContentSyncVersion::STATUS_FAILED => [
                    '❌',
                    trans('bot.channel_sync_report_status_failed'),
                    $version->last_error
                        ? [trans('bot.channel_sync_error_detail', ['error' => $version->last_error])]
                        : [],
                ],
                default => [
                    '⚠️',
                    trans('bot.channel_sync_report_status_skipped'),
                    $version->last_error
                        ? [trans('bot.channel_sync_error_detail', ['error' => $version->last_error])]
                        : [],
                ],
            };

            $lines[] = "{$icon} {$platform}{$channelPart} — {$statusLabel}";
            foreach ($details as $detail) {
                $lines[] = "  {$detail}";
            }
        }

        // مقصدهایی که فیلتر شدند ولی هنوز نسخه‌ای برای آن‌ها ساخته نشده
        // (مثلاً قبل از فراخوانی API یا در صف شناسایی و حذف شده‌اند)
        $plan = $this->resolveTargetsDetailed(
            $content,
            (string) ($content->tag ?: 'all'),
            $content->created_by_chat_id,
            (string) $content->created_by_origin
        );

        foreach ($plan['excluded'] as [$destination, $reason]) {
            if (in_array($destination->id, $versionedDestinationIds, true)) {
                continue;
            }

            $platform = $platforms[$destination->platform] ?? $destination->platform;
            $title = trim((string) $destination->channel_title);
            $titlePart = $title !== '' ? " ({$title})" : '';

            [$icon, $statusLabel, $detail] = match ($reason) {
                'source_channel' => [
                    '⏭',
                    trans('bot.channel_sync_report_status_existing_source'),
                    trans('bot.channel_sync_report_reason_source_channel'),
                ],
                'source_platform' => [
                    '⏭',
                    trans('bot.channel_sync_report_status_existing_source'),
                    trans('bot.channel_sync_report_reason_source_platform'),
                ],
                'suspicious' => [
                    '⛔',
                    trans('bot.channel_sync_report_status_suspicious'),
                    trans('bot.channel_sync_report_reason_suspicious'),
                ],
                default => [
                    '⏭',
                    trans('bot.channel_sync_report_status_skipped'),
                    trans('bot.channel_sync_report_reason_already_published'),
                ],
            };

            $lines[] = "{$icon} {$platform}{$titlePart} — {$statusLabel}";
            $lines[] = "  {$detail}";
        }

        if (mb_strlen(implode("\n", $lines)) > 3500) {
            $lines[] = trans('bot.channel_sync_report_truncated', ['id' => $content->id]);
        }

        return $lines;
    }

    /**
     * فوتر راهنما برای گزارش‌ها و پیام‌های اصلی (نه خطاهای کوتاه تکراری).
     */
    public function helpFooter(): string
    {
        return trans('bot.channel_sync_help_footer');
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
