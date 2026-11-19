<?php

namespace App\Helpers;

use App\Models\Bot;

class ContentBotAdminHelper
{
    public static function isBotOwner(Bot $bot, string $chatId, string $origin): bool
    {
        if (AdminHelper::isAdmin($chatId)) {
            return true;
        }

        if ($origin === 'bale') {
            return (string) $bot->bale_owner_chat_id === (string) $chatId;
        }

        return (string) $bot->telegram_owner_chat_id === (string) $chatId;
    }
}
