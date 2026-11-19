<?php

namespace App\Services;

use App\Models\BotUsers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmailSchedulingService
{
    /**
     * دریافت کاربران واجد شرایط برای دریافت ایمیل
     */
    public function getEligibleUsers(int $batchSize = 10): Collection
    {
        // کاربرانی که:
        // 1. ایمیل ست و verify شده دارند
        // 2. frequency آنها never نیست
        // 3. یک هفته از آخرین ایمیلشان گذشته (یا هیچ ایمیلی دریافت نکرده‌اند)
        
        $query = BotUsers::whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->where('email_report_frequency', '!=', 'never');

        // چک کردن زمان آخرین ایمیل
        $query->where(function($q) {
            $q->whereNull('last_email_report_sent_at')
              ->orWhere('last_email_report_sent_at', '<', now()->subWeek());
        });

        $users = $query->limit($batchSize)->get();

        Log::info('📊 [EmailSchedulingService] Eligible users found', [
            'count' => $users->count(),
            'batch_size' => $batchSize
        ]);

        return $users;
    }

    /**
     * زمان‌بندی ارسال ایمیل برای یک باکس از کاربران
     */
    public function scheduleBatchEmails(Collection $users, int $intervalMinutes = 30): void
    {
        if ($users->isEmpty()) {
            Log::info('ℹ️ [EmailSchedulingService] No users to schedule');
            return;
        }

        $scheduledCount = 0;
        $delay = 0; // تاخیر اولیه (ثانیه)

        foreach ($users as $user) {
            // محاسبه تاخیر برای هر کاربر
            // کاربر اول: بدون تاخیر
            // کاربر بعدی: با تاخیر intervalMinutes
            
            if ($scheduledCount > 0) {
                $delay += ($intervalMinutes * 60); // تبدیل به ثانیه
            }

            Log::info('📅 [EmailSchedulingService] Scheduling email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'delay_seconds' => $delay,
                'delay_minutes' => round($delay / 60, 2)
            ]);

            $scheduledCount++;
        }

        Log::info('✅ [EmailSchedulingService] Batch scheduled', [
            'users_count' => $users->count(),
            'total_delay_minutes' => round($delay / 60, 2),
            'interval_minutes' => $intervalMinutes
        ]);
    }

    /**
     * دریافت تعداد کاربران واجد شرایط
     */
    public function getEligibleUsersCount(): int
    {
        return BotUsers::whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->where('email_report_frequency', '!=', 'never')
            ->where(function($q) {
                $q->whereNull('last_email_report_sent_at')
                  ->orWhere('last_email_report_sent_at', '<', now()->subWeek());
            })
            ->count();
    }

    /**
     * دریافت اینتروال‌های مجاز از .env
     */
    public static function getAllowedIntervals(): array
    {
        $intervals = env('EMAIL_INTERVALS', '10,30,60');
        return array_map('intval', explode(',', $intervals));
    }

    /**
     * دریافت اندازه باکس از .env
     */
    public static function getBatchSize(): int
    {
        return (int) env('EMAIL_BATCH_SIZE', 10);
    }

    /**
     * دریافت اینتروال پیش‌فرض از .env
     */
    public static function getDefaultInterval(): int
    {
        return (int) env('EMAIL_INTERVAL_MINUTES', 30);
    }
}
