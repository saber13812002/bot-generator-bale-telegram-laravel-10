<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthDailyCheckin extends Model
{
    protected $fillable = [
        'growth_profile_id',
        'day_key',
        'energy',
        'mood',
        'sleep_hours',
        'moved',
        'focus_slugs',
        'evening_note',
    ];

    protected $casts = [
        'energy' => 'integer',
        'sleep_hours' => 'integer',
        'moved' => 'boolean',
        'focus_slugs' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GrowthProfile::class, 'growth_profile_id');
    }

    public function isComplete(): bool
    {
        return $this->mood !== null && $this->energy !== null;
    }
}
