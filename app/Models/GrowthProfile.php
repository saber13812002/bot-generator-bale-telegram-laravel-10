<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthProfile extends Model
{
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
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'interaction_budget_per_day' => 'integer',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(GrowthProgram::class);
    }

    public function activeProgram(): ?GrowthProgram
    {
        return $this->programs()->where('status', 'active')->latest('id')->first();
    }
}
