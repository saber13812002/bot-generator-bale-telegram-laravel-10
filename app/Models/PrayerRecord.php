<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PrayerRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_id',
        'bot_id',
        'rakats',
        'prayer_type',
        'detection_method',
        'origin',
        'message_id',
    ];

    protected $casts = [
        'rakats' => 'integer',
        'chat_id' => 'integer',
        'message_id' => 'integer',
    ];

    /**
     * رابطه با کاربر ربات
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }

    /**
     * رابطه با ربات
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    /**
     * Scope: فیلتر بر اساس کاربر
     */
    public function scopeByUser(Builder $query, int $chatId, string $origin): Builder
    {
        return $query->where('chat_id', $chatId)->where('origin', $origin);
    }

    /**
     * Scope: رکوردهای هفته گذشته
     */
    public function scopeLastWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->subWeek()->startOfDay(),
            now()->endOfDay()
        ]);
    }

    /**
     * Scope: رکوردهای ماه گذشته
     */
    public function scopeLastMonth(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->subMonth()->startOfDay(),
            now()->endOfDay()
        ]);
    }

    /**
     * Scope: فیلتر بر اساس نوع نماز
     */
    public function scopeByPrayerType(Builder $query, string $type): Builder
    {
        return $query->where('prayer_type', $type);
    }

    /**
     * Scope: رکوردهای یک دوره زمانی خاص
     */
    public function scopeByPeriod(Builder $query, \Carbon\Carbon $from, \Carbon\Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
