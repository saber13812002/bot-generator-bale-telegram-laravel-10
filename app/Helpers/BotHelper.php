<?php

namespace App\Helpers;

use App\Models\Bot;
use Illuminate\Support\Facades\Log;
use PHPUnit\Exception;
use Telegram;

class BotHelper
{
    public static function switchCase(Telegram $messenger): void
    {
        $text = $messenger->Text();
        if ($text == '/start' || $text == 'ساختن') {
            self::start($messenger);
        } else if (TokenHelper::isToken($text)) {
            self::newBot($messenger);
        } else {
            $message = 'دستور شما منجر به هیچ کاری نشد';
            self::sendMessage($messenger, $message);
            self::start($messenger);
        }
    }

    /**
     * @throws \Exception
     */
    private static function newBot(Telegram $messenger): void
    {
        $text = $messenger->Text();
        $isToken = TokenHelper::isToken($text);
        if ($isToken) {
            $botItem = new Bot();
            $botItem = self::callBale($text, $messenger, $botItem);
            $message = self::properMessage($botItem, $messenger);
        } else {
            $message = 'این یک توکن تلگرام یا بله نیست';
        }
        self::sendMessage($messenger, $message);
    }

    private static function start(Telegram $messenger): void
    {
        $message = 'برای ساخت روبات توکن را بفرستید';
        self::sendMessage($messenger, $message);
    }

    /**
     * @param mixed $text
     * @param Telegram $messenger
     * @param Bot $botItem
     * @return Bot
     * @throws \Exception
     */
    public static function callBale(mixed $text, Telegram $messenger, Bot $botItem): Bot
    {
        try {
            $newBotBale = new Telegram($text, 'bale');
            $getMeBale = ($newBotBale->getMe());
            if ($getMeBale['ok']) {
                $botItem = self::defineCreateBot($messenger, $getMeBale, 'bale');
                $result = $newBotBale->setWebhook(config('bot.balewebhookurl'));
                if (!$result['ok']) {
                    $message = 'وب هوک ست نشد. با ادمین تماس بگیرید @sabertaba';
                    self::sendMessage($messenger, $message);
                }
            }
        } catch (Exception $e) {
            Log::info("no bale bot");
        }
        return $botItem;
    }

    /**
     * @param mixed $text
     * @param Telegram $messenger
     * @param Bot $botItem
     * @return Bot
     * @throws \Exception
     */
    public static function callTelegram(mixed $text, Telegram $messenger, Bot $botItem): Bot
    {
        try {
            $newBotTelegram = new Telegram($text);
            $getMeTelegram = ($newBotTelegram->getMe());
            if ($getMeTelegram['ok']) {
                self::defineCreateBot($messenger, $getMeTelegram, 'telegram');
            }
        } catch (Exception $e) {
            Log::info("not telegram bot");
        }
        return $botItem;
    }

    /**
     * @param Bot $botItem
     * @param Telegram $messenger
     * @return string
     */
    public static function properMessage(Bot $botItem, Telegram $messenger): string
    {
        $message = 'روبات شما @' . ($botItem->bale_bot_name ?? $botItem->telegram_bot_name) . ' ساخته شد';
        self::sendMessage($messenger, $message);

        if ($botItem->bale_bot_name && $botItem->telegram_bot_name) {
            $message = 'روبات های شما ' . ($botItem->bale_bot_name . " و " . $botItem->telegram_bot_name) . ' ساخته شده است دوستان خود را برای استارت کردن روبات ها به کلیک روی اسم آنها با ات سایت دعوت کنید';
            self::sendMessage($messenger, $message);
        } else if ($botItem->bale_bot_name || $botItem->telegram_bot_name) {
            if ($botItem->bale_bot_name) {
                $message = 'روبات بله شما ساخته شده باید توکن روبات تلگرام را اگر لازم دارید بفرستید';
            } else {
                $message = 'روبات تلگرام شما ساخته شده باید توکن روبات بله را اگر لازم دارید بفرستید';
            }
            self::sendMessage($messenger, $message);
        }
        return $message;
    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @param $keyboard
     * @return void
     */
    public static function sendKeyboardMessage(Telegram $messenger, string $message, $keyboard): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'reply_markup' => $keyboard
        ];

        $messenger->sendMessage($content);
    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessage(Telegram $messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message
        ];

        $messenger->sendMessage($content);
    }

    private static function sendStartMessage(Telegram $messenger, string $message): void
    {
        $option = array(
            //First row
//            array($messenger->buildKeyboardButton("Button 1"), $messenger->buildKeyboardButton("Button 2")),
//            //Second row
//            array($messenger->buildKeyboardButton("Button 3"), $messenger->buildKeyboardButton("Button 4"), $messenger->buildKeyboardButton("Button 5")),
//            //Third row
            array($messenger->buildKeyboardButton("Button 6")));
        $keyboard = $messenger->buildKeyBoard($option, $onetime = false);

        self::sendKeyboardMessage($messenger, $message, $keyboard);
    }

    /**
     * @throws \Exception
     */
    private static function defineCreateBot(Telegram $messenger, $getMe, $type): Bot
    {
        $botItem = new Bot();
        if ($type == 'bale') {
            $botItem->bale_owner_chat_id = $messenger->ChatID();
            $botItem->bale_bot_name = $getMe['result']['username'];
            $botItem->bale_bot_token = $messenger->Text();
            $botItem->bale_get_me_api_response = json_encode($getMe['result']);
            $botItem->bale_bot_status = 'Active';
        } else if ($type == 'telegram') {
            $botItem->telegram_owner_chat_id = $messenger->ChatID();
            $botItem->telegram_bot_name = $getMe['result']['username'];
            $botItem->telegram_bot_token = $messenger->Text();
            $botItem->telegram_get_me_api_response = json_encode($getMe['result']);
            $botItem->telegram_bot_status = 'Active';
        }
        try {
            $botItem->save();
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')) {
                self::sendMessage($messenger, "این روبات و توکن قبلا در این سیستم استفاده شده است اگر متوجه اشکال نشدید این پیام را به همراه ساعت و تاریخ برای ادمین ما بفرستید @sabertaba");
            } else {
                self::sendMessage($messenger, $e->getMessage());
            }
            throw $e;
        }
        return $botItem;
    }
}
