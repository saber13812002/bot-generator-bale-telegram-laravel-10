<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionContent extends Model
{
    use HasFactory;

    protected $table = 'mission_contents';

    protected $fillable = [
        'mission_id',
        'content_id',
        'sort_order',
    ];

    /**
     * Get the mission that owns this content assignment.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Get the content assigned to this mission.
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
