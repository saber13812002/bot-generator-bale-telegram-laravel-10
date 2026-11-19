<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LibraryPlanRequest extends Model
{
    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'plan',
        'user_identifier',
        'payment_method',
        'payment_info',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function bot(): BelongsTo
        {
            return $this->belongsTo(Bot::class);
        }

        /**
         * The user's library subscription for the same bot (composite bot_user_id + bot_id).
         * Used for read-only milestone reward visibility in Nova.
         */
        public function subscription(): HasOne
            {
                $relation = $this->hasOne(LibraryUserSubscription::class, 'bot_user_id', 'bot_user_id');
                if ($this->bot_id !== null) {
                    $relation->where('bot_id', $this->bot_id);
                }
                return $relation;
            }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
