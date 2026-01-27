<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoemLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'poem_id',
        'version_id',
        'line_number',
        'content',
        'line_type',
    ];

    /**
     * Get the poem that owns this line.
     */
    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class);
    }

    /**
     * Get the version that owns this line.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PoemVersion::class, 'version_id');
    }
}
