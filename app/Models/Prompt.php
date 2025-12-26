<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Prompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'task_id',
        'tenant_id',
        'mission_id',
    ];

    /**
     * Get the task that owns the prompt.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the tenant that owns the prompt.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the mission that owns the prompt.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Get all tags for this prompt.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
