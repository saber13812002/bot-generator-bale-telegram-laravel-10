<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'psychology_test_bot_id',
        'chat_id',
        'origin',
        'result_data',
        'completed_at',
    ];

    protected $casts = [
        'result_data' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship با PsychologyTestBot
     */
    public function psychologyTestBot(): BelongsTo
    {
        return $this->belongsTo(PsychologyTestBot::class);
    }
}
