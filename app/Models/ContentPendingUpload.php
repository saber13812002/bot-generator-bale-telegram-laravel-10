<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPendingUpload extends Model
{
    protected $fillable = [
        'bot_id',
        'file_id',
        'file_unique_id',
        'origin',
        'uploaded_by_chat_id',
        'mime_type',
    ];
}
