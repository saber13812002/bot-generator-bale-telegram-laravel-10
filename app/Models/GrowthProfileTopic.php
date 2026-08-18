<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthProfileTopic extends Model
{
    protected $fillable = [
        'growth_profile_id',
        'template_slug',
        'enabled',
        'cadence',
        'sort_order',
        'custom_label',
        'weekdays',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
        'weekdays' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GrowthProfile::class, 'growth_profile_id');
    }

    public function displayLabel(): string
    {
        if (is_string($this->custom_label) && $this->custom_label !== '') {
            return $this->custom_label;
        }

        $key = 'growth_companion.focus.'.$this->template_slug;
        $translated = trans($key);

        return $translated === $key ? $this->template_slug : $translated;
    }

    public function isWeekly(): bool
    {
        return $this->cadence === 'weekly';
    }
}
