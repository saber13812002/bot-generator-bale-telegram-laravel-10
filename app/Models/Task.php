<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_name',
        'assigned_user_id',
        'task_status',
        'task_time',
        'assigned_time',
        'reserved_time',
        'points',
        'final_link',
        'approval_message_id',
        'rejection_reason',
        'approved_by_chat_id',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'task_time' => 'datetime',
        'assigned_time' => 'datetime',
        'reserved_time' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the personnel that is assigned to this task.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'assigned_user_id');
    }

    /**
     * Get the prompts for this task.
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * Get the trainings for this task.
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
