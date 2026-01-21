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
            $reportData = $this->prayerBotService->getWeeklyReport($user->chat_id, $user->origin);
            $this->info('✅ گزارش دریافت شد');
            $this->info("   تعداد نمازها: {$reportData['total_prayers']}");
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
}
