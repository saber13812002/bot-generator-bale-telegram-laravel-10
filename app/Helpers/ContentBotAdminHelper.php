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

        if ($origin === 'bale' && (string) $bot->bale_owner_chat_id === (string) $chatId) {
            return true;
        }

        if ($origin !== 'bale' && (string) $bot->telegram_owner_chat_id === (string) $chatId) {
            return true;
        }

        // سازنده‌ی ربات از طریق پنل وب (bots.pardisania.ir):
        // مالکیت از bot_owner_id → BotOwner.bale_chat_id (لگین با شماره/OTP) تشخیص داده می‌شود.
        $owner = $bot->botOwner;
        if ($owner && $owner->bale_chat_id && (string) $owner->bale_chat_id === (string) $chatId) {
            return true;
        }

        return false;
    }
}
