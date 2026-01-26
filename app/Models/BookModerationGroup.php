<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookModerationGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'group_chat_id',
        'origin',
        'is_active',
    ];

    /**
     * Get the bot that owns this moderation group.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
