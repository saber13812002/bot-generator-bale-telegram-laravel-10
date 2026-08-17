<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthProgram extends Model
{
    protected $fillable = [
        'growth_profile_id',
        'bot_id',
        'bot_user_id',
        'name',
        'template_slug',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GrowthProfile::class, 'growth_profile_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GrowthQuestion::class);
    }
}
