<?php

namespace App\Modules\BotOwner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotOwner extends Model
{
    protected $fillable = [
        'phone',
        'name',
        'bale_chat_id',
        'is_pro',
        'pro_confirmed_at',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'is_pro' => 'boolean',
        'pro_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function bots(): HasMany
    {
        return $this->hasMany(\App\Models\Bot::class, 'bot_owner_id');
    }

    public function proRequests(): HasMany
    {
        return $this->hasMany(BotOwnerProRequest::class, 'bot_owner_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
