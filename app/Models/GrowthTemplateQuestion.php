<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthTemplateQuestion extends Model
{
    protected $fillable = [
        'growth_template_id',
        'question_key',
        'intent',
        'domain',
        'difficulty',
        'default_frequency',
        'variants',
    ];

    protected $casts = [
        'difficulty' => 'integer',
        'variants' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GrowthTemplate::class, 'growth_template_id');
    }
}
