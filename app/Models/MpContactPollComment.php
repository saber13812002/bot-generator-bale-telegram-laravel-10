<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpContactPollComment extends Model
{
    protected $fillable = [
        'poll_id',
        'bot_id',
        'chat_id',
        'origin',
        'body',
        'tracking_code',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(MpContactPoll::class, 'poll_id');
    }
}
