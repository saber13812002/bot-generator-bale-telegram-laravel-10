<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryUserSubscription extends Model
{
    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'plan',
        'books_used',
        'books_limit',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function remainingBooks(): int
    {
        return max(0, $this->books_limit - $this->books_used);
    }

    public function canDeliver(): bool
    {
        return $this->status === 'active' && $this->remainingBooks() > 0;
    }
}
