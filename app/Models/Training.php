<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'training_content',
        'training_url',
    ];

    /**
     * Get the task that owns the training.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
