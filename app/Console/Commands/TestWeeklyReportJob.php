<?php

namespace App\Console\Commands;

use App\Interfaces\Services\PrayerBotService;
use App\Jobs\SendPrayerReportEmailJob;
use App\Models\BotUsers;
use App\Models\EmailReportQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestWeeklyReportJob extends Command
{
    protected $signature = 'email:test-weekly-job 
                            {--user-id= : User ID for testing}
                            {--email= : Email address for testing}
                            {--force : Force send even if recently sent}';

    protected $description = 'تست Job ارسال گزارش هفتگی';

    protected PrayerBotService $prayerBotService;

    public function __construct(PrayerBotService $prayerBotService)
    {
        parent::__construct();
        $this->prayerBotService = $prayerBotService;
    }

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $email = $this->option('email');
        $force = $this->option('force');

        $this->info('🧪 تست Job ارسال گزارش هفتگی');
        $this->newLine();

        // پیدا کردن کاربر
        $user = null;
        if ($userId) {
            $user = BotUsers::find($userId);
            if (!$user) {
                $this->error("❌ کاربر با ID {$userId} پیدا نشد");
                return 1;
            }
        } elseif ($email) {
            $user = BotUsers::where('email', $email)->first();
            if (!$user) {
                $this->error("❌ کاربر با ایمیل {$email} پیدا نشد");
                return 1;
            }
        } else {
            // پیدا کردن اولین کاربر واجد شرایط
            $user = BotUsers::whereNotNull('email')
                ->whereNotNull('email_verified_at')
                ->where('email_report_frequency', '!=', 'never')
                ->first();

            if (!$user) {
                $this->error('❌ هیچ کاربر واجد شرایطی پیدا نشد');
                return 1;
            }
        }

        $this->info("👤 کاربر انتخاب شده:");
        $this->info("   ID: {$user->id}");
        $this->info("   Chat ID: {$user->chat_id}");
        $this->info("   Email: {$user->email}");
        $this->info("   Origin: {$user->origin}");
        $this->newLine();

        // چک کردن اینکه آیا اخیراً ایمیل دریافت کرده
        if (!$force && $user->last_email_report_sent_at) {
            $daysSinceLastEmail = now()->diffInDays($user->last_email_report_sent_at);
            if ($daysSinceLastEmail < 7) {
                $this->warn("⚠️  کاربر {$daysSinceLastEmail} روز پیش ایمیل دریافت کرده است");
                $this->info("💡 از --force استفاده کنید برای نادیده گرفتن");
                return 1;
            }
        }

        try {
            // دریافت گزارش هفتگی
            $this->info('📊 دریافت گزارش هفتگی...');
            $rawReportData = $this->prayerBotService->getWeeklyReport($user->chat_id, $user->origin);
            
            // اضافه کردن URL گزارش وب
            if ($user->web_report_token) {
                $rawReportData['report_url'] = url("/namaz-ghaza/{$user->web_report_token}");
            }
            
            // تبدیل به ساختار موردنیاز
            $reportData = $this->transformReportData($rawReportData);
            
            $this->info('✅ گزارش دریافت شد');
            $this->info("   تعداد نمازها: {$reportData['total_prayers']}");
            $this->info("   تعداد رکعات: {$reportData['total_rakats']}");
            $this->info("   پیشرفت: {$reportData['progress_percentage']}%");
            $this->newLine();

            // ایجاد توکن unsubscribe
            if (!$user->email_unsubscribe_token) {
                $user->email_unsubscribe_token = Str::random(64);
                $user->save();
            }

            // ایجاد رکورد صف
            $this->info('📝 ایجاد رکورد صف...');
            $queue = EmailReportQueue::create([
                'user_id' => $user->id,
                'chat_id' => $user->chat_id,
                'email' => $user->email,
                'report_period_start' => now()->subWeek()->toDateString(),
                'report_period_end' => now()->toDateString(),
                'status' => 'pending',
            ]);
            $this->info("✅ رکورد صف ایجاد شد (ID: {$queue->id})");
            $this->newLine();

            // اجرای Job
            $this->info('🚀 اجرای Job...');
            $job = new SendPrayerReportEmailJob(
                $queue->id,
                $reportData,
                $user->email,
                $user->email_unsubscribe_token
            );
            
            $job->handle(app(\App\Interfaces\Services\EmailService::class));
            
            $this->info('✅ Job با موفقیت اجرا شد');
            $this->newLine();

            // بررسی وضعیت صف
            $queue->refresh();
            $this->info("📊 وضعیت صف:");
            $this->info("   Status: {$queue->status}");
            if ($queue->sent_at) {
                $this->info("   Sent At: {$queue->sent_at}");
            }
            if ($queue->failed_reason) {
                $this->error("   Failed Reason: {$queue->failed_reason}");
            }

            // به‌روزرسانی زمان آخرین ارسال
            $user->last_email_report_sent_at = now();
            $user->save();

            $this->info("\n✅ تست با موفقیت انجام شد!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('❌ [TestWeeklyReportJob] Error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * تبدیل reportData به ساختار موردنیاز برای Email
     * ساختار اصلی را حفظ می‌کند و کلیدهای جدید را اضافه می‌کند
     */
    protected function transformReportData(array $rawReportData): array
    {
        $records = $rawReportData['records'] ?? collect();
        $statsByType = $rawReportData['stats_by_type'] ?? [];
        $progress = $rawReportData['progress'] ?? [];
        $period = $rawReportData['period'] ?? [];

        // محاسبه total_rakats از stats_by_type یا records
        $totalRakats = 0;
        foreach ($statsByType as $type => $stats) {
            $totalRakats += $stats['total_rakats'] ?? 0;
        }
        if ($totalRakats == 0) {
            $totalRakats = $records->sum('rakats');
        }

        // تبدیل stats_by_type به فرمت موردنیاز برای generateTextReport
        $prayersByType = [];
        foreach ($statsByType as $type => $stats) {
            $typeLabels = [
                'fajr' => 'صبح',
                'dhuhr' => 'ظهر',
                'asr' => 'عصر',
                'maghrib' => 'مغرب',
                'isha' => 'عشا',
            ];
            $label = $typeLabels[$type] ?? $type;
            $prayersByType[$label] = $stats['count'] ?? 0;
        }

        // اضافه کردن total_rakats به stats_by_type برای view ها
        $statsByTypeWithTotal = $statsByType;
        $statsByTypeWithTotal['total_rakats'] = $totalRakats;

        // تنظیم progress برای view ها
        $progressForView = $progress ? [
            'percentage' => $progress['progress_percentage'] ?? $progress['percentage'] ?? 0,
            'remaining' => $progress['remaining_rakats'] ?? $progress['remaining'] ?? 0,
        ] : null;

        // ساختار اصلی را حفظ می‌کنیم (برای view ها) و کلیدهای جدید را اضافه می‌کنیم (برای generateTextReport)
        return array_merge($rawReportData, [
            // کلیدهای جدید برای generateTextReport
            'period_start' => $period['from'] ?? now()->subWeek()->toDateString(),
            'period_end' => $period['to'] ?? now()->toDateString(),
            'total_prayers' => $records->count(),
            'total_rakats' => $totalRakats,
            'progress_percentage' => $progressForView['percentage'] ?? 0,
            'prayers_by_type' => $prayersByType,
            
            // به‌روزرسانی stats_by_type با total_rakats
            'stats_by_type' => $statsByTypeWithTotal,
            
            // به‌روزرسانی progress با کلیدهای درست
            'progress' => $progressForView,
            
            // اطلاعات تکمیلی (اگر وجود دارد)
            'daily_stats' => $rawReportData['daily_stats'] ?? [],
            'weekly_comparison' => $rawReportData['weekly_comparison'] ?? null,
            'peak_activity' => $rawReportData['peak_activity'] ?? null,
            'completion_time' => $rawReportData['completion_time'] ?? null,
            'top_10_users' => $rawReportData['top_10_users'] ?? [],
            'motivational_message' => $rawReportData['motivational_message'] ?? '',
            
            // URL گزارش وب (اگر در rawReportData وجود دارد)
            'report_url' => $rawReportData['report_url'] ?? null,
        ]);
    }
}
