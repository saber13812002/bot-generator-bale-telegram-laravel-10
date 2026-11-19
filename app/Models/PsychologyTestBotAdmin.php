<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyTestBotAdmin extends Model
{
    use HasFactory;

    protected $fillable = [
        'psychology_test_bot_id',
        'chat_id',
        'origin',
        'is_creator',
    ];

    protected $casts = [
        'is_creator' => 'boolean',
    ];

    /**
     * Relationship با PsychologyTestBot
     */
    public function psychologyTestBot(): BelongsTo
    {
        return $this->belongsTo(PsychologyTestBot::class);
    }
}
