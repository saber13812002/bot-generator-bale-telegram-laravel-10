<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * پیش‌فرض مشترک همگام‌سازی برای کل یک ربات (به ازای هر origin).
 * فقط مالک ربات می‌تواند آن را از /settings تغییر دهد.
 * ادمین‌هایی که تنظیم شخصی نکرده‌اند، این مقادیر را به ارث می‌برند.
 */
class ContentSyncBotDefaults extends Model
{
    protected $table = 'content_sync_bot_defaults';

    protected $fillable = [
        'bot_id',
        'origin',
        'set_by_chat_id',
        'publish_mode',
        'dedup_mode',
        'fast_mode',
    ];

    protected $casts = [
        'fast_mode' => 'boolean',
    ];
}
