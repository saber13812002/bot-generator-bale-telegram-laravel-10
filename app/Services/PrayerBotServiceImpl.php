<?php

namespace App\Services;

use App\Interfaces\Services\PrayerBotService;
use App\Interfaces\Repositories\PrayerRecordRepository;
use App\Interfaces\Repositories\PrayerEstimateRepository;
use App\Models\PrayerRecord;
use App\Models\PrayerEstimate;
use App\Models\BotUsers;
use App\Models\BotUserState;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PrayerBotServiceImpl implements PrayerBotService
{
    protected PrayerRecordRepository $prayerRecordRepository;
    protected PrayerEstimateRepository $prayerEstimateRepository;

    public function __construct(
        PrayerRecordRepository $prayerRecordRepository,
        PrayerEstimateRepository $prayerEstimateRepository
    ) {
        $this->prayerRecordRepository = $prayerRecordRepository;
        $this->prayerEstimateRepository = $prayerEstimateRepository;
    }

    /**
     * ثبت رکعات نماز
     */
    public function recordPrayer(
        int $chatId,
        int $rakats,
        string $origin,
        ?int $botId = null,
        ?int $messageId = null,
        ?string $textContext = null
    ): PrayerRecord {
        Log::info('PrayerBotService - Recording prayer', [
            'chat_id' => $chatId,
            'rakats' => $rakats,
            'origin' => $origin
        ]);

        try {
            // دریافت یا ایجاد کاربر
            $user = BotUsers::firstOrNew($chatId, $botId ?? 1, $origin);

            // تشخیص نوع نماز
            $prayerType = $this->detectPrayerType($rakats, $textContext);
            $detectionMethod = $this->determineDetectionMethod($textContext);

            // ایجاد رکورد
            $data = [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'rakats' => $rakats,
                'prayer_type' => $prayerType,
                'detection_method' => $detectionMethod,
                'origin' => $origin,
                'message_id' => $messageId,
            ];

            $record = $this->prayerRecordRepository->create($data);

            Log::info('PrayerBotService - Prayer recorded successfully', [
                'record_id' => $record->id,
                'prayer_type' => $prayerType
            ]);

            return $record;
        } catch (Exception $e) {
            Log::error('PrayerBotService - Error recording prayer', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            throw $e;
        }
    }

    /**
     * حذف رکعات ثبت شده
     */
    public function removePrayer(int $recordId, int $chatId, string $origin): bool
    {
        Log::info('PrayerBotService - Removing prayer', [
            'record_id' => $recordId,
            'chat_id' => $chatId
        ]);

        return $this->prayerRecordRepository->deleteById($recordId, $chatId, $origin);
    }

    /**
     * دریافت آمار کاربر
     */
    public function getUserStats(int $chatId, string $origin): array
    {
        Log::info('PrayerBotService - Getting user stats', [
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        $stats = $this->prayerRecordRepository->getUserStats($chatId, $origin);
        $statsByType = $this->prayerRecordRepository->getStatsByPrayerType($chatId, $origin);

        return array_merge($stats, ['by_prayer_type' => $statsByType]);
    }

    /**
     * تولید پیام آمار
     */
    public function generateStatsMessage(int $chatId, string $origin): string
    {
        $stats = $this->getUserStats($chatId, $origin);

        $message = "📊 " . trans('bot.weekly_stats') . "\n\n";
        $message .= "🔢 " . trans('bot.total_rakats') . ": " . $stats['total_rakats'] . "\n";
        $message .= "📝 " . trans('bot.total_records') . ": " . $stats['total_records'] . "\n\n";

        $message .= "📅 " . trans('bot.last_week') . ":\n";
        $message .= "🔢 " . $stats['last_week_rakats'] . " " . trans('bot.rakats') . "\n";
        $message .= "📝 " . $stats['last_week_records'] . " " . trans('bot.records') . "\n\n";

        // آمار به تفکیک نماز
        if (!empty($stats['by_prayer_type'])) {
            $message .= "📋 " . trans('bot.by_prayer_type') . ":\n";
            
            $prayerNames = [
                'fajr' => trans('bot.fajr'),
                'dhuhr' => trans('bot.dhuhr'),
                'asr' => trans('bot.asr'),
                'maghrib' => trans('bot.maghrib'),
                'isha' => trans('bot.isha'),
            ];

            foreach ($prayerNames as $type => $name) {
                if (isset($stats['by_prayer_type'][$type])) {
                    $typeStats = $stats['by_prayer_type'][$type];
                    $message .= "  • {$name}: {$typeStats['total_rakats']} " . trans('bot.rakats') . "\n";
                }
            }
        }

        // پیشرفت نسبت به تخمین
        $progress = $this->getProgress($chatId, $origin);
        if ($progress) {
            $message .= "\n" . $this->generateProgressMessage($chatId, $origin);
        }

        return $message;
    }

    /**
     * ثبت یا به‌روزرسانی تخمین نماز قضا
     */
    public function setEstimate(int $chatId, int $totalMissedPrayers, ?string $notes = null): PrayerEstimate
    {
        Log::info('PrayerBotService - Setting estimate', [
            'chat_id' => $chatId,
            'total_missed_prayers' => $totalMissedPrayers
        ]);

        try {
            // محاسبه تعداد رکعات بر اساس تعداد نماز
            // میانگین: (2+4+4+3+4) / 5 = 3.4 رکعت به ازای هر نماز
            $totalMissedRakats = (int) ceil($totalMissedPrayers * 3.4);

            $data = [
                'chat_id' => $chatId,
                'total_missed_prayers' => $totalMissedPrayers,
                'total_missed_rakats' => $totalMissedRakats,
                'start_date' => now()->toDateString(),
                'notes' => $notes,
            ];

            $estimate = $this->prayerEstimateRepository->createOrUpdate($data);

            Log::info('PrayerBotService - Estimate set successfully', [
                'chat_id' => $chatId,
                'estimate_id' => $estimate->id
            ]);

            return $estimate;
        } catch (Exception $e) {
            Log::error('PrayerBotService - Error setting estimate', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            throw $e;
        }
    }

    /**
     * دریافت پیشرفت کاربر نسبت به تخمین
     */
    public function getProgress(int $chatId, string $origin): ?array
    {
        return $this->prayerEstimateRepository->getProgress($chatId, $origin);
    }

    /**
     * تولید پیام پیشرفت
     */
    public function generateProgressMessage(int $chatId, string $origin): ?string
    {
        $progress = $this->getProgress($chatId, $origin);

        if (!$progress) {
            return null;
        }

        $message = "📈 " . trans('bot.progress') . ":\n";
        $message .= "🎯 " . trans('bot.goal') . ": " . $progress['total_missed_rakats'] . " " . trans('bot.rakats') . "\n";
        $message .= "✅ " . trans('bot.completed') . ": " . ($progress['total_missed_rakats'] - $progress['remaining_rakats']) . " " . trans('bot.rakats') . "\n";
        $message .= "⏳ " . trans('bot.remaining') . ": " . $progress['remaining_rakats'] . " " . trans('bot.rakats') . "\n";
        $message .= "📊 " . trans('bot.percentage') . ": " . $progress['progress_percentage'] . "%\n";

        // اضافه کردن پیام تشویقی
        if ($progress['progress_percentage'] >= 75) {
            $message .= "\n🎉 " . trans('bot.almost_done');
        } elseif ($progress['progress_percentage'] >= 50) {
            $message .= "\n💪 " . trans('bot.great_progress');
        } elseif ($progress['progress_percentage'] >= 25) {
            $message .= "\n✨ " . trans('bot.keep_going');
        } else {
            $message .= "\n🌟 " . trans('bot.good_start');
        }

        return $message;
    }

    /**
     * دریافت گزارش هفتگی کاربر
     */
    public function getWeeklyReport(int $chatId, string $origin): array
    {
        Log::info('PrayerBotService - Getting weekly report', [
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        $from = now()->subWeek()->startOfDay();
        $to = now()->endOfDay();

        $records = $this->prayerRecordRepository->getUserRecordsForPeriod($chatId, $origin, $from, $to);
        $statsByType = $this->prayerRecordRepository->getStatsByPrayerType($chatId, $origin, $from, $to);
        $progress = $this->getProgress($chatId, $origin);

        // دریافت اطلاعات تکمیلی
        $enhancementService = app(\App\Services\EmailReportEnhancementService::class);
        $dailyStats = $enhancementService->getDailyStatsLast7Days($chatId, $origin);
        $weeklyComparison = $enhancementService->getWeeklyComparison($chatId, $origin);
        $peakActivity = $enhancementService->findPeakActivity($chatId, $origin);
        $completionTime = $enhancementService->calculateCompletionTimeWithPeakSpeed($chatId, $origin, $peakActivity, $progress);
        $top10Users = $enhancementService->getTop10Users($chatId, $origin);
        $motivationalMessage = $enhancementService->generateMotivationalMessage(
            [
                'total_prayers' => $records->count(),
                'total_rakats' => $records->sum('rakats'),
                'progress_percentage' => $progress['progress_percentage'] ?? 0,
            ],
            $weeklyComparison,
            $peakActivity,
            $completionTime,
            $top10Users
        );

        return [
            'records' => $records,
            'stats_by_type' => $statsByType,
            'progress' => $progress,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            // اطلاعات تکمیلی
            'daily_stats' => $dailyStats,
            'weekly_comparison' => $weeklyComparison,
            'peak_activity' => $peakActivity,
            'completion_time' => $completionTime,
            'top_10_users' => $top10Users,
            'motivational_message' => $motivationalMessage,
        ];
    }

    /**
     * تشخیص هوشمند نوع نماز
     */
    protected function detectPrayerType(int $rakats, ?string $textContext = null): string
    {
        // اگر متن داریم، ابتدا از آن استفاده می‌کنیم
        if ($textContext) {
            $detectedFromText = $this->detectPrayerTypeFromText($textContext);
            if ($detectedFromText !== 'optional') {
                return $detectedFromText;
            }
        }

        // تشخیص بر اساس تعداد رکعات
        if ($rakats == 2) {
            return 'fajr'; // صبح
        } elseif ($rakats == 3) {
            return 'maghrib'; // مغرب
        } elseif ($rakats == 4) {
            // بررسی زمان روز برای تشخیص ظهر، عصر یا عشا
            $hour = now()->hour;
            
            if ($hour >= 6 && $hour < 13) {
                return 'dhuhr'; // ظهر
            } elseif ($hour >= 13 && $hour < 18) {
                return 'asr'; // عصر
            } else {
                return 'isha'; // عشا
            }
        }

        return 'optional'; // پیش‌فرض
    }

    /**
     * تشخیص نوع نماز از متن
     */
    protected function detectPrayerTypeFromText(string $text): string
    {
        $text = mb_strtolower($text);

        $keywords = [
            'fajr' => ['صبح', 'فجر', 'fajr', 'morning'],
            'dhuhr' => ['ظهر', 'dhuhr', 'noon'],
            'asr' => ['عصر', 'asr', 'afternoon'],
            'maghrib' => ['مغرب', 'maghrib', 'sunset'],
            'isha' => ['عشا', 'isha', 'night'],
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
     * تعیین روش تشخیص
     */
    protected function determineDetectionMethod(?string $textContext): string
    {
        if (!$textContext) {
            return 'auto';
        }

        // اگر متن شامل دستور بود
        if (str_starts_with($textContext, '/')) {
            return 'command';
        }

        // اگر متن شامل کلمات کلیدی نماز بود
        if ($this->detectPrayerTypeFromText($textContext) !== 'optional') {
            return 'manual';
        }

        return 'auto';
    }

    /**
     * ست کردن state برای کاربر
     */
    public function setState(
        int $botUserId,
        int $botMotherId,
        string $state,
        ?array $data = null,
        int $expiresInMinutes = 10
    ) {
        // پاک کردن state قبلی
        BotUserState::where('bot_user_id', $botUserId)->delete();
        
        // ایجاد state جدید
        return BotUserState::create([
            'bot_user_id' => $botUserId,
            'bot_mother_id' => $botMotherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    /**
     * دریافت state کاربر
     */
    public function getState(int $botUserId, ?string $state = null)
    {
        $query = BotUserState::where('bot_user_id', $botUserId)->active();
        
        if ($state) {
            $query->where('state', $state);
        }
        
        return $query->latest()->first();
    }

    /**
     * پاک کردن state کاربر
     */
    public function clearState(int $botUserId, ?string $state = null): bool
    {
        $query = BotUserState::where('bot_user_id', $botUserId);
        
        if ($state) {
            $query->where('state', $state);
        }
        
        return $query->delete() > 0;
    }

    /**
     * پاک کردن state های منقضی شده
     */
    public function clearExpiredStates(): int
    {
        return BotUserState::expired()->delete();
    }

    /**
     * تبدیل مقدار به رکعت بر اساس واحد
     */
    public function convertToRakats(int $value, string $unit): int
    {
        return match($unit) {
            'day' => $value * 17,        // 5 نماز × 17 رکعت در روز
            'week' => $value * 7 * 17,   // 7 روز × 17 رکعت
            'month' => $value * 30 * 17, // 30 روز × 17 رکعت
            'year' => $value * 365 * 17, // 365 روز × 17 رکعت
            'rakat' => $value,           // مستقیم رکعت
            default => 0
        };
    }

    /**
     * محاسبه معادل‌های مختلف برای تعداد رکعت
     */
    public function calculateEquivalents(int $rakats): array
    {
        return [
            'days' => round($rakats / 17, 1),
            'weeks' => round($rakats / (7 * 17), 1),
            'months' => round($rakats / (30 * 17), 1),
            'years' => round($rakats / (365 * 17), 2),
        ];
    }
}
