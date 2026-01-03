<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelMessageQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'personnel_id',
        'message_content',
        'status',
        'error_message',
    ];

    /**
     * Get the personnel that owns the message queue.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
