<?php

namespace App\Helpers;

/**
 * هلپر جمع‌آوری چت‌آیدی کانال/گروه برای چند پلتفرم (بله، تلگرام، ایتا).
 * وقتی کاربر از ربات‌مادرِ یک پلتفرم استفاده می‌کند، برای همان پلتفرم فوروارد و برای بقیه «چت‌آیدی را بفرست».
 * قابل استفاده در هر ویزاردی که به چند کانال/گروه نیاز دارد (مثلاً ربات ادمین کانال روزانه).
 */
class MultiPlatformChannelWizardHelper
{
    public const PLATFORM_BALE = 'bale';
    public const PLATFORM_TELEGRAM = 'telegram';

    /**
     * متن درخواست برای کانال/گروه بله.
     * @param string $currentPlatform 'bale' | 'telegram' (پلتفرمی که الان کاربر در آن است)
     */
    public static function getBalePrompt(string $currentPlatform): string
    {
        if ($currentPlatform === self::PLATFORM_BALE) {
            return "اگر کانال بله داری، یک پیام از آن کانال فوروارد کن. اگر نداری یا نمی‌خواهی، «رد» بفرست.";
        }
        return "الان از ربات مادر تلگرام استفاده می‌کنی؛ نمی‌شود از بله فوروارد کرد.\n\n"
            . "از بله با یک ربات (مثلاً رباتی که چت‌آیدی می‌دهد)، چت‌آیدی کانال یا گروه یا چت خصوصی بله را بگیر و اینجا همان عدد را بفرست.\n"
            . "اگر کانال/گروه بله نداری یا نمی‌خواهی، «رد» بفرست.";
    }

    /**
     * متن درخواست برای کانال/گروه تلگرام.
     * @param string $currentPlatform 'bale' | 'telegram'
     */
    public static function getTelegramPrompt(string $currentPlatform): string
    {
        if ($currentPlatform === self::PLATFORM_TELEGRAM) {
            return "اگر کانال تلگرام داری، یک پیام از آن کانال فوروارد کن. اگر نداری یا نمی‌خواهی، «رد» بفرست.";
        }
        return "الان از ربات مادر بله استفاده می‌کنی؛ نمی‌شود از تلگرام فوروارد کرد.\n\n"
            . "از تلگرام با یک ربات (مثلاً @userinfobot یا رباتی که چت‌آیدی می‌دهد)، چت‌آیدی کانال یا گروه یا چت خصوصی تلگرام را بگیر و اینجا همان عدد را بفرست.\n"
            . "اگر کانال/گروه تلگرام نداری یا نمی‌خواهی، «رد» بفرست.";
    }

    /**
     * متن درخواست برای شناسه ایتا (یکسان برای هر پلتفرم).
     */
    public static function getEitaaPrompt(): string
    {
        return "شناسه ارسال به کانال یا گروه ایتا را وارد کن (یک عدد یا رشته). اگر نمی‌خواهی ایتا را اضافه کنی، «رد» بفرست.";
    }

    /**
     * استخراج یا اعتبارسنجی چت‌آیدی بله از ورودی کاربر.
     * @return int|null چت‌آیدی یا null اگر رد/نامعتبر
     */
    public static function parseBaleInput(string $currentPlatform, string $text, array $update): ?int
    {
        $t = mb_strtolower(trim($text));
        if ($t === 'رد' || $t === 'skip') {
            return null;
        }
        if ($currentPlatform === self::PLATFORM_BALE) {
            $forwardFromChat = $update['message']['forward_from_chat'] ?? null;
            if ($forwardFromChat && isset($forwardFromChat['id'])) {
                return (int) $forwardFromChat['id'];
            }
            return null;
        }
        if (preg_match('/^-?\d+$/', trim($text))) {
            return (int) trim($text);
        }
        return null;
    }

    /**
     * آیا ورودی برای چت‌آیدی بله وقتی پلتفرم فعلی تلگرام است نامعتبر است (باید خطا نشان دهیم).
     */
    public static function isBaleInputInvalidWhenNotForward(string $currentPlatform, string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === 'رد' || $t === 'skip') {
            return false;
        }
        if ($currentPlatform !== self::PLATFORM_TELEGRAM) {
            return false;
        }
        return !preg_match('/^-?\d+$/', trim($text));
    }

    /**
     * استخراج یا اعتبارسنجی چت‌آیدی تلگرام از ورودی کاربر.
     * @return int|null چت‌آیدی یا null اگر رد/نامعتبر
     */
    public static function parseTelegramInput(string $currentPlatform, string $text, array $update): ?int
    {
        $t = mb_strtolower(trim($text));
        if ($t === 'رد' || $t === 'skip') {
            return null;
        }
        if ($currentPlatform === self::PLATFORM_TELEGRAM) {
            $forwardFromChat = $update['message']['forward_from_chat'] ?? null;
            if ($forwardFromChat && isset($forwardFromChat['id'])) {
                return (int) $forwardFromChat['id'];
            }
            return null;
        }
        if (preg_match('/^-?\d+$/', trim($text))) {
            return (int) trim($text);
        }
        return null;
    }

    /**
     * آیا ورودی برای چت‌آیدی تلگرام وقتی پلتفرم فعلی بله است نامعتبر است.
     */
    public static function isTelegramInputInvalidWhenNotForward(string $currentPlatform, string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === 'رد' || $t === 'skip') {
            return false;
        }
        if ($currentPlatform !== self::PLATFORM_BALE) {
            return false;
        }
        return !preg_match('/^-?\d+$/', trim($text));
    }
}
