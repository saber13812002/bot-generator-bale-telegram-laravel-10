<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'status',
        'purchase_requested_at',
        'purchase_confirmed_at',
        'confirmed_by_admin_id',
        'expires_at',
        'payment_info',
    ];

    protected $casts = [
        'purchase_requested_at' => 'datetime',
        'purchase_confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
        'payment_info' => 'array',
    ];

    /**
     * Relationship: Bot User
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    /**
     * Relationship: Bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    /**
     * Scope: Active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: For specific bot
     */
    public function scopeForBot($query, int $botId)
    {
        return $query->where('bot_id', $botId);
    }

    /**
     * بررسی فعال بودن
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->status = 'expired';
            $this->save();
            return false;
        }

        return true;
    }
}
