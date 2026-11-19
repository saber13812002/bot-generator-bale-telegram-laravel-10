<?php

namespace App\Modules\BotOwner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotOwnerProRequest extends Model
{
    protected $fillable = [
        'bot_owner_id',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function botOwner(): BelongsTo
    {
        return $this->belongsTo(BotOwner::class, 'bot_owner_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
