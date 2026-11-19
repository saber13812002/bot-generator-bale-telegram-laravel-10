<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrowthQuestion extends Model
{
    protected $fillable = [
        'growth_program_id',
        'question_key',
        'intent',
        'domain',
        'difficulty',
        'frequency',
        'paused_at',
        'source',
        'active',
    ];

    protected $casts = [
        'difficulty' => 'integer',
        'paused_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(GrowthProgram::class, 'growth_program_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(GrowthQuestionVariant::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(GrowthQuestionSchedule::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(GrowthResponse::class);
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null || $this->active === false;
    }
}
