<?php

namespace App\Helpers;

use App\Models\Bot;
use Exception;
use Gap\SDP\Api;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;

class BotHelper
{
    /**
     * @throws Exception
     */
    public static function handleRequestBotMother(Telegram $messenger, $type, $language, $botMotherId): void
    {
        $text = $messenger->Text();
        if ($text == '/start' || $text == 'ساختن') {
            self::handleStartRequest($messenger);
        } else if (TokenHelper::isToken($text, $type)) {
            self::defineNewBot($messenger, $type, $language, $botMotherId);
        }
//        else if ($language != 'fa') {
//            self::setBotLanguage($messenger, $type, $language);
//        }
        else {
            $message = trans("bot.this command not recognized");
            self::sendMessage($messenger, $message);
            self::handleStartRequest($messenger);
        }
    }

    /**
     * @throws Exception
     */
    private static function defineNewBot(Telegram $messenger, $type, $language, $botMotherId): void
    {
        $text = $messenger->Text();
        $isToken = TokenHelper::isToken($text, $type);
        if ($isToken) {
            $botItem = new Bot();
            $token = $text;
            $botItem = self::defineBotInDbThenSetWebHook($token, $messenger, $botItem, $type, $language, $botMotherId);
            $message = self::properMessageForNewBot($botItem, $messenger, $type);
        } else {
            $message = trans("bot.this is not correct bot token");
        }
        self::sendMessage($messenger, $message);
    }

//    public static function setBotLanguage($messenger, $type, $language, $botMotherId): void
//    {
//        $botItem = ($type == 'bale' ? self::callBale($token, $messenger, $botItem, $language, $botMotherId) : self::callTelegram($text, $messenger, $botItem, $language, $botMotherId));
//        $message = self::properMessage($botItem, $messenger, $type);
//    }

    private static function handleStartRequest(Telegram $messenger): void
    {
        $message = trans("bot.send your token to turn on your bot");
        self::sendMessage($messenger, $message);
    }

    /**
     * @param mixed $token
     * @param Telegram $messenger
     * @param Bot $botItem
     * @param $type
     * @param string $language
     * @param int $botMotherId
     * @return Bot
     * @throws Exception
     */
    public static function defineBotInDbThenSetWebHook(mixed $token, Telegram $messenger, Bot $botItem, $type, string $language = 'fa', int $botMotherId = 1): Bot
    {
        try {
            $newBotBale = new Telegram($token, $type);
            $getMeBale = ($newBotBale->getMe());
            if ($getMeBale['ok']) {
                $botItem = self::defineCreateBot($messenger, $getMeBale, $type, $botMotherId);
                $webHookUrl = self::createWebhookUrl($botItem, $type, $language, $botMotherId);
                $result = $newBotBale->setWebhook($webHookUrl);
                if (!$result['ok']) {
                    $message = trans("bot.i cant configure your bot") . " type:" . $type . " :" . trans("bot.please call admin by this account") . ' @sabertaba';
                    self::sendMessage($messenger, $message);
                } else {
                    if ($type == 'bale') {
                        $botItem->bale_webhook_is_set = 1;
                    } else {
                        $botItem->telegram_webhook_is_set = 1;
                    }
                    $botItem->save();
                    if (config('app.env') == 'local') {
                        $message = 'وب هوک :' . $webHookUrl;
                        self::sendMessage($messenger, $message);
                    }
                }
            }
        } catch (Exception $e) {
            Log::info("no " . $type . " bot");
        }
        return $botItem;
    }


    /**
     * @param Bot $botItem
     * @param Telegram $messenger
     * @param $type
     * @return string
     */
    private static function properMessageForNewBot(Bot $botItem, Telegram $messenger, $type): string
    {
        $message = trans("bot.your bots") . ' @' . ($type == 'bale' ? $botItem->bale_bot_name : $botItem->telegram_bot_name) . ' ' . trans("bot.created");
        self::sendMessage($messenger, $message);

        if ($botItem->bale_bot_name && $botItem->telegram_bot_name) {
            $message = trans("bot.Your bots") . ($botItem->bale_bot_name . " " . trans("bot.and") . " " . $botItem->telegram_bot_name) . trans("bot.your bot created as well.") . trans("bot.please invite your friends via your bot link or bot username");
            self::sendMessage($messenger, $message);
        } else if ($botItem->bale_bot_name || $botItem->telegram_bot_name) {
            if ($type == 'bale') {
                $message = trans("bot.Your :bot bot created as well", ['bot' => trans("bot.bale")]) . " " . trans("bot.If you need :bot bot please send another token to https://t.me/botmomsbot", ['bot' => trans("bot.telegram")]);
            } else {
                $message = trans("bot.Your :bot bot created as well", ['bot' => trans("bot.telegram")]) . " " . trans("bot.If you need :bot bot please send another token to https://ble.ir/Testchannelbot ", ['bot' => trans("bot.bale")]);
            }
            self::sendMessage($messenger, $message);
        }
        return $message;
    }

    /**
     * @param Bot $botItem
     * @param $type
     * @param $language
     * @param $botMotherId
     * @return string
     */
    private static function createWebhookUrl(Bot $botItem, $type, $language, $botMotherId): string
    {
        return config('bot.childbotwebhookurl')
            . '?bot_user_name='
            . $botItem->bale_bot_name
            . '&bot_token='
            . $botItem->bale_bot_token
            . '&origin='
            . $type
            . '&language='
            . $language
            . '&bot_mother_id='
            . $botMotherId;
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
            'reply_markup' => $keyboard,
            'parse_mode' => "html"
        ];

        $messenger->sendMessage($content);
    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @param $keyboard
     * @param $chat_id
     * @return void
     */
    public static function sendKeyboardMessageToChatId(Telegram $messenger, string $message, $keyboard, $chat_id): void
    {
        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'reply_markup' => $keyboard,
            'parse_mode' => "html"
        ];

        $messenger->sendMessage($content);
    }


    /**
     * @param $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessage($messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => "html"
        ];

        $messenger->sendMessage($content);
    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @return void
     */
    public static function sendMessageParseMode($messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => "html"
        ];

        $messenger->sendMessage($content);
    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @param $next
     * @param $back
     * @return void
     */
    public static function sendMessage2Button(Telegram $messenger, string $message, $next, $back): void
    {
        $option = array(
            //First row
            array($messenger->buildInlineKeyBoardButton(trans('bot.next'), callback_data: $next)),
            array($messenger->buildInlineKeyBoardButton(trans('bot.previous'), callback_data: $back))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);
    }

    /**
     * @param $messenger
     * @param $chat_id
     * @param string $message
     * @return void
     */
    public static function sendMessageByChatId($messenger, $chat_id, string $message): void
    {
        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => "html"
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
     * @throws Exception
     */
    public static function sendMessageToSuperAdmin(string $message, $type): void
    {
        if ($type != "gap") {
            $bot = new Telegram($type == 'bale' ? env('BOT_MOTHER_TOKEN_BALE') : env('BOT_MOTHER_TOKEN_TELEGRAM'), $type);
        } else {
            $bot = new Api(env('BOT_MOTHER_TOKEN_GAP'));
        }
        BotHelper::sendMessageByChatId($bot, $type == 'bale' ? env('SUPER_ADMIN_CHAT_ID_BALE') : ($type == 'gap' ? env('SUPER_ADMIN_CHAT_ID_GAP') : env('SUPER_ADMIN_CHAT_ID_TELEGRAM')), $message);
    }

    public static function sendTelegram4InlineMessage($messenger, string $message, $array, $isInlineKeyBoard): void
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


    public static function sendGap4InlineMessage($messenger, string $message, $array): void
    {

        $option = [
            [
                [
                    $array[0][1] => $array[0][0]
                ],
                [
                    $array[1][1] => $array[1][0]
                ]
            ],
            [
                [
                    $array[2][1] => $array[2][0]
                ],
                [
                    $array[3][1] => $array[3][0]
                ]
            ]
        ];

        $replyKeyboard = $messenger->replyKeyboard($option);

        $messenger->sendText($messenger->ChatID(), $message, $replyKeyboard);

    }

    public static function buildInlineKeyboardButton(
        $text,
        $url = '',
        $callback_data = '',
        $switch_inline_query = null,
        $switch_inline_query_current_chat = null,
        $callback_game = '',
        $pay = ''
    ): array
    {
        $replyMarkup = [
            'text' => $text,
        ];
        if ($url != '') {
            $replyMarkup['url'] = $url;
        } elseif ($callback_data != '') {
            $replyMarkup['callback_data'] = $callback_data;
        } elseif (!is_null($switch_inline_query)) {
            $replyMarkup['switch_inline_query'] = $switch_inline_query;
        } elseif (!is_null($switch_inline_query_current_chat)) {
            $replyMarkup['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
        } elseif ($callback_game != '') {
            $replyMarkup['callback_game'] = $callback_game;
        } elseif ($pay != '') {
            $replyMarkup['pay'] = $pay;
        }

        return $replyMarkup;
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

    public static function send1button(Telegram $messenger, $array): void
    {
        $option = array(
            array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $array[0][0], $inlineKeyboard);
    }

    public static function send1buttonToChatId(Telegram $messenger, $array, $chat_id): void
    {
        $option = array(
            array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessageToChatId($messenger, $array[0][0], $inlineKeyboard, $chat_id);
    }

    public static function send1buttonWithMessage(Telegram $messenger, $message, $array): void
    {
        $option = array(
            array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $message, $inlineKeyboard);

    }

    /**
     * @throws Exception
     */
    private static function defineCreateBot(Telegram $messenger, $getMe, $type, $botMotherId): Bot
    {
        $botItem = new Bot();
        $botItem->bot_mother_id = $botMotherId;
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
        }// todo:gap
        try {
            $botItem->save();
        } catch (Exception $e) {
            if (str_starts_with($e->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')) {
                self::sendMessage($messenger, trans("bot.Bot creation failed please contact us : ") . " @sabertaba ");
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
     * @throws Exception
     */
    public static function sendMessageToUserAndAdmin(Telegram $bot, string $message, $type): void
    {
        BotHelper::sendMessage($bot, $message);
        BotHelper::sendMessageToSuperAdmin($message . StringHelper::insertTextForAdmin($bot, $type), 'bale');
        BotHelper::sendMessageToSuperAdmin($message . StringHelper::insertTextForAdmin($bot, $type), 'telegram');
    }

    public static function createChatInviteLink(string $botID, string $key, string $value, $type): string
    {
        $endLink = $botID . '?start=' . $key . '?' . $value;
        return $type == 'bale' ? config('bot.base_url.bale') . $endLink : config('bot.base_url.telegram') . $endLink;
    }

    public static function getCommandRefferralWhenStart($botText, string $delimiterCharacter = '?'): array
    {
        $hashMap = array();
        if (Str::substr($botText, 0, 1) == '/') {
            $allCommand = Str::substr($botText, 1, Str::length($botText) - 1);
            $split = explode(" ", $allCommand);

            if (count($split) > 0) {
                $command = $split[0];

                if (count($split) > 1) {
                    $pairs = Str::substr($botText, Str::length($command) + 2, Str::length($botText) - 1);

                    $pairs = explode(' ', $pairs);
                    # loop through each pair
                    foreach ($pairs as $pair) {
                        # split into name and value
                        list($name, $value) = explode($delimiterCharacter, $pair, 2);

                        # if name already exists
                        if (isset($hashMap[$name])) {
                            # stick multiple values into an array
                            if (is_array($hashMap[$name])) {
                                $hashMap[$name][] = $value;
                            } else {
                                $hashMap[$name] = array($hashMap[$name], $value);
                            }
                        } # otherwise, simply stick it in a scalar
                        else {
                            $hashMap[$name] = $value;
                        }
                    }
                } else {
                    return [$command, null];
                }
            } else {
                return [null, null];
            }

            return [$command, $hashMap];

        }
        return [null, null];
    }

    public
    static function makeGapKeyboard2button($text1, $cd1, $text2, $cd2): array
    {
        return [
            [
                [
                    $cd1 => $text1
                ],
                [
                    $cd2 => $text2
                ]
            ]
        ];
    }

    public
    static function makeKeyboard2button($text1, $cd1, $text2, $cd2): array
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

    public
    static function makeBaleKeyboard4button($array, $arrayCommands): array
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
            ],
            $arrayCommands
        ];
    }

    public
    static function makeKeyboard6button($array): array
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

    public
    static function makeBaleKeyboard1button($array): array
    {
        $arr = [];
        $arr[0] =
            [
                [
                    "text" => $array[0][0],
                    "callback_data" => $array[0][1]
                ]
            ];
        return $arr;
    }

    /**
     * @param $messenger
     * @param string $message
     * @param $inlineKeyboard
     * @return void
     */
    public
    static function messageGapWithKeyboard($messenger, string $message, $inlineKeyboard): void
    {

        $replyKeyboard = $messenger->replyKeyboard($inlineKeyboard);
        $messenger->sendText($messenger->ChatID(), $message, $replyKeyboard);
    }

    /**
     * @param $botToken
     * @param $chatId
     * @param string $message
     * @param $inlineKeyboard
     * @return void
     * @throws GuzzleException
     */
    public
    static function messageWithKeyboard($botToken, $chatId, string $message, $inlineKeyboard): void
    {
        $client = new GuzzleHttp\Client();
        $uri = 'https://tapi.bale.ai/bot' . $botToken . '/sendMessage';
        $response = $client->post($uri, ['json' => [
            "chat_id" => $chatId,
            "text" => $message,
            'parse_mode' => "html",
            "reply_markup" => [
                "inline_keyboard" => $inlineKeyboard
            ]]]);
//        echo $response->getBody()->getContents();
    }


    /**
     * @param mixed $chat_id
     * @param string $photoUrl
     * @param string $title
     * @param Telegram $messenger
     * @param string $caption
     * @return mixed
     */
    public static function sendPhoto(mixed $chat_id, string $photoUrl, string $title, Telegram $messenger, string $caption = ""): mixed
    {
        $content = [
            'chat_id' => $chat_id,
            'photo' => $photoUrl,
            'title' => $title,
            'caption' => $caption
        ];

        return $messenger->sendPhoto($content);
    }
}
