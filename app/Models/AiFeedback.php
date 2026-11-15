<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — بازخورد customerهای pilot (brief §8).
 */
class AiFeedback extends Model
{
    public const CATEGORIES = ['general', 'bug', 'quality', 'missing', 'other'];

    public const STATUSES = ['new', 'in_progress', 'resolved', 'ignored'];

    protected $table = 'ai_feedback';

    protected $fillable = [
        'bot_id', 'customer_id', 'user_id', 'feature', 'category',
        'comment', 'rating', 'status', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'bot_id'      => 'integer',
        'customer_id' => 'integer',
        'user_id'     => 'integer',
        'rating'      => 'integer',
        'resolved_at' => 'datetime',
    ];
}
