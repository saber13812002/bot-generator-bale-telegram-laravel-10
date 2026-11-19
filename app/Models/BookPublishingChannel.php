<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookPublishingChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'channel_chat_id',
        'origin',
        'is_active',
    ];

    /**
     * Get the bot that owns this channel.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the publishing queue items for this channel.
     */
    public function publishingQueues(): HasMany
    {
        return $this->hasMany(BookPublishingQueue::class, 'channel_id');
    }
}
