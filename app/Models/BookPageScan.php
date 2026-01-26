<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookPageScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_page_id',
        'user_id',
        'bot_id',
        'page_number',
        'file_id',
        'file_unique_id',
        'status',
        'approval_message_id',
        'approved_by_chat_id',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'points_awarded',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the book page that owns this scan.
     */
    public function bookPage(): BelongsTo
    {
        return $this->belongsTo(BookPage::class);
    }

    /**
     * Get the user who created this scan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }

    /**
     * Get the bot that owns this scan.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the voice for this scan.
     */
    public function voice(): HasOne
    {
        return $this->hasOne(BookPageVoice::class);
    }

    /**
     * Get the publishing queue items for this scan.
     */
    public function publishingQueues(): HasMany
    {
        return $this->hasMany(BookPublishingQueue::class);
    }
}
