<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookPublishingQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_page_scan_id',
        'book_page_voice_id',
        'bot_id',
        'channel_id',
        'scheduled_at',
        'published_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Get the book page scan.
     */
    public function bookPageScan(): BelongsTo
    {
        return $this->belongsTo(BookPageScan::class);
    }

    /**
     * Get the book page voice.
     */
    public function bookPageVoice(): BelongsTo
    {
        return $this->belongsTo(BookPageVoice::class);
    }

    /**
     * Get the bot.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the publishing channel.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(BookPublishingChannel::class, 'channel_id');
    }
}
