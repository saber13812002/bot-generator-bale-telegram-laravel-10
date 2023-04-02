<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class TokenHelper
{

    public static function isToken(mixed $text): bool
    {
        $check = preg_match("/^[0-9]{8,10}:[a-zA-Z0-9_-]{40}$/", $text);
//        dd($check);
        if ($check) {
            return true;
        }
        return false;
    }


    /**
     * @return mixed
     */
    public static function getMotherBotToken(): mixed
    {
        $bot_token = env('BOT_MOTHER_TOKEN');
        if ($bot_token == null) {
            Log::info("master botmother token is not set");
        }
        return $bot_token;
    }
}
