<?php

namespace App\Helpers;

class BotHelper
{
    public static function switchCase(Messengers $messenger): void
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

    private static function newBot(Messengers $messenger): void
    {
        $message = 'روبات شما ساخته شد';
        self::sendMessage($messenger, $message);
    }

    private static function start(Messengers $messenger): void
    {
        $message = 'چکار میخواهید بکنید';
        self::sendStartMessage($messenger, $message);
    }

    /**
     * @param Messengers $messenger
     * @param string $message
     * @param $keyboard
     * @return void
     */
    public static function sendKeyboardMessage(Messengers $messenger, string $message, $keyboard): void
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
     * @param Messengers $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessage(Messengers $messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message
        ];

        $messenger->sendMessage($content);
    }

    private static function sendStartMessage(Messengers $messenger, string $message): void
    {
        $option = array(
            //First row
            array($messenger->buildKeyboardButton("Button 1"), $messenger->buildKeyboardButton("Button 2")),
            //Second row
            array($messenger->buildKeyboardButton("Button 3"), $messenger->buildKeyboardButton("Button 4"), $messenger->buildKeyboardButton("Button 5")),
            //Third row
            array($messenger->buildKeyboardButton("Button 6")));
        $keyboard = $messenger->buildKeyBoard($option, $onetime = false);

        self::sendKeyboardMessage($messenger, $message, $keyboard);
    }
}
