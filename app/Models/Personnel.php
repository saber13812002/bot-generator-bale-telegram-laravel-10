<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Personnel extends Model
{
    use HasFactory;

    protected $table = 'personnel';

    protected $fillable = [
        'first_name',
        'last_name',
        'national_code',
        'phone_number',
        'tenant_id',
        'rank',
    ];

    /**
     * Get the tenant that owns the personnel.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the message queues for the personnel.
     */
    public function messageQueues()
    {
        return $this->hasMany(PersonnelMessageQueue::class);
    }

    /**
     * Get the tasks assigned to this personnel.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }

    /**
     * Get the missions assigned to this personnel.
     */
    public function missions()
    {
        return $this->belongsToMany(Mission::class, 'mission_personnel')
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
    public function missionPersonnel()
    {
        return $this->hasMany(MissionPersonnel::class);
    }

    /**
     * Calculate total points from approved tasks and missions
     */
    public function getTotalPointsAttribute(): int
    {
        $taskPoints = $this->tasks()
            ->where('task_status', 'approved')
            ->sum('points');

        // Use mission_personnel.status to avoid ambiguity after join
        $missionPoints = $this->missionPersonnel()
            ->where('mission_personnel.status', 'approved')
            ->join('missions', 'mission_personnel.mission_id', '=', 'missions.id')
            ->sum('missions.points');

        return $taskPoints + $missionPoints;
    }

    /**
     * Get all tags for this personnel.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
