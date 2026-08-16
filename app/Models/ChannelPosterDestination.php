<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPosterDestination extends Model
{
    public const PLATFORM_BALE = 'bale';
    public const PLATFORM_TELEGRAM = 'telegram';
    public const PLATFORM_EITAA = 'eitaa';
    public const PLATFORM_SOROUSH = 'soroush';

    protected $fillable = [
        'bot_id',
        'platform',
        'channel_chat_id',
        'channel_title',
        'tag',
        'bot_token',
        'verified_at',
        'is_active',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
