<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiLlm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all missions that use this AI.
     */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'ai_id');
    }

    /**
     * Get all mission personnel that selected this AI.
     */
    public function missionPersonnel(): HasMany
    {
        return $this->hasMany(MissionPersonnel::class, 'selected_ai_id');
    }

    /**
     * Scope a query to only include active AI/LLMs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
