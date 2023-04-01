<?php

namespace App\Helpers;

class BotHelper
{
    public static function switchCase(\Telegram $messenger): void
    {
        switch ($messenger->Text()) {
            case '/start':
            case 'ساختن':
                self::start($messenger);
                break;
            case '/new_bot':
            case 'جدید':
                self::newBot($messenger);
                break;
            default:
                $message = 'دستور شما منجر به هیچ کاری نشد';
                self::sendMessage($messenger, $message);
                break;
        }


    }

    private static function newBot(\Telegram $messenger): void
    {
        $message = 'روبات شما ساخته شد';
        self::sendMessage($messenger, $message);
    }

    private static function start(\Telegram $messenger): void
    {
        $message = 'برای ساخت روبات /new_bot را بفرستید';
        self::sendMessage($messenger, $message);
    }

    /**
     * @param \Telegram $messenger
     * @param string $message
     * @param $keyboard
     * @return void
     */
    public static function sendKeyboardMessage(\Telegram $messenger, string $message, $keyboard): void
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
     * @param \Telegram $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessage(\Telegram $messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message
        ];

        $messenger->sendMessage($content);
    }

    private static function sendStartMessage(\Telegram $messenger, string $message): void
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
}
