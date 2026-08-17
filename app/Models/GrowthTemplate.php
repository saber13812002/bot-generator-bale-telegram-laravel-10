<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'default_tone',
        'ai_instructions',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(GrowthTemplateQuestion::class);
    }
}
