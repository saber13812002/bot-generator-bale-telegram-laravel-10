<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک محتوای واحد (مطلب) با شناسه مشترک content_id.
 * مستقل از پلتفرم مبدأ است؛ نسخه‌های منتشرشده در جدول versions ذخیره می‌شود.
 */
class ContentSyncContent extends Model
{
    public const CONTENT_TYPE_TEXT = 'text';
    public const CONTENT_TYPE_MEDIA = 'media';

    protected $table = 'content_sync_contents';

    protected $fillable = [
        'bot_id',
        'text',
        'text_hash',
        'content_type',
        'created_by_chat_id',
        'created_by_origin',
        'source_platform',
        'source_channel_id',
        'source_message_id',
        'source_url',
        'source_url_raw',
        'tag',
    ];

    protected $casts = [
        'text_hash' => 'string',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function versions()
    {
        return $this->hasMany(ContentSyncVersion::class, 'content_id');
    }

    public function operations()
    {
        return $this->hasMany(ContentSyncOperation::class, 'content_id');
    }

    /**
     * نسخه‌های منتشرشده (status published)
     */
    public function publishedVersions()
    {
        return $this->versions()->where('publish_status', ContentSyncVersion::STATUS_PUBLISHED);
    }

    /**
     * آیا این محتوا از قبل ثبت شده؟ (بر اساس شناسه پیام مبدأ)
     */
    public static function findBySource(int $botId, string $platform, string $channelId, string $messageId): ?self
    {
        return static::where('bot_id', $botId)
            ->where('source_platform', $platform)
            ->where('source_channel_id', $channelId)
            ->where('source_message_id', $messageId)
            ->first();
    }
}
