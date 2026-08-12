<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentNote extends Model
{
    protected $fillable = [
        'content_item_id',
        'bot_user_id',
        'type',
        'text',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }
}
