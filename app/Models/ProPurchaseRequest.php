<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProPurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'user_identifier',
        'payment_method',
        'payment_info',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'approved_at' => 'datetime',
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
     * Scope: Pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: For specific bot
     */
    public function scopeForBot($query, int $botId)
    {
        return $query->where('bot_id', $botId);
    }
}
