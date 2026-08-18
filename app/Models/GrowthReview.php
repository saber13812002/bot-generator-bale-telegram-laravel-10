<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthReview extends Model
{
    protected $fillable = [
        'growth_profile_id',
        'period_start',
        'period_end',
        'body',
        'stats',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'stats' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GrowthProfile::class, 'growth_profile_id');
    }
}
