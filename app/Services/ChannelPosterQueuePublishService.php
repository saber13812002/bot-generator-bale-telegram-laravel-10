<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterPublishLog;
use App\Models\ChannelPosterQueue;
use App\Models\ContentSyncAdminPrefs;
use App\Models\ContentSyncContent;
use App\Models\ContentSyncVersion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سرویس مشترک انتشار آیتم‌های صف channel-poster
 *
 * استفاده‌کنندگان:
 * - ProcessChannelPosterQueueJob (جوب هر ۵ دقیقه)
 * - ارسال دستی از ربات (/queue → send now)
 * - دستور channel-poster:send-queued
 * - watchdog ساعتی PostOverdueWatchdog
 *
 * فقط آیتم‌هایی با status = pending منتشر می‌شوند (idempotent).
 */
class ChannelPosterQueuePublishService
{
    public function __construct(
        private ChannelPosterBotService $service,
        private ChannelPosterPublisherFactory $publisherFactory,
        private \App\Services\ContentSync\ContentSyncService $contentSync,
        private ChannelPosterMediaStore $mediaStore,
    ) {
    }

    /**
     * انتشار یک آیتم صف
     *
     * @return array{status: string, error: string|null, results: array, destinations_count: int}
     *   status ∈ published|failed|skipped
     */
    public function publishItem(ChannelPosterQueue $item, bool $notifyOwner = true): array
    {
        $fail = function (?string $error = null) use ($item): array {
            $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);
            Log::warning('[ChannelPoster] Queue item failed', [
                'queue_id' => $item->id,
                'error'    => $error,
            ]);

            return ['status' => 'failed', 'error' => $error, 'results' => [], 'destinations_count' => 0];
        };

        try {
            // فقط آیتم pending منتشر می‌شود (جلوگیری از انتشار مجدد)
            if ($item->status !== ChannelPosterQueue::STATUS_PENDING) {
                return ['status' => 'skipped', 'error' => 'item is not pending', 'results' => [], 'destinations_count' => 0];
            }

            $bot = Bot::find($item->bot_id);
            if (!$bot) {
                return $fail('bot not found');
            }

            $tag = $item->tag ?? '';

            if ($item->content_id) {
                // §2: برای آیتم‌های content-sync هیچ fallback به «همه کانال‌ها» نداریم.
                // فقط مقصدهای فعالِ تگ + فقط مقصدهای نسخه‌های queued این آیتم.
                $destinations = $this->service->resolveByTag($bot->id, $tag);

                $queuedVersionDestIds = ContentSyncVersion::where('content_id', $item->content_id)
                    ->where('queue_id', $item->id)
                    ->where('publish_status', ContentSyncVersion::STATUS_QUEUED)
                    ->whereNotNull('destination_id')
                    ->pluck('destination_id')
                    ->all();

                if ($queuedVersionDestIds !== []) {
                    $destinations = $destinations->filter(
                        fn ($d) => in_array($d->id, $queuedVersionDestIds, true)
                    )->values();
                }
            } else {
                // رفتار قدیمی برای آیتم‌های صف بدون content (تغییر نکرده)
                $destinations = $this->service->resolveByTag($bot->id, $tag);
                if ($destinations->isEmpty()) {
                    $destinations = $this->service->resolveDestinations($bot->id, 'all');
                }
            }

            if ($destinations->isEmpty()) {
                return $fail('no destinations resolved');
            }

            // Append signature if needed
            $text = $item->text;
            if ($item->signature_enabled && $text !== null) {
                $sig = $this->service->buildSignature($bot->id, $tag);
                if ($sig !== '') {
                    $text .= $sig;
                }
            }

            // §4: امضای منبع — حالتی که در لحظه صف‌بندی فریز شده بود؛ برای آیتم‌های
            // content فقط اضافه می‌شود (روی متن ذخیره‌شده، نه ستون textِ محتوا).
            if ($item->content_id && $text !== null && $item->source_attribution
                && $item->source_attribution !== \App\Models\ContentSyncAdminPrefs::ATTR_NONE
            ) {
                $content = \App\Models\ContentSyncContent::find($item->content_id);
                if ($content) {
                    $suffix = $this->contentSync->buildAttributionSuffix($content, (string) $item->source_attribution);
                    if (trim($suffix) !== '') {
                        $text .= "\n\n" . $suffix;
                    }
                }
            }

            // Determine which token/origin to use for publishing
            $ownerOrigin = $item->owner_origin ?? 'bale';
            $botToken = $ownerOrigin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$botToken) {
                return $fail('bot token not configured for origin: ' . $ownerOrigin);
            }

            $publisher = $this->publisherFactory->make($botToken, $ownerOrigin);

            $allOk = true;
            $results = [];
            foreach ($destinations as $destination) {
                // جلوگیری از انتشار مجدد به مقصدی که قبلا در این آیتم موفق بوده
                // (در انتشارهای جزئی — مثلا ۲ از ۳ کانال — ریکورد «failed» می‌ماند
                // تا فقط مقصدهای شکست‌خورده تکرار شوند).
                if (ChannelPosterPublishLog::where('queue_id', $item->id)
                    ->where('destination_id', $destination->id)
                    ->where('success', true)
                    ->exists()
                ) {
                    Log::info('[ChannelPoster] Queue destination already published, skipping', [
                        'queue_id' => $item->id,
                        'destination_id' => $destination->id,
                    ]);
                    $results[] = [
                        'destination_id' => $destination->id,
                        'platform' => $destination->platform,
                        'success' => true,
                        'skipped' => true,
                        'message_id' => null,
                    ];
                    continue;
                }

                $result = $publisher->publish(
                    $destination->channel_chat_id,
                    $item->content_type,
                    $text,
                    $item->file_id,
                    $destination->platform,
                    $destination->bot_token,
                    $item->media_path
                );
                $ok = $result['success'] ?? false;
                $messageId = $result['message_id'] ?? null;
                $error = $result['error'] ?? null;

                $this->service->logPublish(
                    $bot->id,
                    $destination->id,
                    $destination->platform,
                    $ok,
                    $messageId,
                    is_string($error) ? mb_substr($error, 0, 1000) : null,
                    $item->id
                );

                $results[] = [
                    'destination_id' => $destination->id,
                    'platform' => $destination->platform,
                    'success' => $ok,
                    'message_id' => $messageId,
                    'error' => $error,
                ];

                if (!$ok) {
                    $allOk = false;
                }
            }

            $item->update([
                'status' => $allOk ? ChannelPosterQueue::STATUS_PUBLISHED : ChannelPosterQueue::STATUS_FAILED,
                'published_at' => now(),
            ]);

            // پس از انتشار موفق، فایل رسانه حذف می‌شود؛ در صورت شکست برای
            // /retry روی دیسک می‌ماند (پاک‌سازی یتیم‌ها با prune-stale).
            if ($allOk) {
                $this->mediaStore->delete($item->media_path);
                $item->update(['media_path' => null]);
            }

            // ثبت نسخه‌های همگام‌سازی (post_url + link_status)
            if ($item->content_id) {
                try {
                    $this->contentSync->recordQueueResults($item, $results);
                } catch (Throwable $e) {
                    Log::warning('[ChannelPoster] Failed to record content versions', [
                        'queue_id' => $item->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            // Notify owner
            if ($notifyOwner && $item->owner_chat_id) {
                try {
                    $report = $this->service->buildPublishReport($results, $destinations);
                    $header = $allOk
                        ? trans('bot.channel_poster_queue_published')
                        : trans('bot.channel_poster_queue_some_failed');
                    $msg = $header . "\n" . $report;
                    $publisher->sendPrivateMessage($item->owner_chat_id, $msg);
                } catch (Throwable $e) {
                    Log::warning('[ChannelPoster] Failed to notify owner', [
                        'queue_id' => $item->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            Log::info('[ChannelPoster] Queue item published', [
                'queue_id' => $item->id,
                'bot_id'   => $bot->id,
                'all_ok'   => $allOk,
            ]);

            return [
                'status'             => $allOk ? 'published' : 'failed',
                'error'              => $allOk ? null : 'one or more destinations failed',
                'results'            => $results,
                'destinations_count' => $destinations->count(),
            ];
        } catch (Throwable $e) {
            Log::error('[ChannelPoster] Queue processing failed', [
                'queue_id' => $item->id,
                'error'    => $e->getMessage(),
            ]);

            return $fail($e->getMessage());
        }
    }
}
