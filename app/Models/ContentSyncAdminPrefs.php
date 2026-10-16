<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات شخصی هر ادمین برای هر ربات: آخرین تگ، حالت انتشار، روش حذف تکراری (dedup) و fast mode.
 * هر ادمین تنظیمات خودش را دارد و با ادمین‌های دیگر تداخل ندارد.
 *
 * ادمینی که هنوز تنظیم شخصی نکرده، پیش‌فرض مشترک ربات (content_sync_bot_defaults —
 * قابل تنظیم فقط توسط مالک) را به ارث می‌برد.
 */
class ContentSyncAdminPrefs extends Model
{
    public const MODE_IMMEDIATE = 'immediate';
    public const MODE_QUEUE = 'queue';

    public const DEDUP_SOURCE_CHANNEL = 'exclude_source_channel';
    public const DEDUP_SOURCE_PLATFORM = 'exclude_source_platform';

    // روش نمایش منبع (Source Attribution)
    public const ATTR_SIGNED = 'signed';
    public const ATTR_LINK_ONLY = 'link_only';
    public const ATTR_NONE = 'none';

    protected $table = 'content_sync_admin_prefs';

    protected $fillable = [
        'bot_id',
        'admin_chat_id',
        'origin',
        'last_tag',
        'publish_mode',
        'dedup_mode',
        'fast_mode',
        'settings_asked',
        'source_attribution',
    ];

    protected $casts = [
        'fast_mode' => 'boolean',
        'settings_asked' => 'boolean',
    ];

    public static function forAdmin(int $botId, string $adminChatId, string $origin): self
    {
        $prefs = static::firstOrCreate(
            [
                'bot_id' => $botId,
                'admin_chat_id' => $adminChatId,
                'origin' => $origin,
            ],
            [
                'publish_mode' => self::MODE_IMMEDIATE,
                'dedup_mode' => self::DEDUP_SOURCE_CHANNEL,
                'fast_mode' => true,
            ]
        );

        return $prefs;
    }

    /**
     * آیا این ادمین حداقل یک‌بار تنظیم شخصی (publish/dedup/fast) انجام داده؟
     * برای سؤالِ یک‌بارِ تنظیمات استفاده می‌شود — اگر شخصی‌سازی نشده باشد،
     * یک‌بار دکمه‌ها نشان داده می‌شود و دیگر تکرار نمی‌شود.
     */
    public static function hasPersonalized(int $botId, string $adminChatId, string $origin): bool
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        return $prefs !== null;
    }

    public static function getMode(int $botId, string $adminChatId, string $origin): string
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        if ($prefs?->publish_mode) {
            return (string) $prefs->publish_mode;
        }

        $defaults = static::botDefaults($botId, $origin);

        return $defaults?->publish_mode ?? self::MODE_IMMEDIATE;
    }

    /**
     * روش حذف تکراری: پیش‌فرض exclude_source_channel (فقط همان کانال مبدأ).
     */
    public static function getDedupMode(int $botId, string $adminChatId, string $origin): string
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        if ($prefs?->dedup_mode) {
            return (string) $prefs->dedup_mode;
        }

        $defaults = static::botDefaults($botId, $origin);

        return $defaults?->dedup_mode ?? self::DEDUP_SOURCE_CHANNEL;
    }

    /**
     * روش نمایش منبع (Source Attribution): signed | link_only | none
     * پیش‌فرض signed (برچسب + لینک منبع).
     */
    public static function getAttributionMode(int $botId, string $adminChatId, string $origin): string
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        $value = $prefs?->source_attribution ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $defaults = static::botDefaults($botId, $origin);
        $value = $defaults?->source_attribution ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return self::ATTR_SIGNED;
    }

    /**
     * آیا سؤالِ یک‌باره‌ی تنظیمات اولیه هنوز نشده است؟
     * (دکمه‌های کوتاه تنظیمات فقط یک‌بار — اولین لینک — نمایش داده می‌شوند.)
     */
    public static function isSettingsAsked(int $botId, string $adminChatId, string $origin): bool
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        return $prefs !== null && (bool) $prefs->settings_asked;
    }

    public static function markSettingsAsked(int $botId, string $adminChatId, string $origin): void
    {
        $prefs = static::forAdmin($botId, $adminChatId, $origin);
        if (!(bool) $prefs->settings_asked) {
            $prefs->settings_asked = true;
            $prefs->save();
        }
    }

    /**
     * fast mode: با ON، تگ و روش انتخابی از عملیات‌های قبلی به خاطر سپرده می‌شود
     * و سؤال اضافه نمی‌شود؛ با OFF، هر بار که ابهام باشد دوباره سؤال می‌شود.
     */
    public static function getFastMode(int $botId, string $adminChatId, string $origin): bool
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        if ($prefs && array_key_exists('fast_mode', $prefs->getAttributes()) && $prefs->fast_mode !== null) {
            return (bool) $prefs->fast_mode;
        }

        $defaults = static::botDefaults($botId, $origin);

        return $defaults ? (bool) $defaults->fast_mode : true;
    }

    public static function rememberTag(int $botId, string $adminChatId, string $origin, ?string $tag): void
    {
        $prefs = static::forAdmin($botId, $adminChatId, $origin);
        if ($prefs->last_tag !== $tag) {
            $prefs->last_tag = $tag;
            $prefs->save();
        }
    }

    /**
     * پیش‌فرض مشترک کل ربات (تنظیم‌شده توسط مالک).
     */
    public static function botDefaults(int $botId, string $origin): ?ContentSyncBotDefaults
    {
        return ContentSyncBotDefaults::where('bot_id', $botId)
            ->where('origin', $origin)
            ->first();
    }

    public static function botDefaultsFor(int $botId, string $origin): ContentSyncBotDefaults
    {
        return ContentSyncBotDefaults::firstOrCreate(
            ['bot_id' => $botId, 'origin' => $origin],
            [
                'publish_mode' => self::MODE_IMMEDIATE,
                'dedup_mode' => self::DEDUP_SOURCE_CHANNEL,
                'fast_mode' => true,
                'source_attribution' => self::ATTR_SIGNED,
            ]
        );
    }
}
