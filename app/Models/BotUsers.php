<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotUsers extends Model
{
    use HasFactory;


    protected $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
        'email_verification_code_expires_at' => 'datetime',
        'last_email_report_sent_at' => 'datetime',
        'location_set_at' => 'datetime',
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
            'mp3_reciter' => $this->setting('mp3_reciter'),

            'quran_text_or_simple' => $this->setting('quran_text_or_simple'),
            'quran_translation' => $this->setting('quran_translation'),

            'quran_transliteration_tr' => $this->setting('quran_transliteration_tr'),
            'quran_transliteration_en' => $this->setting('quran_transliteration_en'),
        ]);
    }

    // TODO: use first or Create laravel
    public static function firstOrNew(string $chat_id, $botMotherId, $origin): Model|bool|BotUsers
    {
        $user = BotUsers::whereChatId($chat_id)->where('origin', $origin)->first();

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

    /**
     * Relationship: کاربری که این کاربر را دعوت کرده است
     */
    public function inviter()
    {
        return $this->belongsTo(BotUsers::class, 'invited_by', 'chat_id')
            ->where('origin', $this->origin);
    }

    /**
     * Relationship: کاربرانی که این کاربر دعوت کرده است
     */
    public function invitees()
    {
        return $this->hasMany(BotUsers::class, 'invited_by', 'chat_id')
            ->where('origin', $this->origin);
    }

    /**
     * Relationship: Weather Alerts
     */
    public function weatherAlerts()
    {
        return $this->hasMany(WeatherAlert::class, 'bot_user_id');
    }

    /**
     * Relationship: Pro Subscriptions
     */
    public function proSubscriptions()
    {
        return $this->hasMany(ProUser::class, 'bot_user_id');
    }

    /**
     * دریافت location
     */
    public function getLocation(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'address' => $this->location_address,
            ];
        }
        return null;
    }

    /**
     * ذخیره location
     */
    public function setLocation(float $lat, float $lng, ?string $address = null): self
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->location_address = $address;
        $this->location_set_at = now();
        $this->save();
        return $this;
    }

    /**
     * بررسی وجود location
     */
    public function hasLocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * بررسی Pro بودن برای یک ربات
     */
    public function isPro(?int $botId = null): bool
    {
        $query = $this->proSubscriptions()
            ->where('status', 'active');
        
        if ($botId) {
            $query->where('bot_id', $botId);
        }
        
        $pro = $query->first();
        
        if (!$pro) {
            return false;
        }
        
        // بررسی انقضا
        if ($pro->expires_at && $pro->expires_at->isPast()) {
            $pro->status = 'expired';
            $pro->save();
            return false;
        }
        
        return true;
    }

    /**
     * تعداد alerts فعال برای یک ربات
     */
    public function getActiveAlertsCount(?int $botId = null): int
    {
        $query = $this->weatherAlerts()->where('is_active', true);
        
        if ($botId) {
            $query->where('bot_id', $botId);
        }
        
        return $query->count();
    }
}
