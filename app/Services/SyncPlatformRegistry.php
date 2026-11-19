<?php

namespace App\Services;

/**
 * دسترسی‌گر statیک به رجیستری پلتفرم‌های همگام‌سازی (config/sync_platforms.php).
 *
 * Phase 5: هدف تعمیم‌دادن موتور کانال‌پست‌ر/همگام‌سازی از if/switchهای
 * سخت‌کدشده بر اساس origin به «قواعد config-driven» است. همه‌ی نقاطی که
 * قبلاً `$origin === 'bale' ? ... : ...` داشتند باید از این کلاس استفاده کنند.
 *
 * پلتفرم‌های ناشناخته روی رفتار موروثی telegram (vendor + دانلود رسانه از
 * مبدأ) fall back می‌کنند تا هیچ‌جای قدیمی رفتار خود را از دست ندهد.
 *
 * وضعیت: MOCK_TESTED (فقط با Http::fake). LIVE_TESTED در Phase 7.
 */
class SyncPlatformRegistry
{
    /** ستون bots که توکن ربات مبدأ در آن نگه داشته می‌شود. */
    public static function tokenColumn(string $origin): string
    {
        return self::entry($origin)['token_column'];
    }

    /** ستون bots که chat_id مالک ربات مبدأ در آن نگه داشته می‌شود. */
    public static function ownerColumn(string $origin): string
    {
        return self::entry($origin)['owner_column'];
    }

    /**
     * نوع publisher: 'vendor' (SDK تلگرام/بله) یا 'native' (کلاینت‌های داخلی
     * SoroushBotClient / RubikaBotClient).
     */
    public static function publisher(string $origin): string
    {
        return self::entry($origin)['publisher'];
    }

    /**
     * آیا موتور می‌تواند فایل رسانه را از API مبدأ دانلود کند؟
     * false = data-driven skip (دانلود رسانه از آن API تأییدنشده است).
     */
    public static function canDownloadMediaFromOrigin(string $origin): bool
    {
        return (bool) self::entry($origin)['download_media_from_origin'];
    }

    /** برچسب نمایشی پلتفرم (برای گزارش/پیام). */
    public static function label(string $origin): string
    {
        return self::entry($origin)['label'];
    }

    /**
     * ورودی کامل پلتفرم از config؛ برای origin ناشناشته، ورودی موروثی telegram.
     */
    private static function entry(string $origin): array
    {
        $all = config('sync_platforms', []);

        return $all[$origin] ?? $all['telegram'] ?? [
            'label' => 'Telegram',
            'token_column' => 'telegram_bot_token',
            'owner_column' => 'telegram_owner_chat_id',
            'publisher' => 'vendor',
            'download_media_from_origin' => true,
        ];
    }
}
