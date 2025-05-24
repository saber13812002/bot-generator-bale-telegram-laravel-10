<?php

namespace App\Helpers;

use App\Models\Projects;
use Exception;
use Illuminate\Support\Facades\Log;
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
            self::handleStartRequestVoice($messenger);
        } else if (self::isProject($text, $type)) {
            self::registerInProject($messenger, $type, $language, $botMotherId);
        } else {
            $message = trans("bot.this command not recognized");
            BotHelper::sendMessage($messenger, $message);
            self::handleStartRequestVoice($messenger);
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

    private static function isProject(mixed $text, $type): bool
    {
        try {
            $projectItem = Projects::query()
                ->whereSlug($text)
                ->whereStatus('active')
                ->firstOrFail();
            if ($projectItem->count() == 1) {
                return true;
            }
        } catch (Exception $e) {
            BotHelper::sendMessageToSuperAdmin(trans("bot.An error occurred when admin want to approve your request"), $type);
            Log::error($e->getMessage());
//                throw $e;
        }
        return false;
    }


}
