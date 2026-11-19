<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsychologyTestCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'psychology_test_bot_id',
        'name',
        'description',
    ];

    /**
     * Relationship با PsychologyTestBot
     */
    public function psychologyTestBot(): BelongsTo
    {
        return $this->belongsTo(PsychologyTestBot::class);
    }

    /**
     * Relationship با Questions
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PsychologyTestQuestion::class);
    }
}
