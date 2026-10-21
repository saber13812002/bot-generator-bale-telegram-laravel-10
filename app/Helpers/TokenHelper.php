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
            // توکن بله می‌تواند 7-10 رقم قبل از : و 35-45 کاراکتر بعد از : داشته باشد
            // فرمت انعطاف‌پذیر برای پشتیبانی از انواع توکن‌های بله
            $check = preg_match("/^[0-9]{7,10}:[a-zA-Z0-9_-]{30,50}$/", $text);
        } elseif ($type == 'soroush') {
            // توکن سoroush پلاس طبق مستندات رسمی، دقیقاً هم‌فرمت تلگرام است:
            // 7-10 رقم + ":" + حداقل 30 کاراکتر
            $check = preg_match("/^[0-9]{7,10}:[a-zA-Z0-9_-]{30,}/", $text);
        } elseif ($type == 'rubika') {
            // فرمت توکن ربات روبیکا در مستندات رسمی اعلام نشده (opaque)؛
            // فقط بررسی سنجشی (sanity) کاراکترهای مجاز انجام می‌شود.
            $check = preg_match("/^[a-zA-Z0-9_-]{8,200}$/", $text);
        } else {
            // توکن تلگرام: 7-10 رقم و حداقل 30 کاراکتر
            $check = preg_match("/^[0-9]{7,10}:[a-zA-Z0-9_-]{30,}/", $text);
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

    /**
     * ماسک‌کردن توکن‌های ربات در متن خطا پیش از ثبت در لاگ.
     *
     * توکن‌ها هرگز نباید در لاگ ثبت شوند؛ حتی وقتی در URL یک خطای API
     * (مثلاً Guzzle/Telegram client) وجود دارند.
     *
     * نمونه: "https://tapi.bale.ai/bot123:SECRET/sendPhoto failed"
     *        → "https://tapi.bale.ai/bot[REDACTED]/sendPhoto failed"
     */
    public static function redactTokens(string $message): string
    {
        // 0) روبیکا: توکن داخل URL به شکل botapi.rubika.ir/v3/<token>/<method>
        //    این الگو حتماً باید قبل از الگوی 1 اجرا شود، زیرا الگوی 1 با «bot»ِ
        //    دامنه botapi.rubika.ir مطابقت می‌دهد و به‌جای توکن، دامنه را ماسک
        //    می‌کند و توکن واقعی را بیرون می‌گذارد.
        $message = preg_replace(
            '#(botapi\.rubika\.ir/v3/)([^\s/?&"\'\\#]{8,200})(?=/|\s|$)#',
            '$1[REDACTED]',
            $message
        );

        // 1) توکن داخل URL: bot<token>/ یا /api/<token>/
        $message = preg_replace(
            '#(bot|/api/)([a-zA-Z0-9:_.-]{10,128})(?=/|\?|&|"$|\s)#',
            '$1[REDACTED]',
            $message
        );

        // 2) توکن مستقل به شکل digits:long-secret (بله/تلگرام)
        $message = preg_replace(
            '#\b\d{7,10}:[a-zA-Z0-9_-]{20,}\b#',
            '[REDACTED]',
            $message
        );

        return $message;
    }
}
