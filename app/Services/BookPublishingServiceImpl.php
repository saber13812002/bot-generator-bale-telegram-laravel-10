<?php

namespace App\Services;

use App\Interfaces\Services\BookPublishingService;
use App\Models\BookPageScan;
use App\Models\BookPublishingQueue;
use App\Models\BookPublishingChannel;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookPublishingServiceImpl implements BookPublishingService
{
    public function addToQueue(BookPageScan $scan, ?int $voiceId = null): BookPublishingQueue
    {
        Log::info('BookPublishingService - Adding to queue', [
            'scan_id' => $scan->id,
            'voice_id' => $voiceId,
            'bot_id' => $scan->bot_id
        ]);

        // Get all active channels for this bot
        $channels = BookPublishingChannel::where('bot_id', $scan->bot_id)
            ->where('is_active', true)
            ->get();

        $queueItems = [];

        foreach ($channels as $channel) {
            // Random time between 7 PM (19:00) and 12 AM (23:59) in next 7 days
            $randomHour = rand(19, 23);
            $randomMinute = rand(0, 59);
            $scheduledAt = now()->addDays(rand(0, 7))->setTime($randomHour, $randomMinute);
            
            $queueItem = BookPublishingQueue::create([
                'book_page_scan_id' => $scan->id,
                'book_page_voice_id' => $voiceId,
                'bot_id' => $scan->bot_id,
                'channel_id' => $channel->id,
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
            ]);

            $queueItems[] = $queueItem;
        }

        Log::info('BookPublishingService - Added to queue', [
            'scan_id' => $scan->id,
            'queue_items_count' => count($queueItems)
        ]);

        return $queueItems[0] ?? null;
    }

    public function getRandomPendingItem(int $botId): ?BookPublishingQueue
    {
        $items = BookPublishingQueue::where('bot_id', $botId)
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->inRandomOrder()
            ->limit(1)
            ->get();

        return $items->first();
    }

    public function publishToChannels(BookPublishingQueue $queueItem, int $botId, string $type): bool
    {
        Log::info('BookPublishingService - Publishing to channels', [
            'queue_item_id' => $queueItem->id,
            'bot_id' => $botId,
            'type' => $type
        ]);

        try {
            $scan = $queueItem->bookPageScan;
            if (!$scan) {
                Log::error('BookPublishingService - Scan not found', [
                    'queue_item_id' => $queueItem->id
                ]);
                return false;
            }

            $channel = $queueItem->channel;
            if (!$channel || !$channel->is_active) {
                Log::warning('BookPublishingService - Channel not found or inactive', [
                    'queue_item_id' => $queueItem->id,
                    'channel_id' => $queueItem->channel_id
                ]);
                return false;
            }

            // Get bot token
            $bot = \App\Models\Bot::find($botId);
            if (!$bot) {
                Log::error('BookPublishingService - Bot not found', ['bot_id' => $botId]);
                return false;
            }

            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::error('BookPublishingService - Bot token not found', ['bot_id' => $botId]);
                return false;
            }

            $telegramBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            $book = $scan->bookPage->book;
            $caption = "📚 {$book->name}\n📄 صفحه {$scan->page_number}";

            // If voice exists, send voice with photo
            if ($queueItem->book_page_voice_id && $queueItem->bookPageVoice) {
                $voice = $queueItem->bookPageVoice;
                $result = $telegramBot->sendVoice([
                    'chat_id' => $channel->channel_chat_id,
                    'voice' => $voice->file_id,
                    'caption' => $caption
                ]);

                // Also send photo
                $telegramBot->sendPhoto([
                    'chat_id' => $channel->channel_chat_id,
                    'photo' => $scan->file_id,
                    'caption' => $caption
                ]);
            } else {
                // Just send photo
                $result = $telegramBot->sendPhoto([
                    'chat_id' => $channel->channel_chat_id,
                    'photo' => $scan->file_id,
                    'caption' => $caption
                ]);
            }

            if ($result && isset($result['ok']) && $result['ok']) {
                $this->markAsPublished($queueItem);
                Log::info('BookPublishingService - Published successfully', [
                    'queue_item_id' => $queueItem->id
                ]);
                return true;
            }

            Log::error('BookPublishingService - Failed to publish', [
                'queue_item_id' => $queueItem->id,
                'result' => $result
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('BookPublishingService - Error publishing', [
                'error' => $e->getMessage(),
                'queue_item_id' => $queueItem->id
            ]);
            return false;
        }
    }

    public function markAsPublished(BookPublishingQueue $queueItem): bool
    {
        $queueItem->status = 'published';
        $queueItem->published_at = now();
        $queueItem->save();

        Log::info('BookPublishingService - Marked as published', [
            'queue_item_id' => $queueItem->id
        ]);

        return true;
    }
}
