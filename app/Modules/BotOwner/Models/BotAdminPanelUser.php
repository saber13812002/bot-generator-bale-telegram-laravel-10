<?php

namespace App\Modules\BotOwner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAdminPanelUser extends Model
{
    protected $fillable = [
        'bot_owner_id',
        'bot_id',
        'added_by_owner_id',
    ];

    public function botOwner(): BelongsTo
    {
        return $this->belongsTo(BotOwner::class, 'bot_owner_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Bot::class, 'bot_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(BotOwner::class, 'added_by_owner_id');
    }
}
