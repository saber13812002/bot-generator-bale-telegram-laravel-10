<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterQueue;
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
            $destinations = $this->service->resolveByTag($bot->id, $tag);
            if ($destinations->isEmpty()) {
                $destinations = $this->service->resolveDestinations($bot->id, 'all');
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
                $result = $publisher->publish(
                    $destination->channel_chat_id,
                    $item->content_type,
                    $text,
                    $item->file_id,
                    $destination->platform,
                    $destination->bot_token
                );
                $ok = $result['success'] ?? false;
                $messageId = $result['message_id'] ?? null;

                $this->service->logPublish(
                    $bot->id,
                    $destination->id,
                    $destination->platform,
                    $ok,
                    $messageId,
                    null,
                    $item->id
                );

                $results[] = [
                    'destination_id' => $destination->id,
                    'platform' => $destination->platform,
                    'success' => $ok,
                ];

                if (!$ok) {
                    $allOk = false;
                }
            }

            $item->update([
                'status' => $allOk ? ChannelPosterQueue::STATUS_PUBLISHED : ChannelPosterQueue::STATUS_FAILED,
                'published_at' => now(),
            ]);

            // Notify owner
            if ($notifyOwner && $item->owner_chat_id) {
                try {
                    $report = $this->service->buildPublishReport($results, $destinations);
                    $msg = trans('bot.channel_poster_queue_published') . "\n" . $report;
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
