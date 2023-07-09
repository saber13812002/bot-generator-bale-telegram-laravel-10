<?php

namespace App\Helpers;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\QuranAyat;
use Exception;
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
    public static function switchCase(Telegram $messenger, $type, $language, $botMotherId): void
    {
        $text = $messenger->Text();
        if ($text == '/start' || $text == 'ساختن') {
            self::start($messenger);
        } else if (TokenHelper::isToken($text, $type)) {
            self::newBot($messenger, $type, $language, $botMotherId);
        }
//        else if ($language != 'fa') {
//            self::setBotLanguage($messenger, $type, $language);
//        }
        else {
            $message = trans("bot.this command not recognized");
            self::sendMessage($messenger, $message);
            self::start($messenger);
        }
    }

    /**
     * @throws Exception
     */
    private static function newBot(Telegram $messenger, $type, $language, $botMotherId): void
    {
        $text = $messenger->Text();
        $isToken = TokenHelper::isToken($text, $type);
        if ($isToken) {
            $botItem = new Bot();
            $token = $text;
            $botItem = self::defineBotInDbThenSetWebHook($token, $messenger, $botItem, $type, $language, $botMotherId);
            $message = self::properMessage($botItem, $messenger, $type);
        } else {
            $message = trans("bot.this is not correct bot token");
        }
        self::sendMessage($messenger, $message);
    }

    public static function setBotLanguage($messenger, $type, $language, $botMotherId): void
    {
//        $botItem = ($type == 'bale' ? self::callBale($token, $messenger, $botItem, $language, $botMotherId) : self::callTelegram($text, $messenger, $botItem, $language, $botMotherId));
//        $message = self::properMessage($botItem, $messenger, $type);
    }

    private static function start(Telegram $messenger): void
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
    public static function properMessage(Bot $botItem, Telegram $messenger, $type): string
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
    public static function createWebhookUrl(Bot $botItem, $type, $language, $botMotherId): string
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
    public static function sendMessageParseMode(Telegram $messenger, string $message): void
    {
        $chat_id = $messenger->ChatID();

        $content = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => "HTML"
        ];

        $messenger->sendMessage($content);
    }

    /**
     * @param Telegram $messenger
     * @param $suraId
     * @param $ayaId
     * @param null $userSettings
     * @return void
     */
    public static function sendAudio(Telegram $messenger, $suraId, $ayaId, BotUsers $userSettings = null): void
    {
        // TODO: cache
        //
        $aye = QuranAyat::query()
            ->whereSura($suraId)
            ->whereAya($ayaId)
            ->first();
//        dd($aye->id);

        $chat_id = $messenger->ChatID();

        // https://qurano.com/en/1-al-fatiha/
        // https://static.qurano.com/dist/audio/001002.mp3

        // https://quranwbw.com/1
        // https://words.audios.quranwbw.com/1/001_001_001.mp3
        // https://words.audios.quranwbw.com/1/001_007_009.mp3

        // https://quran.com/1
        // https://audio.qurancdn.com/wbw/001_002_004.mp3
        // https://quran.com/3:71/tafsirs/en-tafisr-ibn-kathir

        // http://audio.recitequran.com/wbw/arabic/wisam_sharieff/

        // https://cors-proxy.elfsight.com/
        // http://wbwcradio.bw.edu:8000/

        // http://verses.quran.com/wbw/

        // https://server7.mp3quran.net/download/basit/Almusshaf-Al-Mojawwad/001.mp3
        // https://quranwbw.github.io/audio-words-new/001_002_001.mp3
        // https://quranwbw.github.io/audio-ayah-english/001_002_001.mp3
        // https://quranwbw.github.io/audio-ayah-arabic
        // https://github.com/marwan/quranwbw.com/blob/9f916b35f591f854c53ef0c8922fe3fcc18efa91/assets/js/main.js#L25

        // http://www.houseofquran.com/qsys/quranteacher1.html
        // http://3cba.houseofquran.com/01/1F_1_2.mp3
        // http://3cba.houseofquran.com/01/1S_2_3.mp3
        // http://3cba.houseofquran.com/01/1S_2_4.mp3

        // ar.abdulazizazzahrani
        // ar.abdulbariaththubaity
        // ar.abdulbarimohammed
        // ar.abdulbasitmujawwad
        // ar.abdulbasitmurattal
        // ar.abdulkareemalhazmi
        // ar.abdullahalmatrood
        // ar.abdullahawadaljuhani
        // ar.abdullahbasfar

//        $userSettings = BotUsers::first($chat_id, $bot_id);
        $base_url = "https://cdn.islamic.network/quran/audio/128/ar.alafasy/";
        $mp3Enable = "false";

//        dd($userSettings);
        if ($userSettings != null) {
            $mp3Reciter = $userSettings->setting('mp3_base_url') == "parhizgar" ? "parhizgar" : "alafasy";
            $mp3Enable = $userSettings->setting('mp3_enable') == "true" ? "true" : "false";

            if ($mp3Reciter == "parhizgar")
                $base_url = "http://cdn.alquran.cloud/media/audio/ayah/ar.parhizgar/";

            //https://github.com/GlobalQuran/docs/blob/a0543eb602bab509c366b02a571a4f480a7214ec/api.yaml#L1613

            // http://cdn.alquran.cloud/media/audio/ayah/fa.hedayatfarfooladvand/
            // http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/
            // \/\/audio.globalquran.com\/fa.hedayatfarfooladvand\/mp3\/40kbs\/
            // \/\/audio.globalquran.com\/ar.parhizgar\/mp3\/48kbs\/
            // \/\/audio.globalquran.com\/ur.khan\/mp3\/64kbs\/
        }
        $caption = "";
        $caption = self::getSettingReciter();

        $audio = $base_url . $aye->id . ".mp3";

        $content = [
            'chat_id' => $chat_id,
            'audio' => $audio,
            // TODO:
//            'duration' => NULL,
//            'performer' => NULL,
//            'title' => NULL,
            'caption' => $caption,
//            'disable_notification' => FALSE,
//            'reply_to_message_id' => NULL,
//            'reply_markup' => NULL,
//            'parse_mode' => NULL
        ];

//        dd($mp3Enable, $caption, $audio, $mp3Reciter);
        if ($mp3Enable != "true")
            $messenger->sendAudio($content);

    }

    /**
     * @param Telegram $messenger
     * @param string $message
     * @param $next
     * @param $back
     * @return void
     */
    public static function sendMessageAye(Telegram $messenger, string $message, $next, $back): void
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

    public static function sendStart(Telegram $messenger, $array): void
    {
        $option = array(
            array($messenger->buildInlineKeyBoardButton($array[0][0], callback_data: $array[0][1]))
        );
        $inlineKeyboard = $messenger->buildInlineKeyBoard($option);
        self::sendKeyboardMessage($messenger, $array[0][0], $inlineKeyboard);

    }

    public static function sendQuranSearchResult(Telegram $messenger, $message, $array): void
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
        }
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
            ],
            [
                [
                    "text" => trans("bot.disable enable reciter"),
                    "callback_data" => "/commandmp3"
                ],
                [
                    "text" => trans("bot.change reciter"),
                    "callback_data" => "/commandmp3_reciter"
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

    public static function makeBaleKeyboard1button($array): array
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

    public static function isAdminCommand(mixed $Text): bool
    {
        if (Str::start($Text, '///'))
            return true;
        return false;
    }

    public static function isAdmin(mixed $ChatID): bool
    {
        return true;
    }

    public static function getMessageAdmin(mixed $Text): string
    {
        return Str::substr($Text, 3, -1);
    }

    /**
     * @return string
     */
    public static function getSettingReciter(): string
    {
        $caption = "
" . trans("bot.disable enable reciter") . " /commandmp3
";

        $caption .= trans("bot.change reciter") . " /commandmp3_reciter
";
        return $caption;
    }
}
