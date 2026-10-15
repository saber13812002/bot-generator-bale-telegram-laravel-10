<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات شخصی هر ادمین برای هر ربات: آخرین تگ استفاده‌شده و حالت انتشار.
 * هر ادمین تنظیمات خودش را دارد و با ادمین‌های دیگر تداخل ندارد.
 */
class ContentSyncAdminPrefs extends Model
{
    public const MODE_IMMEDIATE = 'immediate';
    public const MODE_QUEUE = 'queue';

    protected $table = 'content_sync_admin_prefs';

    protected $fillable = [
        'bot_id',
        'admin_chat_id',
        'origin',
        'last_tag',
        'publish_mode',
    ];

    public static function forAdmin(int $botId, string $adminChatId, string $origin): self
    {
        $prefs = static::firstOrCreate(
            [
                'bot_id' => $botId,
                'admin_chat_id' => $adminChatId,
                'origin' => $origin,
            ],
            ['publish_mode' => self::MODE_IMMEDIATE]
        );

        return $prefs;
    }

    public static function getMode(int $botId, string $adminChatId, string $origin): string
    {
        $prefs = static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->first();

        return $prefs?->publish_mode ?? self::MODE_IMMEDIATE;
    }

    public static function rememberTag(int $botId, string $adminChatId, string $origin, ?string $tag): void
    {
        $prefs = static::forAdmin($botId, $adminChatId, $origin);
        if ($prefs->last_tag !== $tag) {
            $prefs->last_tag = $tag;
            $prefs->save();
        }
    }
}
