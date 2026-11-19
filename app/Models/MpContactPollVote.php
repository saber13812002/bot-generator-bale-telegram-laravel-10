<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpContactPollVote extends Model
{
    public const CHOICE_AGREE = 'agree';
    public const CHOICE_DISAGREE = 'disagree';

    protected $fillable = [
        'poll_id',
        'chat_id',
        'origin',
        'choice',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(MpContactPoll::class, 'poll_id');
    }
}
