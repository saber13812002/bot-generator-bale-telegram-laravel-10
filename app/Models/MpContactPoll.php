<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpContactPoll extends Model
{
    protected $fillable = [
        'bot_id',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(MpContactPollVote::class, 'poll_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MpContactPollComment::class, 'poll_id');
    }
}
