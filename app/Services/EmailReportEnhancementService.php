<?php

namespace App\Services;

use App\Models\PrayerRecord;
use App\Models\BotUsers;
use App\Interfaces\Repositories\PrayerRecordRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmailReportEnhancementService
{
    protected PrayerRecordRepository $prayerRecordRepository;

    public function __construct(PrayerRecordRepository $prayerRecordRepository)
    {
        $this->prayerRecordRepository = $prayerRecordRepository;
    }

    /**
     * دریافت آمار روزانه 7 روز گذشته
     */
    public function getDailyStatsLast7Days(int $chatId, string $origin): array
    {
        $stats = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = $date->copy()->endOfDay();
            
            $records = $this->prayerRecordRepository->getUserRecordsForPeriod(
                $chatId,
                $origin,
                $date,
                $nextDate
            );
            
            $stats[] = [
                'date' => $date->format('Y-m-d'),
                'date_label' => $date->format('d M'),
                'day_name' => $this->getDayName($date),
                'rakats' => $records->sum('rakats'),
                'count' => $records->count(),
            ];
        }
        
        return $stats;
    }

    /**
     * مقایسه با هفته‌های گذشته
     */
    public function getWeeklyComparison(int $chatId, string $origin): array
    {
        $comparison = [];
        
        // هفته جاری
        $currentWeekStart = now()->startOfWeek();
        $currentWeekEnd = now()->endOfWeek();
        $currentWeekRecords = $this->prayerRecordRepository->getUserRecordsForPeriod(
            $chatId,
            $origin,
            $currentWeekStart,
            $currentWeekEnd
        );
        
        $comparison['current'] = [
            'rakats' => $currentWeekRecords->sum('rakats'),
            'count' => $currentWeekRecords->count(),
        ];
        
        // هفته گذشته
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();
        $lastWeekRecords = $this->prayerRecordRepository->getUserRecordsForPeriod(
            $chatId,
            $origin,
            $lastWeekStart,
            $lastWeekEnd
        );
        
        $comparison['last_week'] = [
            'rakats' => $lastWeekRecords->sum('rakats'),
            'count' => $lastWeekRecords->count(),
        ];
        
        // هفته قبل از آن
        $twoWeeksAgoStart = now()->subWeeks(2)->startOfWeek();
        $twoWeeksAgoEnd = now()->subWeeks(2)->endOfWeek();
        $twoWeeksAgoRecords = $this->prayerRecordRepository->getUserRecordsForPeriod(
            $chatId,
            $origin,
            $twoWeeksAgoStart,
            $twoWeeksAgoEnd
        );
        
        $comparison['two_weeks_ago'] = [
            'rakats' => $twoWeeksAgoRecords->sum('rakats'),
            'count' => $twoWeeksAgoRecords->count(),
        ];
        
        // محاسبه تغییرات
        $comparison['change_vs_last_week'] = $comparison['current']['rakats'] - $comparison['last_week']['rakats'];
        $comparison['change_percentage'] = $comparison['last_week']['rakats'] > 0 
            ? round(($comparison['change_vs_last_week'] / $comparison['last_week']['rakats']) * 100, 1)
            : ($comparison['current']['rakats'] > 0 ? 100 : 0);
        
        return $comparison;
    }

    /**
     * پیدا کردن پیک فعالیت (بهترین ماه/هفته)
     */
    public function findPeakActivity(int $chatId, string $origin): ?array
    {
        // بررسی 6 ماه گذشته
        $peakMonth = null;
        $maxRakats = 0;
        
        for ($i = 1; $i <= 6; $i++) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            
            $records = $this->prayerRecordRepository->getUserRecordsForPeriod(
                $chatId,
                $origin,
                $monthStart,
                $monthEnd
            );
            
            $rakats = $records->sum('rakats');
            
            if ($rakats > $maxRakats) {
                $maxRakats = $rakats;
                $peakMonth = [
                    'month' => $monthStart->format('Y-m'),
                    'month_label' => $monthStart->format('F Y'),
                    'rakats' => $rakats,
                    'count' => $records->count(),
                    'months_ago' => $i,
                ];
            }
        }
        
        return $peakMonth;
    }

    /**
     * محاسبه زمان تکمیل با سرعت پیک
     */
    public function calculateCompletionTimeWithPeakSpeed(int $chatId, string $origin, ?array $peakActivity, ?array $progress): ?array
    {
        if (!$peakActivity || !$progress || !isset($progress['remaining_rakats'])) {
            return null;
        }
        
        $remainingRakats = $progress['remaining_rakats'] ?? 0;
        if ($remainingRakats <= 0) {
            return null;
        }
        
        // محاسبه میانگین روزانه در ماه پیک
        $daysInPeakMonth = Carbon::parse($peakActivity['month'] . '-01')->daysInMonth;
        $dailyAverage = $peakActivity['rakats'] / $daysInPeakMonth;
        
        if ($dailyAverage <= 0) {
            return null;
        }
        
        // محاسبه تعداد روزهای لازم
        $daysNeeded = ceil($remainingRakats / $dailyAverage);
        $monthsNeeded = round($daysNeeded / 30, 1);
        $yearsNeeded = round($daysNeeded / 365, 1);
        
        return [
            'days' => $daysNeeded,
            'months' => $monthsNeeded,
            'years' => $yearsNeeded,
            'daily_average' => round($dailyAverage, 1),
            'peak_month_rakats' => $peakActivity['rakats'],
        ];
    }

    /**
     * دریافت Top 10 کاربران (بدون ذکر نام)
     */
    public function getTop10Users(int $excludeChatId, string $origin): array
    {
        // محاسبه آمار هفته گذشته برای همه کاربران
        $lastWeekStart = now()->subWeek()->startOfDay();
        $lastWeekEnd = now()->endOfDay();
        
        $topUsers = PrayerRecord::where('origin', $origin)
            ->where('chat_id', '!=', $excludeChatId)
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('chat_id, SUM(rakats) as total_rakats, COUNT(*) as total_count')
            ->groupBy('chat_id')
            ->orderBy('total_rakats', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'rakats' => (int) $item->total_rakats,
                    'count' => (int) $item->total_count,
                ];
            })
            ->toArray();
        
        return $topUsers;
    }

    /**
     * تولید متن انگیزشی
     */
    public function generateMotivationalMessage(array $reportData, ?array $weeklyComparison, ?array $peakActivity, ?array $completionTime, array $top10Users): string
    {
        $messages = [];
        
        // اگر هیچ فعالیتی نداشته
        if (($reportData['total_prayers'] ?? 0) == 0) {
            $messages[] = "🌟 این هفته فعالیتی ثبت نشده است، اما هر روز یک فرصت جدید است!";
            $messages[] = "💪 از همین امروز شروع کنید و اولین قدم را بردارید.";
            $messages[] = "✨ به یاد داشته باشید: سفر هزار مایل با یک قدم شروع می‌شود.";
            
            // اضافه کردن آمار Top 10
            if (!empty($top10Users)) {
                $topRakats = $top10Users[0]['rakats'] ?? 0;
                if ($topRakats > 0) {
                    $messages[] = "\n📊 کاربران دیگر این هفته تا {$topRakats} رکعت ثبت کرده‌اند.";
                    $messages[] = "🎯 شما هم می‌توانید به این موفقیت برسید!";
                }
            }
            
            return implode("\n\n", $messages);
        }
        
        // پیام بر اساس پیشرفت
        $progressPercentage = $reportData['progress_percentage'] ?? 0;
        $totalPrayers = $reportData['total_prayers'] ?? 0;
        $totalRakats = $reportData['total_rakats'] ?? 0;
        
        if ($progressPercentage >= 75) {
            $messages[] = "🎉 عالی! شما نزدیک به هدف هستید!";
            $messages[] = "💪 فقط چند قدم دیگر باقی مانده است. ادامه دهید!";
        } elseif ($progressPercentage >= 50) {
            $messages[] = "🌟 پیشرفت عالی! شما نیمی از راه را پیموده‌اید!";
            $messages[] = "💚 با همین سرعت، به زودی به هدف می‌رسید.";
        } elseif ($progressPercentage >= 25) {
            $messages[] = "✨ خوب پیش می‌روید! هر قدم شما ارزشمند است.";
            $messages[] = "📈 با ادامه این مسیر، موفقیت در انتظار شماست.";
        } else {
            $messages[] = "🌱 شروع خوبی داشته‌اید!";
            $messages[] = "💪 هر نماز قضایی که می‌خوانید، شما را به هدف نزدیک‌تر می‌کند.";
        }
        
        // پیام بر اساس مقایسه با هفته گذشته
        if ($weeklyComparison && isset($weeklyComparison['change_vs_last_week'])) {
            $change = $weeklyComparison['change_vs_last_week'];
            if ($change > 0) {
                $messages[] = "\n📈 عالی! این هفته {$change} رکعت بیشتر از هفته گذشته ثبت کردید!";
                $messages[] = "🎯 این نشان می‌دهد که در مسیر درستی هستید.";
            } elseif ($change < 0) {
                $messages[] = "\n💪 این هفته کمی کمتر از هفته گذشته بود، اما مهم این است که ادامه دهید!";
            } else {
                $messages[] = "\n✅ این هفته همانند هفته گذشته بود. سعی کنید هفته آینده بیشتر باشید!";
            }
        }
        
        // پیام بر اساس پیک فعالیت
        if ($peakActivity && $completionTime) {
            $monthsAgo = $peakActivity['months_ago'];
            $peakRakats = $peakActivity['rakats'];
            $monthsNeeded = $completionTime['months'];
            
            $messages[] = "\n🔥 در {$monthsAgo} ماه پیش، شما {$peakRakats} رکعت در یک ماه ثبت کردید!";
            $messages[] = "⏱️ اگر با همان سرعت ادامه دهید، تقریباً {$monthsNeeded} ماه دیگر کار تمام می‌شود!";
            $messages[] = "💪 بیایید دوباره به آن سطح برسیم!";
        }
        
        // اضافه کردن آمار Top 10
        if (!empty($top10Users)) {
            $topRakats = $top10Users[0]['rakats'] ?? 0;
            $userRakats = $totalRakats;
            
            if ($topRakats > $userRakats) {
                $difference = $topRakats - $userRakats;
                $messages[] = "\n📊 کاربران برتر این هفته تا {$topRakats} رکعت ثبت کرده‌اند.";
                $messages[] = "🎯 شما {$userRakats} رکعت دارید. فقط {$difference} رکعت دیگر تا رسیدن به رتبه اول!";
            } else {
                $messages[] = "\n🏆 شما در بین برترین‌ها هستید!";
                $messages[] = "🌟 این موفقیت را حفظ کنید!";
            }
        }
        
        return implode("\n\n", $messages);
    }

    /**
     * دریافت نام روز هفته
     */
    protected function getDayName(Carbon $date): string
    {
        $days = [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنج‌شنبه',
            'Friday' => 'جمعه',
        ];
        
        return $days[$date->format('l')] ?? $date->format('l');
    }
}
