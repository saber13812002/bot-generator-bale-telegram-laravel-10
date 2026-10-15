<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * چند ادمین مجاز برای یک ربات (هر ادمین در هر پیام‌رسان به‌صورت جداگانه).
 * مالک اصلی ربات همیشه ادمین است؛ این جدول ادمین‌های افزوده را نگه می‌دارد.
 */
class ContentSyncAdmin extends Model
{
    protected $table = 'content_sync_admins';

    protected $fillable = [
        'bot_id',
        'admin_chat_id',
        'origin',
    ];

    public static function add(int $botId, string $adminChatId, string $origin): self
    {
        return static::firstOrCreate(
            [
                'bot_id' => $botId,
                'admin_chat_id' => $adminChatId,
                'origin' => $origin,
            ]
        );
    }

    public static function isRegistered(int $botId, string $adminChatId, string $origin): bool
    {
        return static::where('bot_id', $botId)
            ->where('admin_chat_id', $adminChatId)
            ->where('origin', $origin)
            ->exists();
    }
}
