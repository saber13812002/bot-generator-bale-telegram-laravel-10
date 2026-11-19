<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentUserProgress extends Model
{
    protected $table = 'content_user_progress';

    protected $fillable = [
        'bot_user_id',
        'category_id',
        'bot_id',
        'last_position',
        'last_content_item_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function lastItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'last_content_item_id');
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }
}
