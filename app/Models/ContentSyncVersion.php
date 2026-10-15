<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نسخه منتشرشده (یا در حال انتشار) یک محتوا در یک مقصد خاص.
 * post_url + link_status قلب سیستم ارجاع‌دهی است.
 */
class ContentSyncVersion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const LINK_CONFIRMED = 'confirmed';
    public const LINK_CONSTRUCTED = 'constructed';
    public const LINK_UNVERIFIED = 'unverified';
    public const LINK_NONE = 'none';

    protected $table = 'content_sync_versions';

    protected $fillable = [
        'content_id',
        'bot_id',
        'destination_id',
        'platform',
        'channel_id',
        'channel_username',
        'message_id',
        'post_url',
        'link_status',
        'publish_status',
        'published_at',
        'last_error',
        'queue_id',
        'chunks_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function content()
    {
        return $this->belongsTo(ContentSyncContent::class, 'content_id');
    }

    public function destination()
    {
        return $this->belongsTo(ChannelPosterDestination::class, 'destination_id');
    }

    public function queueItem()
    {
        return $this->belongsTo(ChannelPosterQueue::class, 'queue_id');
    }

    public function scopePublished($query)
    {
        return $query->where('publish_status', self::STATUS_PUBLISHED);
    }

    public function scopeFailed($query)
    {
        return $query->where('publish_status', self::STATUS_FAILED);
    }

    public function scopeWithLink($query)
    {
        return $query->whereNotNull('post_url')->where('post_url', '!=', '');
    }
}
