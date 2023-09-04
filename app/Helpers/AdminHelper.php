<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class AdminHelper
{

    public
    static function isAdminCommand(mixed $Text): bool
    {
        if (Str::start($Text, '///'))
            return true;
        return false;
    }

    public
    static function isAdmin(mixed $chatId): bool
    {
        if (
            $chatId == env("CHAT_ID_ACCOUNT_1_SABER") ||
            $chatId == env("CHAT_ID_ACCOUNT_2_SABER") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_TELEGRAM") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_BALE") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_BALE2") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_GAP")
        )
            return true;
        return false;
    }

    public
    static function getAdmins(): bool
    {
        return [env("CHAT_ID_ACCOUNT_1_SABER"),
            env("CHAT_ID_ACCOUNT_2_SABER"),
            env("SUPER_ADMIN_CHAT_ID_TELEGRAM"),
            env("SUPER_ADMIN_CHAT_ID_BALE"),
            env("SUPER_ADMIN_CHAT_ID_BALE2"),
            env("SUPER_ADMIN_CHAT_ID_GAP")];
    }

    public
    static function getMessageAdmin(mixed $Text): string
    {
        return Str::substr($Text, 3, Str::length($Text));
    }

}
