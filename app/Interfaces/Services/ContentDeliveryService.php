<?php

namespace App\Interfaces\Services;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentItem;
use Telegram;

interface ContentDeliveryService
{
    public function deliverNextInCategory(
        Telegram $bot,
        BotUsers $botUser,
        ContentItem $item,
        int $botId,
        string $origin
    ): bool;
}
