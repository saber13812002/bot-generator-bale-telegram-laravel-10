<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoemCollaboration extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_poem_id',
        'forked_poem_id',
        'forked_by',
    ];

    /**
     * Get the original poem.
     */
    public function originalPoem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'original_poem_id');
    }

    /**
     * Get the forked poem.
     */
    public function forkedPoem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'forked_poem_id');
    }

    /**
     * Get the user who forked the poem.
     */
    public function forkedBy(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'forked_by');
    }
}
