<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'bot_mother_id',
        'bot_id',
        'title',
        'poem_type',
        'status',
        'likes_count',
    ];

    /**
     * Get the bot user that owns this poem.
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    /**
     * Get the bot that owns this poem.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    /**
     * Get the versions for this poem.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PoemVersion::class);
    }

    /**
     * Get the lines for this poem.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PoemLine::class);
    }

    /**
     * Get the likes for this poem.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PoemLike::class);
    }

    /**
     * Get the suggestions for this poem.
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(PoemSuggestion::class);
    }

    /**
     * Get the collaborations (forks) for this poem.
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(PoemCollaboration::class, 'original_poem_id');
    }

    /**
     * Check if user has liked this poem.
     */
    public function isLikedBy(int $botUserId): bool
    {
        return $this->likes()->where('bot_user_id', $botUserId)->exists();
    }
}
