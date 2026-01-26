<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookPageVoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_page_scan_id',
        'user_id',
        'bot_id',
        'file_id',
        'file_unique_id',
        'points_awarded',
    ];

    /**
     * Get the book page scan that owns this voice.
     */
    public function bookPageScan(): BelongsTo
    {
        return $this->belongsTo(BookPageScan::class);
    }

    /**
     * Get the user who created this voice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }

    /**
     * Get the bot that owns this voice.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
