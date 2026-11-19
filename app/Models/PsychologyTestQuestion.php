<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyTestQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'psychology_test_bot_id',
        'psychology_test_category_id',
        'question_text',
        'weight',
        'direction',
        'order',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'direction' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Relationship با PsychologyTestBot
     */
    public function psychologyTestBot(): BelongsTo
    {
        return $this->belongsTo(PsychologyTestBot::class);
    }

    /**
     * Relationship با Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PsychologyTestCategory::class, 'psychology_test_category_id');
    }
}
