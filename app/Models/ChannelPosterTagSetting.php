<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPosterTagSetting extends Model
{
    protected $fillable = [
        'bot_id',
        'tag',
        'signature_enabled',
    ];

    protected $casts = [
        'signature_enabled' => 'boolean',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }
}
