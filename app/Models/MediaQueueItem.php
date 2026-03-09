<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaQueueItem extends Model
{
    use HasFactory;

    const TYPE_TEXT = 'text';
    const TYPE_PHOTO = 'photo';
    const TYPE_VIDEO = 'video';

    protected $fillable = [
        'media_queue_id',
        'position',
        'content_type',
        'content_text',
        'file_id_telegram',
        'file_id_bale',
    ];

    public function mediaQueue(): BelongsTo
    {
        return $this->belongsTo(MediaQueue::class);
    }
}
