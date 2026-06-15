<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
