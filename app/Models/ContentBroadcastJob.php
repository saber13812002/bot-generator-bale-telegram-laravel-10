<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBroadcastJob extends Model
{
    protected $fillable = [
        'bot_id',
        'target_filter',
        'target_category_id',
        'message_text',
        'file_id',
        'file_type',
        'status',
        'sent_count',
        'failed_count',
        'created_by_chat_id',
    ];
}
