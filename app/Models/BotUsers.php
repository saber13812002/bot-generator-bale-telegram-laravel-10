<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotUsers extends Model
{
    use HasFactory;


    protected $casts = [
        'settings' => 'array'
    ];

    protected $guarded = [];

    public function settings(array $revisions): self
    {
        $this->settings = array_merge($this->settings ?? [], $revisions);
        $this->save();

        return $this;
    }


    public function setting(string $name, $default = null)
    {
        if (array_key_exists($name, $this->settings ?? [])) {
            return $this->settings[$name];
        }

        return $default;
    }


    public function getSocialLinksAttribute(): array
    {
        return array_filter([
            'personal' => $this->setting('social_personal'),
            'twitter' => $this->setting('social_twitter'),
            'facebook' => $this->setting('social_facebook'),
            'instagram' => $this->setting('social_instagram'),
            'linkedin' => $this->setting('social_linkedin'),
            'reddit' => $this->setting('social_reddit'),
        ]);
    }

    public function getQuranSettingAttribute(): array
    {
        return array_filter([
            'mp3_enable' => $this->setting('mp3_enable'),
            'mp3_base_url' => $this->setting('mp3_base_url'),

            'quran_text_or_simple' => $this->setting('quran_text_or_simple'),
            'quran_translation' => $this->setting('quran_translation'),

            'quran_transliteration_tr' => $this->setting('quran_transliteration_tr'),
            'quran_transliteration_en' => $this->setting('quran_transliteration_en'),
        ]);
    }

    public static function firstOrNew(string $chat_id, $botMotherId, $origin): Model|bool|BotUsers
    {
        $user = BotUsers::whereChatId($chat_id)->first();

        if ($user === null) {
            $user = new BotUsers([
                'chat_id' => $chat_id,
                'status' => 'active',
                'origin' => $origin,
//                'bot_id' => $botMotherId,
                'bot_id' => $botMotherId
            ]);
            $user->save();
        }

        return $user;
    }
}
