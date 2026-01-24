<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'alert_type',
        'comparison_type',
        'threshold_value',
        'time_hour',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:2',
        'time_hour' => 'integer',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Relationship: Bot User
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    /**
     * Relationship: Bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    /**
     * Scope: Active alerts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: For specific bot
     */
    public function scopeForBot($query, int $botId)
    {
        return $query->where('bot_id', $botId);
    }

    /**
     * چک کردن و trigger کردن alert
     */
    public function checkAndTrigger(array $currentWeather, ?array $previousWeather = null): bool
    {
        // این متد در WeatherAlertService پیاده‌سازی می‌شود
        return false;
    }
}
