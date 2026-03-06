<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSubmissionBotConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'channel_chat_id',
        'group_chat_id',
        'required_approvals',
        'origin',
    ];

    protected $casts = [
        'channel_chat_id' => 'integer',
        'group_chat_id' => 'integer',
        'required_approvals' => 'integer',
    ];

    /**
     * Get the bot that owns this config.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Whether approval group is configured.
     */
    public function hasApprovalGroup(): bool
    {
        return $this->group_chat_id !== null && $this->required_approvals > 0;
    }
}
