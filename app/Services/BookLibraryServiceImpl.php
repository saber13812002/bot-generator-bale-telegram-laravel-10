<?php

namespace App\Services;

use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryBotConfig;
use App\Models\LibraryUserSubscription;
use Illuminate\Support\Facades\Log;

class BookLibraryServiceImpl implements BookLibraryService
{
    public function getOrCreateSubscription(BotUsers $botUser, int $botId): LibraryUserSubscription
    {
        $subscription = LibraryUserSubscription::firstOrCreate(
            [
                'bot_user_id' => $botUser->id,
                'bot_id' => $botId,
            ],
            [
                'plan' => 'free',
                'books_used' => 0,
                'books_limit' => config('book_library.plans.free.limit', 3),
                'status' => 'active',
            ]
        );

        return $subscription;
    }

    public function buildProgressBar(LibraryUserSubscription $subscription): string
    {
        $used = $subscription->books_used;
        $limit = $subscription->books_limit;
        $percent = $limit > 0 ? (int) round(($used / $limit) * 100) : 0;
        $filled = (int) round($percent / 10);
        $bar = str_repeat('█', $filled) . str_repeat('░', 10 - $filled);

        return trans('book_library.progress', [
            'bar' => $bar,
            'used' => $used,
            'limit' => $limit,
            'percent' => $percent,
        ]);
    }

    public function getBotConfig(int $botId): ?LibraryBotConfig
    {
        return LibraryBotConfig::where('bot_id', $botId)->first();
    }

    public function markReaderStarted(BotUsers $botUser, int $mainBotId): void
    {
        $botUser->settings(['library_reader_started_' . $mainBotId => true]);
    }

    public function hasReaderStarted(BotUsers $botUser, int $mainBotId): bool
    {
        return (bool) $botUser->setting('library_reader_started_' . $mainBotId, false);
    }

    public function getReaderBotUsername(int $mainBotId): ?string
    {
        $config = $this->getBotConfig($mainBotId);
        if (!$config || !$config->reader_bot_id) {
            return null;
        }

        $readerBot = Bot::find($config->reader_bot_id);
        if (!$readerBot) {
            return null;
        }

        return $readerBot->bale_bot_name ?: $readerBot->telegram_bot_name;
    }
}
