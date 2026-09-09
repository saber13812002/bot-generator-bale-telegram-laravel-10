<?php

namespace App\Jobs;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChannelPosterQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        ChannelPosterBotService $service,
        ChannelPosterPublisherFactory $publisherFactory
    ): void {
        $items = ChannelPosterQueue::ready()
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        foreach ($items as $item) {
            $this->processItem($item, $service, $publisherFactory);
        }
    }

    private function processItem(
        ChannelPosterQueue $item,
        ChannelPosterBotService $service,
        ChannelPosterPublisherFactory $publisherFactory
    ): void {
        try {
            $bot = Bot::find($item->bot_id);
            if (!$bot) {
                $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);
                return;
            }

            $tag = $item->tag ?? '';
            $destinations = $service->resolveByTag($bot->id, $tag);
            if ($destinations->isEmpty()) {
                $destinations = $service->resolveDestinations($bot->id, 'all');
            }

            if ($destinations->isEmpty()) {
                $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);
                return;
            }

            // Append signature if needed
            $text = $item->text;
            if ($item->signature_enabled && $text !== null) {
                $sig = $service->buildSignature($bot->id, $tag);
                if ($sig !== '') {
                    $text .= $sig;
                }
            }

            // Determine which token/origin to use for publishing
            $ownerOrigin = $item->owner_origin ?? 'bale';
            $botToken = $ownerOrigin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$botToken) {
                $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);
                return;
            }

            $publisher = $publisherFactory->make($botToken, $ownerOrigin);

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

                $service->logPublish(
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
            if ($item->owner_chat_id) {
                $report = $service->buildPublishReport($results, $destinations);
                $msg = trans('bot.channel_poster_queue_published') . "\n" . $report;
                $publisher->sendPrivateMessage($item->owner_chat_id, $msg);
            }

            Log::info('[ChannelPoster] Queue item published', [
                'queue_id' => $item->id,
                'bot_id' => $bot->id,
                'all_ok' => $allOk,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ChannelPoster] Queue processing failed', [
                'queue_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);
        }
    }
}
