<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'prompt_id',
        'content_id',
        'points',
        'max_personnel',
        'current_personnel_count',
        'status',
    ];

    /**
     * Get the tenant that owns the mission.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the prompt for this mission.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * Get the primary content for this mission.
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Get all contents for this mission.
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'mission_contents')
            ->withPivot('sort_order')
            ->orderBy('mission_contents.sort_order');
    }

    /**
     * Get all personnel assigned to this mission.
     */
    public function personnel(): BelongsToMany
    {
        return $this->belongsToMany(Personnel::class, 'mission_personnel')
            ->withPivot([
                'status',
                'result_link',
                'approval_message_id',
                'rejection_reason',
                'approved_by_chat_id',
                'approved_at',
                'rejected_at',
                'started_at',
                'completed_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get mission personnel pivot records.
     */
    public function missionPersonnel(): HasMany
    {
        return $this->hasMany(MissionPersonnel::class);
    }

    /**
     * Scope a query to only include active missions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include available missions.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->whereColumn('current_personnel_count', '<', 'max_personnel');
    }
}
