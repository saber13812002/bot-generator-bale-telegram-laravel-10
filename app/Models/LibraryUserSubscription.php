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
        'reward_target',
        'reward_bonus',
        'reward_granted_at',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'reward_granted_at' => 'datetime',
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

    /**
     * Milestone reward engine
     * ------------------------------------------------------------------
     * A "milestone" is armed when a paid plan is confirmed:
     *   reward_target  = plan book limit (N)
     * When books_used reaches N we assume the user listened to all of
     * them and the win moment fires exactly once (reward_granted_at).
     */

    /**
     * Is a milestone currently armed (paid plan confirmed, reward not yet given)?
     */
    public function hasArmedMilestone(): bool
    {
        return $this->reward_target !== null
            && $this->reward_granted_at === null;
    }

    /**
     * Milestone reached? (books_used >= target and not yet granted)
     */
    public function milestoneReached(): bool
    {
        return $this->hasArmedMilestone() && $this->books_used >= $this->reward_target;
    }

    /**
     * Arm (or re-arm) a milestone for the given target book count.
     */
    public function armMilestone(int $target): void
    {
        $this->reward_target = $target;
        $this->reward_bonus = null;
        $this->reward_granted_at = null;
    }
}
