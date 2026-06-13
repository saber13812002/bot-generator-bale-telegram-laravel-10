<?php

namespace App\Modules\BotOwner\Models;

use Illuminate\Database\Eloquent\Model;

class BotOwnerOtpSession extends Model
{
    protected $fillable = [
        'phone',
        'otp_hash',
        'expires_at',
        'attempts',
        'ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
