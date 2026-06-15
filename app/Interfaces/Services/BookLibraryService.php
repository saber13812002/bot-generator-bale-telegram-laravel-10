<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use App\Models\LibraryBook;
use App\Models\LibraryUserBook;
use App\Models\LibraryUserSubscription;
use Telegram;

interface BookLibraryService
{
    public function getOrCreateSubscription(BotUsers $botUser, int $botId): LibraryUserSubscription;

    public function buildProgressBar(LibraryUserSubscription $subscription): string;

    public function getBotConfig(int $botId): ?\App\Models\LibraryBotConfig;

    public function markReaderStarted(BotUsers $botUser, int $mainBotId): void;

    public function hasReaderStarted(BotUsers $botUser, int $mainBotId): bool;

    public function getReaderBotUsername(int $mainBotId): ?string;
}
