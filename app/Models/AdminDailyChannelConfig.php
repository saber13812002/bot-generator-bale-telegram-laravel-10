<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDailyChannelConfig extends Model
{
    use HasFactory;

    const CONTENT_TYPE_VERSE = 'verse';
    const CONTENT_TYPE_HADITH = 'hadith';
    const CONTENT_TYPE_NAHJ = 'nahj';
    const CONTENT_TYPE_SHARABE_BEHESHTI = 'sharabe_beheshti';
    const CONTENT_TYPE_MIXED = 'mixed';

    protected $fillable = [
        'admin_chat_id',
        'content_type',
        'bale_channel_chat_id',
        'telegram_channel_chat_id',
        'eitaa_channel_chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hasBale(): bool
    {
        return !empty($this->bale_channel_chat_id);
    }

    public function hasTelegram(): bool
    {
        return !empty($this->telegram_channel_chat_id);
    }

    public function hasEitaa(): bool
    {
        return !empty($this->eitaa_channel_chat_id);
    }
}
