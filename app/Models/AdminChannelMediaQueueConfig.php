<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelMediaQueueConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_chat_id',
        'media_queue_id',
        'bale_channel_chat_id',
        'telegram_channel_chat_id',
        'eitaa_channel_chat_id',
        'last_sent_item_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_item_index' => 'integer',
    ];

    public function mediaQueue(): BelongsTo
    {
        return $this->belongsTo(MediaQueue::class);
    }

    public function hasBale(): bool
    {
        return !empty($this->bale_channel_chat_id);
    }

    public function hasTelegram(): bool
    {
        return !empty($this->telegram_channel_chat_id);
    }

    public function hasEitaa(): bool
    {
        return !empty($this->eitaa_channel_chat_id);
    }
}
