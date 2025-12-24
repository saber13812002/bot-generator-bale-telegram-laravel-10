<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Calculate total points from approved tasks
     */
    public function getTotalPointsAttribute(): int
    {
        return $this->tasks()
            ->where('task_status', 'approved')
            ->sum('points');
    }
}
