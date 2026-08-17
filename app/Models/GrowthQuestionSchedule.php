<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthQuestionSchedule extends Model
{
    protected $fillable = [
        'growth_question_id',
        'cadence_type',
        'days_of_week',
        'time_local',
        'next_due_at',
        'last_sent_at',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'next_due_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(GrowthQuestion::class, 'growth_question_id');
    }
}
