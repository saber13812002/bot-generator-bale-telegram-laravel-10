<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Messenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bale_channel_chat_id',
        'bale_admin_chat_id',
        'bale_bot_token',
        'bale_channel_invite_link',
        'telegram_channel_chat_id',
        'telegram_admin_chat_id',
        'telegram_bot_token',
        'telegram_channel_invite_link',
        'eitaa_channel_chat_id',
        'eitaa_admin_chat_id',
        'eitaa_bot_token',
        'eitaa_channel_invite_link',
    ];

    protected $casts = [
        'bale_channel_chat_id' => 'integer',
        'bale_admin_chat_id' => 'integer',
        'telegram_channel_chat_id' => 'integer',
        'telegram_admin_chat_id' => 'integer',
        'eitaa_channel_chat_id' => 'integer',
        'eitaa_admin_chat_id' => 'integer',
    ];

    public function scopeByTelegramAdminChatId($query, $chatId)
    {
        return $query->where('telegram_admin_chat_id', $chatId);
    }

    public function scopeByBaleAdminChatId($query, $chatId)
    {
        return $query->where('bale_admin_chat_id', $chatId);
    }

    public function hasTelegram(): bool
    {
        return !empty($this->telegram_bot_token) && !empty($this->telegram_channel_chat_id);
    }

    public function hasBale(): bool
    {
        return !empty($this->bale_bot_token) && !empty($this->bale_channel_chat_id);
    }

    public function hasEitaa(): bool
    {
        return !empty($this->eitaa_bot_token) && !empty($this->eitaa_channel_chat_id);
    }
}
