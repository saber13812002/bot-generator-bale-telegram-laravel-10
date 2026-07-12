<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Scope: فیلتر بر اساس webhook endpoint URI
     */
    public function scopeByWebhookUri($query, string $uri)
    {
        return $query->where('webhook_endpoint_uri', $uri);
    }

    /**
     * Scope: فیلتر بر اساس کد زبان
     */
    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Scope: فیلتر بر اساس پلتفرم (bale/telegram)
     */
    public function scopeByPlatform($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: فقط最近的活动
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * دریافت آمار کاربران گروه‌بندی شده بر اساس زبان و پلتفرم
     * برای webhook-quran-word
     */
    public static function getQuranStatsGrouped(int $botMotherId = 1, int $days = 30): array
    {
        return self::where('webhook_endpoint_uri', 'webhook-quran-word')
            ->where('bot_mother_id', $botMotherId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('language, type, COUNT(DISTINCT chat_id) as unique_users, COUNT(*) as total_requests, MAX(created_at) as last_activity')
            ->groupBy('language', 'type')
            ->orderByDesc('unique_users')
            ->get()
            ->toArray();
    }

    /**
     * دریافت آمار یک زبان خاص به تفکیک ربات
     * این متد بررسی می‌کند bot_id در لاگ‌ها به کدام ربات اشاره دارد
     */
    public static function getStatsByLanguageWithBots(string $language, string $platform, int $botMotherId = 1, int $days = 30): array
    {
        $logs = self::where('webhook_endpoint_uri', 'webhook-quran-word')
            ->where('bot_mother_id', $botMotherId)
            ->where('language', $language)
            ->where('type', $platform)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('bot_id, COUNT(DISTINCT chat_id) as unique_users, COUNT(*) as total_requests, MAX(created_at) as last_activity')
            ->groupBy('bot_id')
            ->orderByDesc('unique_users')
            ->get();

        $result = [];
        foreach ($logs as $log) {
            $botName = 'ناشناخته';
            if ($log['bot_id']) {
                $bot = Bot::find($log['bot_id']);
                if ($bot) {
                    $botName = $platform == 'telegram'
                        ? ($bot->telegram_bot_name ?? $bot->bale_bot_name ?? 'ناشناخته')
                        : ($bot->bale_bot_name ?? $bot->telegram_bot_name ?? 'ناشناخته');
                }
            }
            $result[] = [
                'bot_id' => $log['bot_id'],
                'bot_name' => $botName,
                'unique_users' => (int) $log['unique_users'],
                'total_requests' => (int) $log['total_requests'],
                'last_activity' => $log['last_activity'],
            ];
        }

        return $result;
    }

    /**
     * دریافت chat_id های کاربران یک زبان خاص
     * برای ارسال پیام همگانی
     */
    public static function getChatIdsByLanguage(string $language, string $platform, int $botMotherId = 1): array
    {
        return self::where('webhook_endpoint_uri', 'webhook-quran-word')
            ->where('bot_mother_id', $botMotherId)
            ->where('language', $language)
            ->where('type', $platform)
            ->distinct()
            ->pluck('chat_id')
            ->toArray();
    }

    /**
     * دریافت chat_id های کاربران یک زبان خاص به تفکیک bot_id
     * برای گزارش دقیق از هر ربات
     */
    public static function getChatIdsByLanguageAndBot(string $language, string $platform, int $botMotherId = 1): array
    {
        return self::where('webhook_endpoint_uri', 'webhook-quran-word')
            ->where('bot_mother_id', $botMotherId)
            ->where('language', $language)
            ->where('type', $platform)
            ->select('chat_id', 'bot_id')
            ->distinct()
            ->get()
            ->groupBy('bot_id')
            ->map(function ($items) {
                return $items->pluck('chat_id')->unique()->values()->toArray();
            })
            ->toArray();
    }
}
