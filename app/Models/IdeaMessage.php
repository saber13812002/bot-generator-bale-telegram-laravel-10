<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaMessage extends Model
{
    protected $fillable = [
        'idea_id',
        'sender_type',
        'sender_name',
        'message',
        'attachment_type',
        'attachment_url',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function scopeUserMessages($query)
    {
        return $query->where('sender_type', 'user');
    }

    public function scopeAdminMessages($query)
    {
        return $query->where('sender_type', 'admin');
    }
}
