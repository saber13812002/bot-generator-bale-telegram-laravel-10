<?php

namespace App\Helpers;

use App\Models\Bot;
use Exception;
use Gap\SDP\Api;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Telegram;

class BotHelperVoice
{

    /**
     * @throws Exception
     */
    public static function handleRequestBotVoice(Telegram $messenger, $type, $language, $botMotherId): void
    {
        $text = $messenger->Text();
        if ($text == '/start' || $text == 'ساختن') {
            self::handleRequestBotVoice($messenger);
        } else if (TokenHelper::isToken($text, $type)) {
            self::registerInProject($messenger, $type, $language, $botMotherId);
        }
        else {
            $message = trans("bot.this command not recognized");
            BotHelper::sendMessage($messenger, $message);
            self::handleRequestBotVoice($messenger);
        }
    }


    private static function handleStartRequestVoice(Telegram $messenger): void
    {
        $message = trans("bot.send your token to turn on your bot");
        self::sendMessage($messenger, $message);
    }

    private static function registerInProject(Telegram $messenger, $type, $language, $botMotherId)
    {

    }


}
