<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoemSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'poem_id',
        'suggested_by',
        'line_content',
        'suggested_line_number',
        'status',
    ];

    /**
     * Get the poem for this suggestion.
     */
    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class);
    }

    /**
     * Get the user who made this suggestion.
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'suggested_by');
    }
}
