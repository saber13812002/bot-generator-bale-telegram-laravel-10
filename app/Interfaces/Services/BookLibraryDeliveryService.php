<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use App\Models\LibraryBook;
use App\Models\LibraryUserBook;
use Telegram;

interface BookLibraryDeliveryService
{
    public function deliverBook(
        Telegram $mainBot,
        BotUsers $botUser,
        LibraryBook $book,
        int $mainBotId,
        string $origin,
        bool $isRandom,
        bool $revealTitleFirst
    ): ?LibraryUserBook;

    public function sendQuickAction(
        Telegram $mainBot,
        BotUsers $botUser,
        LibraryUserBook $userBook,
        int $mainBotId,
        string $origin,
        string $action
    ): bool;
}
