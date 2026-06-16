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
        'pro_expires_at',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'is_pro' => 'boolean',
        'pro_confirmed_at' => 'datetime',
        'pro_expires_at' => 'datetime',
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

    public function hasActivePro(): bool
    {
        if (!$this->is_pro) {
            return false;
        }

        if ($this->pro_expires_at === null) {
            return true;
        }

        if ($this->pro_expires_at->isPast()) {
            $this->update(['is_pro' => false]);

            return false;
        }

        return true;
    }

    public function isProUnlimited(): bool
    {
        return $this->is_pro && $this->pro_expires_at === null;
    }
}
