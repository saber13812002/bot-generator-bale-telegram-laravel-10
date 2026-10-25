<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPosterDestination extends Model
{
    public const PLATFORM_BALE = 'bale';
    public const PLATFORM_TELEGRAM = 'telegram';
    public const PLATFORM_EITAA = 'eitaa';
    public const PLATFORM_SOROUSH = 'soroush';
    public const PLATFORM_RUBIKA = 'rubika';

    protected $fillable = [
        'bot_id',
        'platform',
        'channel_chat_id',
        'channel_title',
        'channel_link',
        'signature_label',
        'signature_type',
        'signature_enabled',
        'channel_username',
        'tag',
        'bot_token',
        'verified_at',
        'is_active',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
        'signature_enabled' => 'boolean',
    ];

    /**
     * Relationship to the Bot model.
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }
}
