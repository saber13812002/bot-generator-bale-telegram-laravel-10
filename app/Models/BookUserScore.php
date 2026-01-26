<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookUserScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'total_points',
        'scans_count',
        'voices_count',
    ];

    /**
     * Get the bot that owns this score.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the user that owns this score.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }
}
