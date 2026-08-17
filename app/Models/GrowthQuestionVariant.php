<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthQuestionVariant extends Model
{
    protected $fillable = [
        'growth_question_id',
        'body',
        'locale',
        'tone',
        'difficulty',
    ];

    protected $casts = [
        'difficulty' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(GrowthQuestion::class, 'growth_question_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(GrowthResponse::class, 'growth_question_variant_id');
    }
}
