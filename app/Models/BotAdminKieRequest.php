<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAdminKieRequest extends Model
{
    protected $fillable = [
        'bot_id',
        'chat_id',
        'origin',
        'bot_user_id',
        'first_name',
        'last_name',
        'username',
        'alias_name',
        'email',
        'webhook_endpoint',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function displayName(): string
    {
        $parts = array_filter([
            trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
            $this->alias_name,
            $this->username ? '@' . $this->username : null,
        ]);

        return $parts[0] ?? (string) $this->chat_id;
    }
}
