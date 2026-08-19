<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthProfile extends Model
{
    public const DEFAULT_BOARD_SLUGS = [
        'health',
        'family',
        'work',
        'spirituality',
        'study',
        'self',
    ];

    public const CATALOG_SLUGS = [
        'health',
        'family',
        'work',
        'spirituality',
        'study',
        'self',
        'sport',
        'relations',
        'custom',
    ];

    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'mode',
        'tone',
        'timezone',
        'notify_time',
        'quiet_hours_start',
        'quiet_hours_end',
        'depth',
        'intensity',
        'interaction_budget_per_day',
        'onboarding_completed_at',
        'day_reset_hour',
        'ai_consent',
        'settings',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'interaction_budget_per_day' => 'integer',
        'day_reset_hour' => 'integer',
        'ai_consent' => 'boolean',
        'settings' => 'array',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(GrowthProgram::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(GrowthProfileTopic::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(GrowthReview::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(GrowthDailyCheckin::class);
    }

    public function activeProgram(): ?GrowthProgram
    {
        return $this->programs()->where('status', 'active')->latest('id')->first();
    }

    public function activePrograms(): HasMany
    {
        return $this->programs()->where('status', 'active');
    }

    public function dailyBudget(): int
    {
        return max(1, (int) $this->interaction_budget_per_day);
    }

    public function isAdvanced(): bool
    {
        return $this->mode === 'advanced';
    }
}
