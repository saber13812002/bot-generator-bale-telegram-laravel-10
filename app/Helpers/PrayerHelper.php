<?php

namespace App\Helpers;

use App\Models\PrayerRecord;
use App\Models\PrayerEstimate;
use Illuminate\Support\Facades\Log;

class PrayerHelper
{
    /**
     * محاسبه پیشرفت نسبت به تخمین
     * 
     * @param int $chatId
     * @param string $origin
     * @return array|null
     */
    public static function calculateProgress(int $chatId, string $origin): ?array
    {
        Log::info('PrayerHelper - Calculating progress', [
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        $estimate = PrayerEstimate::where('chat_id', $chatId)->first();

        if (!$estimate) {
            return null;
        }

        $totalCompleted = PrayerRecord::where('chat_id', $chatId)
            ->where('origin', $origin)
            ->sum('rakats');

        $remaining = max(0, $estimate->total_missed_rakats - $totalCompleted);
        $percentage = $estimate->total_missed_rakats > 0 
            ? min(100, round(($totalCompleted / $estimate->total_missed_rakats) * 100, 2))
            : 0;

        return [
            'total_goal' => $estimate->total_missed_rakats,
            'completed' => $totalCompleted,
            'remaining' => $remaining,
            'percentage' => $percentage,
        ];
    }

    /**
     * تشخیص هوشمند نوع نماز بر اساس زمان و تعداد رکعات
     * 
     * @param int $rakats
     * @param string|null $text
     * @return string
     */
    public static function detectPrayerType(int $rakats, ?string $text = null): string
    {
        // اگر متن داریم، ابتدا از آن استفاده می‌کنیم
        if ($text) {
            $detectedFromText = self::detectPrayerTypeFromText($text);
            if ($detectedFromText !== 'optional') {
                return $detectedFromText;
            }
        }

        // تشخیص بر اساس تعداد رکعات
        if ($rakats == 2) {
            return 'fajr'; // صبح - همیشه 2 رکعت
        } elseif ($rakats == 3) {
            return 'maghrib'; // مغرب - همیشه 3 رکعت
        } elseif ($rakats == 4) {
            // بررسی زمان روز برای تشخیص ظهر، عصر یا عشا
            $hour = now()->hour;
            
            // ساعت 6 صبح تا 1 بعدازظهر: ظهر
            if ($hour >= 6 && $hour < 13) {
                return 'dhuhr';
            }
            // ساعت 1 بعدازظهر تا 6 عصر: عصر
            elseif ($hour >= 13 && $hour < 18) {
                return 'asr';
            }
            // بقیه اوقات: عشا
            else {
                return 'isha';
            }
        }

        return 'optional'; // پیش‌فرض
    }

    /**
     * تشخیص نوع نماز از متن (کلمات کلیدی)
     * 
     * @param string $text
     * @return string
     */
    public static function detectPrayerTypeFromText(string $text): string
    {
        $text = mb_strtolower($text);

        // کلمات کلیدی برای هر نماز
        $keywords = [
            'fajr' => ['صبح', 'فجر', 'sobh', 'fajr', 'morning'],
            'dhuhr' => ['ظهر', 'zohr', 'dhuhr', 'noon'],
            'asr' => ['عصر', 'asr', 'afternoon'],
            'maghrib' => ['مغرب', 'maghreb', 'maghrib', 'sunset'],
            'isha' => ['عشا', 'isha', 'night', 'evening'],
        ];

        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $type;
                }
            }
        }

        return 'optional';
    }

    /**
     * تولید پیام آمار
     * 
     * @param int $chatId
     * @param string $origin
     * @return string
     */
    public static function generateStatsMessage(int $chatId, string $origin): string
    {
        $totalRecords = PrayerRecord::where('chat_id', $chatId)
            ->where('origin', $origin)
            ->count();

        $totalRakats = PrayerRecord::where('chat_id', $chatId)
            ->where('origin', $origin)
            ->sum('rakats');

        $lastWeekRakats = PrayerRecord::where('chat_id', $chatId)
            ->where('origin', $origin)
            ->where('created_at', '>=', now()->subWeek())
            ->sum('rakats');

        $message = "📊 " . trans('bot.weekly_stats') . "\n\n";
        $message .= "🔢 " . trans('bot.total_rakats') . ": {$totalRakats}\n";
        $message .= "📝 " . trans('bot.total_records') . ": {$totalRecords}\n";
        $message .= "📅 " . trans('bot.last_week') . ": {$lastWeekRakats} " . trans('bot.rakats') . "\n\n";

        // آمار به تفکیک نماز
        $stats = self::getStatsByPrayerType($chatId, $origin);
        if (!empty($stats)) {
            $message .= "📋 " . trans('bot.by_prayer_type') . ":\n";
            foreach ($stats as $type => $count) {
                $prayerName = trans("bot.{$type}");
                $message .= "  • {$prayerName}: {$count} " . trans('bot.rakats') . "\n";
            }
        }

        // پیشرفت نسبت به تخمین
        $progress = self::calculateProgress($chatId, $origin);
        if ($progress) {
            $message .= "\n📈 " . trans('bot.progress') . ":\n";
            $message .= "🎯 " . trans('bot.goal') . ": {$progress['total_goal']} " . trans('bot.rakats') . "\n";
            $message .= "✅ " . trans('bot.completed') . ": {$progress['completed']} " . trans('bot.rakats') . "\n";
            $message .= "⏳ " . trans('bot.remaining') . ": {$progress['remaining']} " . trans('bot.rakats') . "\n";
            $message .= "📊 {$progress['percentage']}%\n";
        }

        return $message;
    }

    /**
     * دریافت آمار به تفکیک نوع نماز
     * 
     * @param int $chatId
     * @param string $origin
     * @return array
     */
    protected static function getStatsByPrayerType(int $chatId, string $origin): array
    {
        $stats = PrayerRecord::where('chat_id', $chatId)
            ->where('origin', $origin)
            ->selectRaw('prayer_type, SUM(rakats) as total')
            ->groupBy('prayer_type')
            ->pluck('total', 'prayer_type')
            ->toArray();

        return $stats;
    }

    /**
     * تولید پیام تشویقی رندوم
     * 
     * @return string
     */
    public static function getRandomEncouragementMessage(): string
    {
        $messages = [
            trans('bot.encouragement_1'),
            trans('bot.encouragement_2'),
            trans('bot.encouragement_3'),
            trans('bot.encouragement_4'),
            trans('bot.encouragement_5'),
            trans('bot.encouragement_6'),
            trans('bot.encouragement_7'),
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * تشخیص عدد در متن
     * 
     * @param string $text
     * @return int|null
     */
    public static function detectNumberInText(string $text): ?int
    {
        // تشخیص اعداد انگلیسی
        if (preg_match('/\b([234])\b/', $text, $matches)) {
            return (int) $matches[1];
        }

        // تشخیص اعداد فارسی
        $persianNumbers = [
            '۲' => 2,
            '۳' => 3,
            '۴' => 4,
        ];

        foreach ($persianNumbers as $persian => $english) {
            if (str_contains($text, $persian)) {
                return $english;
            }
        }

        // تشخیص کلمات
        $words = [
            'دو' => 2, 'two' => 2,
            'سه' => 3, 'three' => 3,
            'چهار' => 4, 'four' => 4,
        ];

        $text = mb_strtolower($text);
        foreach ($words as $word => $number) {
            if (str_contains($text, $word)) {
                return $number;
            }
        }

        return null;
    }

    /**
     * محاسبه تعداد کل رکعات بر اساس تعداد نماز
     * 
     * @param int $totalPrayers
     * @return int
     */
    public static function calculateTotalRakatsFromPrayers(int $totalPrayers): int
    {
        // میانگین رکعات در روز: (2+4+4+3+4) = 17 رکعت
        // یعنی هر 5 نماز = 17 رکعت
        // یا به طور میانگین هر نماز = 3.4 رکعت
        return (int) ceil($totalPrayers * 3.4);
    }

    /**
     * بررسی اعتبار تعداد رکعات
     * 
     * @param int $rakats
     * @return bool
     */
    public static function isValidRakatCount(int $rakats): bool
    {
        return in_array($rakats, [2, 3, 4]);
    }
}
