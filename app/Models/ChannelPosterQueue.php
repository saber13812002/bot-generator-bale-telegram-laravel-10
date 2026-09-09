<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPosterQueue extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'channel_poster_queue';

    protected $fillable = [
        'bot_id',
        'tag',
        'content_type',
        'text',
        'file_id',
        'signature_enabled',
        'scheduled_at',
        'published_at',
        'status',
        'owner_chat_id',
        'owner_origin',
    ];

    protected $casts = [
        'signature_enabled' => 'boolean',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function publishLogs()
    {
        return $this->hasMany(ChannelPosterPublishLog::class, 'queue_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReady($query)
    {
        return $query->pending()->where('scheduled_at', '<=', now());
    }
}
