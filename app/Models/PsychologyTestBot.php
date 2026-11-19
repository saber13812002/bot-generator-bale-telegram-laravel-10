<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsychologyTestBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'title',
        'description',
        'back_navigation',
    ];

    /**
     * Relationship با Bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Relationship با Categories
     */
    public function categories(): HasMany
    {
        return $this->hasMany(PsychologyTestCategory::class);
    }

    /**
     * Relationship با Questions
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PsychologyTestQuestion::class);
    }

    /**
     * Relationship با Results
     */
    public function results(): HasMany
    {
        return $this->hasMany(PsychologyTestResult::class);
    }

    /**
     * Relationship با Admins
     */
    public function admins(): HasMany
    {
        return $this->hasMany(PsychologyTestBotAdmin::class);
    }
}
