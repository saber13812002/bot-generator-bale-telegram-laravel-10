<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class AdminHelper
{
    public static function isAdminCommand(mixed $Text): bool
    {
        if (Str::start($Text, '///'))
            return true;
        return false;
    }

    public static function isAdmin(mixed $chatId): bool
    {
        if (
            $chatId == env("CHAT_ID_ACCOUNT_1_SABER") ||
            $chatId == env("CHAT_ID_ACCOUNT_2_SABER") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_TELEGRAM") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_BALE") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_BALE2") ||
            $chatId == env("SUPER_ADMIN_CHAT_ID_GAP")
        )
            return true;
        return false;
    }

    public static function getAdmins(): array
    {
        return array(env("CHAT_ID_ACCOUNT_1_SABER"),
            env("CHAT_ID_ACCOUNT_2_SABER"),
            env("SUPER_ADMIN_CHAT_ID_TELEGRAM"),
            env("SUPER_ADMIN_CHAT_ID_BALE"),
            env("SUPER_ADMIN_CHAT_ID_BALE2"),
            env("SUPER_ADMIN_CHAT_ID_GAP"));
    }

    public static function getMessageAdmin(mixed $Text, $start = 3): string
    {
        return Str::substr($Text, $start, Str::length($Text));
    }

    // =============================================
    // متدهای جدید برای سیستم پیام‌رسانی ادمین
    // =============================================

    /**
     * تشخیص فرمان آمار: ///stats
     */
    public static function isStatsCommand(string $text): bool
    {
        return trim($text) === '///stats';
    }

    /**
     * تشخیص فرمان آمار یک زبان خاص: ///stats ru
     */
    public static function isStatsLanguageCommand(string $text): bool
    {
        if (!Str::startsWith($text, '///stats ')) {
            return false;
        }
        $parts = explode(' ', trim($text), 2);
        if (count($parts) < 2) {
            return false;
        }
        return true;
    }

    /**
     * تشخیص فرمان ارسال به زبان خاص: ////xx متن پیام
     * ////ru سلام => language=ru, message=سلام
     */
    public static function isBroadcastLanguageCommand(string $text): bool
    {
        if (!Str::startsWith($text, '////')) {
            return false;
        }
        // باید حتماً بعد از //// یک کد زبان 2-5 حرفی و بعد فاصله و متن باشد
        $after = substr($text, 4);
        if (empty(trim($after))) {
            return false;
        }
        $parts = explode(' ', trim($after), 2);
        if (count($parts) < 2 || empty(trim($parts[0])) || empty(trim($parts[1]))) {
            return false;
        }
        return true;
    }

    /**
     * تشخیص فرمان ارسال به همه: /////all متن پیام
     */
    public static function isBroadcastAllCommand(string $text): bool
    {
        return Str::startsWith($text, '/////all ');
    }

    /**
     * تشخیص فرمان تأیید ارسال: /confirm
     */
    public static function isConfirmCommand(string $text): bool
    {
        return trim($text) === '/confirm';
    }

    /**
     * تشخیص فرمان راهنمای ادمین: /helpadmin
     */
    public static function isHelpAdminCommand(string $text): bool
    {
        return trim($text) === '/helpadmin';
    }

    /**
     * دریافت متن راهنمای ادمین
     */
    public static function getHelpAdminText(): string
    {
        $help = "🔐 *راهنمای دستورات ادمین*\n";
        $help .= "─────────────────────\n\n";
        
        $help .= "📊 *آمار:*\n";
        $help .= "└ `///stats` ← آمار کامل همه زبان‌ها\n";
        $help .= "└ `///stats ru` ← آمار زبان خاص (مثلاً روسی)\n\n";
        
        $help .= "📢 *ارسال پیام همگانی:*\n";
        $help .= "└ `////ru متن` ← ارسال به کاربران یک زبان خاص\n";
        $help .= "└ `/////all متن` ← ارسال به همه کاربران\n\n";
        
        $help .= "✅ *تأیید:*\n";
        $help .= "└ `/confirm` ← تأیید ارسال پیام همگانی\n\n";
        
        $help .= "📋 *کدهای زبان:*\n";
        $help .= "└ `fa` فارسی | `en` انگلیسی | `ar-IQ` عربی\n";
        $help .= "└ `ru` روسی | `ur` اردو | `tr` ترکی\n";
        $help .= "└ `fr` فرانسوی | `de-DE` آلمانی | `es` اسپانیایی\n";
        $help .= "└ `zh-CN` چینی | `he` عبری | `pt-BR` پرتغالی\n\n";
        
        $help .= "📌 *نکات:*\n";
        $help .= "└ فقط از ربات بله استفاده کنید\n";
        $help .= "└ بعد از `////xx` باید `/confirm` بزنید\n";
        $help .= "└ درخواست تأیید ۵ دقیقه اعتبار دارد\n";
        $help .= "└ پس از ارسال، گزارش کامل به سوپرمین ارسال می‌شود\n";
        
        return $help;
    }

    /**
     * استخراج کد زبان از فرمان
     * ///stats ru => ru
     * ////ru سلام => ru
     */
    public static function parseLanguageFromCommand(string $text): ?string
    {
        if (self::isStatsLanguageCommand($text)) {
            $parts = explode(' ', trim($text), 2);
            return trim($parts[1] ?? '') ?: null;
        }

        if (self::isBroadcastLanguageCommand($text)) {
            $after = substr($text, 4);
            $parts = explode(' ', trim($after), 2);
            return trim($parts[0]) ?: null;
        }

        return null;
    }

    /**
     * استخراج متن پیام از فرمان broadcast
     * ////ru سلام به همه => سلام به همه
     */
    public static function parseMessageFromBroadcast(string $text): ?string
    {
        if (self::isBroadcastLanguageCommand($text)) {
            $after = substr($text, 4);
            $parts = explode(' ', trim($after), 2);
            return trim($parts[1] ?? '') ?: null;
        }

        if (self::isBroadcastAllCommand($text)) {
            return trim(substr($text, 8)) ?: null; // طول "/////all "
        }

        return null;
    }

    /**
     * دریافت نام نمایشی زبان
     */
    public static function getLanguageName(string $code): string
    {
        $languages = [
            'ar-IQ' => '🇸🇦 عربی',
            'ur' => '🇵🇰 اردو',
            'zh-CN' => '🇨🇳 چینی',
            'es' => '🇪🇸 اسپانیایی',
            'de-DE' => '🇩🇪 آلمانی',
            'fr' => '🇫🇷 فرانسوی',
            'en' => '🇬🇧 انگلیسی',
            'fa' => '🇮🇷 فارسی',
            'ru' => '🇷🇺 روسی',
            'tr' => '🇹🇷 ترکی',
            'he' => '🇮🇱 عبری',
            'pt-BR' => '🇧🇷 پرتغالی (برزیل)',
            'pt-PT' => '🇵🇹 پرتغالی (پرتغال)',
            'it' => '🇮🇹 ایتالیایی',
            'id' => '🇮🇩 اندونزیایی',
            'sw' => '🇰🇪 سواحیلی',
            'az' => '🇦🇿 آذربایجانی',
            'bs' => '🇧🇳 بوسنیایی',
        ];

        return $languages[$code] ?? $code;
    }

    /**
     * دریافت نام ربات بر اساس language_code و پلتفرم
     */
    public static function getBotNameByLanguage(string $languageCode, string $platform): ?string
    {
        $bots = config('quran_bots.bots', []);
        foreach ($bots as $bot) {
            if ($bot['language_code'] === $languageCode) {
                $link = $bot['link'] ?? '';
                if ($platform === 'telegram') {
                    if (Str::startsWith($link, 't.me/')) {
                        return substr($link, 5);
                    }
                    return null;
                } elseif ($platform === 'bale') {
                    if (Str::startsWith($link, 'ble.ir/')) {
                        return substr($link, 7);
                    }
                    return null;
                }
                return null;
            }
        }
        return null;
    }
}
