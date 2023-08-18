<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class TokenHelper
{

    public static function isToken(mixed $text, $type): bool
    {
        $text = str_replace(' ', '', $text); // remove spaces
        $text = str_replace("\t", '', $text); // remove tabs
        $text = str_replace("\n", '', $text); // remove new lines
        $text = str_replace("\r", '', $text);

        if ($type == 'bale') {
            $check = preg_match("/^[0-9]{8,10}:[a-zA-Z0-9_-]{40}$/", $text);
        } else {
            $check = preg_match("/^[0-9]{8,10}:[a-zA-Z0-9_-]{35}/", $text);
        }
//        dd($check);
        if ($check) {
            return true;
        }
        return false;
    }


    /**
     * @param string $type
     * @return mixed
     */
    public
    static function getMotherBotToken(string $type = 'telegram'): mixed
    {
        $bot_token = env('BOT_MOTHER_TOKEN' . ($type == 'telegram' ? '_TELEGRAM' : '_BALE'));
        if ($bot_token == null) {
            Log::info("master botmother token is not set for:" . ($type == 'telegram' ? '_TELEGRAM' : '_BALE'));
        }
        return $bot_token;
    }
}
