<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryUserBook extends Model
{
    protected $fillable = [
        'bot_user_id',
        'book_id',
        'bot_id',
        'delivered_via',
        'status',
        'revealed_title',
        'is_random',
    ];

    protected $casts = [
        'revealed_title' => 'boolean',
        'is_random' => 'boolean',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
