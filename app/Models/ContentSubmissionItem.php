<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSubmissionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'submitter_chat_id',
        'content_type',
        'content_text',
        'file_id',
        'file_unique_id',
        'status',
        'approval_message_id',
        'first_approver_chat_id',
        'second_approver_chat_id',
        'approved_at',
        'published_at',
        'channel_message_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the bot that owns this item.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

}
