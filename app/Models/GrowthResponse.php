<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthResponse extends Model
{
    protected $fillable = [
        'growth_question_id',
        'growth_question_variant_id',
        'bot_user_id',
        'bot_id',
        'body',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(GrowthQuestion::class, 'growth_question_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(GrowthQuestionVariant::class, 'growth_question_variant_id');
    }
}
