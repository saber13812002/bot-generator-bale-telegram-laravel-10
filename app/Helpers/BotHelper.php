<?php

namespace App\Helpers;

use App\Models\Bot;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use PHPUnit\Exception;
use Telegram;

class BotHelper
{
    /**
     * @throws \Exception
     */
    public static function switchCase(Telegram $messenger, $type): void
    {
        $text = $messenger->Text();
        if ($text == '/start' || $text == 'ساختن') {
            self::start($messenger);
        } else if (TokenHelper::isToken($text, $type)) {
            self::newBot($messenger, $type);
        } else {
            $message = 'دستور شما منجر به هیچ کاری نشد';
            self::sendMessage($messenger, $message);
            self::start($messenger);
        }
    }

    /**
     * @throws \Exception
     */
    private static function newBot(Telegram $messenger, $type): void
    {
        $text = $messenger->Text();
        $isToken = TokenHelper::isToken($text, $type);
        if ($isToken) {
            $botItem = new Bot();
            $token = $text;
            $botItem = ($type == 'bale' ? self::callBale($token, $messenger, $botItem) : self::callTelegram($text, $messenger, $botItem));
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
     * @param mixed $token
     * @param Telegram $messenger
     * @param Bot $botItem
     * @return Bot
     * @throws \Exception
     */
    public static function callBale(mixed $token, Telegram $messenger, Bot $botItem): Bot
    {
        try {
            $newBotBale = new Telegram($token, 'bale');
            $getMeBale = ($newBotBale->getMe());
            if ($getMeBale['ok']) {
                $botItem = self::defineCreateBot($messenger, $getMeBale, 'bale');
                $webHookUrl = self::createWebhookUrl($botItem);
                $result = $newBotBale->setWebhook($webHookUrl);
                if (!$result['ok']) {
                    $message = 'وب هوک ست نشد. با ادمین تماس بگیرید @sabertaba';
                    self::sendMessage($messenger, $message);
                } else {
                    //telegram_webhook_is_set
                    $botItem->telegram_webhook_is_set = 1;
                    $botItem->save();
                    if (config('app.env') == 'local') {
                        $message = 'وب هوک :' . $webHookUrl;
                        self::sendMessage($messenger, $message);
                    }
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
        $message = 'روبات شما @' . ($botItem->bale_bot_name != "" ?? $botItem->telegram_bot_name) . ' ساخته شد';
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
     * @param Bot $botItem
     * @return string
     */
    public static function createWebhookUrl(Bot $botItem): string
    {
        return config('bot.balewebhookurl') . '?bot_user_name=' . $botItem->bale_bot_name . '&bot_token=' . $botItem->bale_bot_token;
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

    /**
     * @param Telegram $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessageAye(Telegram $messenger, string $message, $next, $back): void
    {
        $option = array(
            //First row
            array($messenger->buildInlineKeyBoardButton("بعدی", callback_data: $next)),
            array($messenger->buildInlineKeyBoardButton("قبلی", callback_data: $back))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);
    }

    /**
     * @param Telegram $messenger
     * @param $chat_id
     * @param string $message
     * @return void
     */
    public static function sendMessageByChatId(Telegram $messenger, $chat_id, string $message): void
    {
        $content = [
            'chat_id' => $chat_id,
            'text' => $message
        ];

        $messenger->sendMessage($content);
    }


    /**
     * @param Telegram $bot
     * @param string $message
     * @return void
     */
    public static function sendMessageToBotAdmin(Telegram $bot, string $message): void
    {
        BotHelper::sendMessageByChatId($bot, env('SUPER_ADMIN_CHAT_ID_BALE'), $message);
    }


    /**
     * @param string $message
     * @param $type
     * @return void
     */
    public static function sendMessageToSuperAdmin(string $message, $type): void
    {
        $bot = new Telegram($type == 'bale' ? env('BOT_MOTHER_TOKEN_BALE') : env('BOT_MOTHER_TOKEN_TELEGRAM'), $type);
        BotHelper::sendMessageByChatId($bot, $type == 'bale' ? env('SUPER_ADMIN_CHAT_ID_BALE') : env('SUPER_ADMIN_CHAT_ID_TELEGRAM'), $message);
    }

    public static function sendTelegram4InlineMessage(Telegram $messenger, string $message, $array, $isInlineKeyBoard): void
    {

        if (!$isInlineKeyBoard) {
            $option = array(
//            First row
                array($messenger->buildKeyboardButton($array[0][1]), $messenger->buildKeyboardButton($array[1][1])),
                //Second row
                array($messenger->buildKeyboardButton($array[2][1]), $messenger->buildKeyboardButton($array[3][1])),
                //Third row
//            array($messenger->buildKeyboardButton("Button 6"))
            );
            $keyboard = $messenger->buildKeyBoard($option, $onetime = false);
            self::sendKeyboardMessage($messenger, $message, $keyboard);
        } else {
            $option = array(
                //First row
                array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]), $messenger->buildInlineKeyBoardButton($array[1][0], callback_data: $array[1][1])),
                //Second row
                array($messenger->buildInlineKeyBoardButton($array[2][0], callback_data: $array[2][1]), $messenger->buildInlineKeyBoardButton($array[3][0], callback_data: $array[3][1])),
                //Third row
//                        array($bot->buildInlineKeyBoardButton("Button 6", $url = "http://link6.com")))
            );
            $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
            self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);
        }
    }

    public static function sendTelegram2InlineMessage(Telegram $messenger, string $message, $array, $isInlineKeyBoard): void
    {
        if (!$isInlineKeyBoard) {
            $option = array(
                array($messenger->buildKeyboardButton($array[0][1]), $messenger->buildKeyboardButton($array[1][1])),
            );
            $keyboard = $messenger->buildKeyBoard($option, $onetime = false);
            self::sendKeyboardMessage($messenger, $message, $keyboard);
        } else {
            $option = array(
                array(
                    $messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]),
                    $messenger->buildInlineKeyBoardButton($array[1][0], callback_data: $array[1][1]),
                ),
            );
            $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
            self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);
        }
    }

    public static function sendTelegram6InlineMessage(Telegram $messenger, string $message, $array, $isInlineKeyBoard): void
    {
        if (!$isInlineKeyBoard) {
            $option = array(
                array($messenger->buildKeyboardButton($array[0][1]), $messenger->buildKeyboardButton($array[1][1])),
                array($messenger->buildKeyboardButton($array[2][1]), $messenger->buildKeyboardButton($array[3][1])),
                array($messenger->buildKeyboardButton($array[4][1]), $messenger->buildKeyboardButton($array[5][1])),
            );
            $keyboard = $messenger->buildKeyBoard($option, $onetime = false);
            self::sendKeyboardMessage($messenger, $message, $keyboard);
        } else {
            $option = array(
                array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]), $messenger->buildInlineKeyBoardButton($array[1][0], callback_data: $array[1][1])),

                array($messenger->buildInlineKeyBoardButton($array[2][0], callback_data: $array[2][1]), $messenger->buildInlineKeyBoardButton($array[3][0], callback_data: $array[3][1])),

                array($messenger->buildInlineKeyBoardButton($array[4][0], callback_data: $array[4][1]), $messenger->buildInlineKeyBoardButton($array[5][0], callback_data: $array[5][1])),
            );
            $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
            self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);
        }
    }

    public static function sendStart(Telegram $messenger, string $message): void
    {
        $option = array(
            //First row
            array($messenger->buildInlineKeyBoardButton("بازگشت به فهرست", callback_data: "/start"))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);

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
        }
        return $botItem;
    }


    /**
     * @param Telegram $bot
     * @param string $message
     * @param $type
     * @return void
     */
    public static function sendMessageToUserAndAdmin(Telegram $bot, string $message, $type): void
    {
        BotHelper::sendMessage($bot, $message);
        BotHelper::sendMessageToSuperAdmin($message . StringHelper::insertTextForAdmin($bot, $type), 'bale');
        BotHelper::sendMessageToSuperAdmin($message . StringHelper::insertTextForAdmin($bot, $type), 'telegram');
    }

    public static function makeKeyboard2button($text1, $cd1, $text2, $cd2): array
    {
        return [
            [
                [
                    "text" => $text1,
                    "callback_data" => $cd1
                ],
                [
                    "text" => $text2,
                    "callback_data" => $cd2
                ]
            ]
        ];
    }

    public static function makeBaleKeyboard4button($array): array
    {
        return [
            [
                [
                    "text" => $array[0][0],
                    "callback_data" => $array[0][1]
                ],
                [
                    "text" => $array[1][0],
                    "callback_data" => $array[1][1]
                ]
            ],
            [
                [
                    "text" => $array[2][0],
                    "callback_data" => $array[2][1]
                ],
                [
                    "text" => $array[3][0],
                    "callback_data" => $array[3][1]
                ]
            ]
        ];
    }

    public static function makeKeyboard6button($array): array
    {
        $arr = [];
        for ($j = 0, $i = 0; $j < count($array); $j++) {
            $arr[$i++] =
                [
                    [
                        "text" => $array[$j][0],
                        "callback_data" => $array[$j][1]
                    ],
                    [
                        "text" => $array[++$j][0],
                        "callback_data" => $array[$j][1]
                    ]
                ];
        }
        return $arr;
    }

    /**
     * @param $botToken
     * @param $chatId
     * @param string $message
     * @param $inlineKeyboard
     * @return void
     * @throws GuzzleException
     */
    public static function messageWithKeyboard($botToken, $chatId, string $message, $inlineKeyboard): void
    {
        $client = new GuzzleHttp\Client();
        $uri = 'https://tapi.bale.ai/bot' . $botToken . '/sendMessage';
        $response = $client->post($uri, ['json' => [
            "chat_id" => $chatId,
            "text" => $message,
            "reply_markup" => [
                "inline_keyboard" => $inlineKeyboard
            ]]]);
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
    }
}
