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
    const CONTENT_TYPE_SEQUENTIAL = 'sequential';

    /** ترتیب نوبت برای نوع ترتیبی */
    const SEQUENTIAL_ORDER = ['verse', 'hadith', 'nahj', 'sharabe_beheshti'];

    /** تعداد ارسال در روز: فقط ۱، ۲ یا ۴ */
    const POSTS_PER_DAY_ONE = 1;
    const POSTS_PER_DAY_TWO = 2;
    const POSTS_PER_DAY_FOUR = 4;

    protected $fillable = [
        'admin_chat_id',
        'content_type',
        'last_sent_content_type',
        'posts_per_day',
        'bale_channel_chat_id',
        'telegram_channel_chat_id',
        'eitaa_channel_chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'posts_per_day' => 'integer',
    ];

    /**
     * آیا این config در اسلات داده‌شده (۱–۴) باید ارسال کند؟
     * اسلات ۱=۰۰:۰۰، ۲=۰۶:۰۰، ۳=۱۲:۰۰، ۴=۱۸:۰۰
     */
    public function shouldRunInSlot(int $slot): bool
    {
        $ppd = (int) ($this->posts_per_day ?? 1);
        if ($ppd === 1) {
            return $slot === 2; // فقط اسلات ۲ (۰۶:۰۰)
        }
        if ($ppd === 2) {
            return $slot === 2 || $slot === 4; // ۰۶:۰۰ و ۱۸:۰۰
        }
        if ($ppd === 4) {
            return true;
        }
        return $slot === 2;
    }

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
