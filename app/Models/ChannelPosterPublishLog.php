<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPosterPublishLog extends Model
{
    protected $fillable = [
        'bot_id',
        'queue_id',
        'destination_id',
        'platform',
        'success',
        'message_id',
        'error',
        'published_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function queueItem()
    {
        return $this->belongsTo(ChannelPosterQueue::class, 'queue_id');
    }

    public function destination()
    {
        return $this->belongsTo(ChannelPosterDestination::class, 'destination_id');
    }
}
