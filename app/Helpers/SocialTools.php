<?php

namespace App\Helpers;

class SocialTools
{
    // TODO amniat saber todosaber
    public static function googleKon($q)
    {
        $telegram_virgooleita_channel_id = "-1001818066671";

        $eitaa_bot_token = env('BOT_EITAA_TOKEN_SABER');
        $bale_bot_token = env('BOT_BALE_TOKEN_GOOGLEKON');
        $telegram_bot_token = env('BOT_TELEGRAM_TOKEN_GOOGLEKON');

        $telegram_virgooleitaa_bot_token = env('TELEGRAM_VIRGOOLEITAA_BOT_TOKEN');
        $bale_virgooleitaa_bot_token = env('BALE_VIRGOOLEITAA_BOT_TOKEN');

        $chat_id = 8598940;
        $caption = 'یک جستجوی پیشنهادی که روی اون کلیک کنید';
        $title = 'جستجو: ' . $q;
        $q = preg_replace('/\s+/', '+', $q);
        $link = "https://www.google.com/search?q=" . $q;


        $trends = "https://trends.google.com/trends/explore?date=today%205-y&q=" . $q . "&hl=fa";

        $text = $title . "
        " . $caption . "
        " . $link . "

        اثر جستجوی شما در این نمودار مشخص میشه.

        " . $trends . "

    پس تا جایی که میتونید این پست رو به اشتراک بگذارید

    جستجوهاتون رو برای ادمین کانال زیر بفرستید
    eitaa.com/googlekon
    ble.ir/googlekon
    t.me/google_kon

        ";


        self::callEitaaApi($eitaa_bot_token, $chat_id, $title, $text);
        self::call_bale_api($bale_bot_token, "@googlekon", $text);
        self::call_telegram_api($telegram_bot_token, "-1001980670257", $text);

        echo "token ok: q:" . $q;
    }


    private static function callEitaaApi($bot_token, $chat_id, $title, $text): void
    {
        // initialise the curl request
        $request = curl_init('https://eitaayar.ir/api/' . $bot_token . '/sendMessage');
        // send a file
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $request,
            CURLOPT_POSTFIELDS,
            array(
                // 'file' => new \CurlFile(realpath('C:/Users/eitaa/Desktop/eitaa.apk')),
                'chat_id' => $chat_id,
                'title' => $title,
                'pin' => 1,
                'text' => $text,
                'date' => time() + 1,
                // send next 30 second
            )
        );

        // output the response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($request);

        // close the session
        curl_close($request);
    }


    private static function call_bale_api($bot_token, $chat_id, $text): void
    {
        // initialise the curl request
        $request = curl_init('https://tapi.bale.ai/bot' . $bot_token . '/sendMessage');
        // send a file
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $request,
            CURLOPT_POSTFIELDS,
            array(
                'chat_id' => $chat_id,
                'text' => $text
            )
        );

        // output the response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($request);

        // close the session
        curl_close($request);
    }

    private static function call_telegram_api($bot_token, $chat_id, $text): void
    {
        // initialise the curl request
        $request = curl_init('https://api.telegram.org/bot' . $bot_token . '/sendMessage');
        // send a file
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $request,
            CURLOPT_POSTFIELDS,
            array(
                'chat_id' => $chat_id,
                'text' => $text
            )
        );

        // output the response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($request);

        // close the session
        curl_close($request);
    }

    public static function virgool(\App\Http\Requests\StoreSocialPublishRequest $request)
    {
// todo use this instead of google kon in extension
        $eitaa_bot_token = env('BOT_EITAA_TOKEN_SABER');

        $telegram_virgooleita_channel_id = "-1001818066671";


        $telegram_virgooleitaa_bot_token = env('TELEGRAM_VIRGOOLEITAA_BOT_TOKEN');
        $bale_virgooleitaa_bot_token = env('BALE_VIRGOOLEITAA_BOT_TOKEN');

        $chat_id = "8419225";
        if (isset($_REQUEST['url']))
            $url = $_REQUEST['url'];
        if ($url) {

            $blog_owner = "";
            if (isset($_REQUEST['blog_owner']))
                $blog_owner = $_REQUEST['blog_owner'];

            $text = "یک پست جدید ویرگولی :
                https://vrgl.ir/" . $url . "
                از:
"
                . $blog_owner . "
                به ما بپیوندید:
"
                . "
تلگرام
t.me/virgooleitaa
ایتا
https://eitaa.com/joinchat/2749366559C49604357e0
بله
ble.ir/virgooleitaa";

            $title = $text;

            self::callEitaaApi($eitaa_bot_token, $chat_id, $title, $text);
            self::call_bale_api($bale_virgooleitaa_bot_token, "@googlekon", $text);
            self::call_telegram_api($telegram_virgooleitaa_bot_token, $telegram_virgooleita_channel_id, $text);
        }
    }
}
