<?php

namespace App\Console\Commands;

use App\Interfaces\Services\PrayerBotService;
use App\Jobs\SendPrayerReportEmailJob;
use App\Models\BotUsers;
use App\Models\EmailReportQueue;
use App\Services\EmailThresholdService;
use App\Services\EmailSchedulingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SendPrayerWeeklyReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prayer:send-weekly-reports 
                            {--limit=2 : Maximum number of emails to send in this run}
                            {--force : Force send even if recently sent}
                            {--batch-size=10 : Size of email batch}
                            {--interval=30 : Interval between batches in minutes}
                            {--force-threshold : Ignore threshold check (admin only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ارسال گزارش‌های هفتگی نماز قضا به کاربران (با محدودیت تعداد)';

    protected PrayerBotService $prayerBotService;
    protected EmailThresholdService $thresholdService;
    protected EmailSchedulingService $schedulingService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        PrayerBotService $prayerBotService,
        EmailThresholdService $thresholdService,
        EmailSchedulingService $schedulingService
    ) {
        parent::__construct();
        $this->prayerBotService = $prayerBotService;
        $this->thresholdService = $thresholdService;
        $this->schedulingService = $schedulingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        
        Log::info('📧 [SendPrayerWeeklyReports] Command started');
        $this->info('🕌 شروع ارسال گزارش‌های هفتگی نماز قضا...');

        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $interval = (int) $this->option('interval');
        $forceThreshold = $this->option('force-threshold');

        // نمایش اطلاعات Threshold
        $this->displayThresholdInfo();

        // چک کردن Threshold (مگر اینکه force-threshold باشد)
        if (!$forceThreshold) {
            if (!$this->thresholdService->canSendEmail('daily')) {
                $this->warn('⚠️  Threshold روزانه رد شده است. ارسال متوقف می‌شود.');
                Log::warning('⚠️ [SendPrayerWeeklyReports] Daily threshold exceeded');
                return 0;
            }
        } else {
            $this->warn('⚠️  حالت force-threshold فعال است. Threshold نادیده گرفته می‌شود.');
        }

        try {
            // استفاده از EmailSchedulingService برای دریافت کاربران
            if ($batchSize > 0) {
                $users = $this->schedulingService->getEligibleUsers($batchSize);
            } else {
                $users = $this->getEligibleUsers($limit, $force);
            }

            if ($users->isEmpty()) {
                $this->info('ℹ️  هیچ کاربری برای ارسال ایمیل یافت نشد.');
                Log::info('ℹ️ [SendPrayerWeeklyReports] No eligible users found');
                return 0;
            }

            $this->info("📊 تعداد کاربران: {$users->count()}");
            $this->info("📦 اندازه باکس: {$batchSize}");
            $this->info("⏱️  اینتروال: {$interval} دقیقه");

            // زمان‌بندی ارسال
            if ($interval > 0) {
                $this->schedulingService->scheduleBatchEmails($users, $interval);
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($users as $user) {
                try {
                    // چک کردن Threshold قبل از هر ارسال
                    if (!$forceThreshold && !$this->thresholdService->canSendEmail('daily')) {
                        $this->warn("⚠️  Threshold رد شد. ارسال برای {$user->email} متوقف شد.");
                        break;
                    }

                    $this->processUser($user);
                    
                    // افزایش تعداد Threshold
                    if (!$forceThreshold) {
                        $this->thresholdService->incrementSentCount('daily');
                    }
                    
                    $successCount++;
                    $this->info("✅ ایمیل برای {$user->email} آماده ارسال شد.");
                } catch (\Exception $e) {
                    $failCount++;
                    $this->error("❌ خطا برای {$user->email}: {$e->getMessage()}");
                    Log::error('❌ [SendPrayerWeeklyReports] Error processing user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->info("\n📈 خلاصه:");
            $this->info("✅ موفق: {$successCount}");
            $this->info("❌ ناموفق: {$failCount}");
            $this->info("⏱️  زمان: {$duration} ثانیه");

            // نمایش اطلاعات Threshold نهایی
            $this->displayThresholdInfo();

            Log::info('✅ [SendPrayerWeeklyReports] Command completed', [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'duration' => $duration
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ خطای کلی: {$e->getMessage()}");
            Log::error('❌ [SendPrayerWeeklyReports] Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * نمایش اطلاعات Threshold
     */
    protected function displayThresholdInfo(): void
    {
        $info = $this->thresholdService->getThresholdInfo('daily');
        $this->info("\n📊 Threshold روزانه:");
        $this->info("   فعلی: {$info['current']} / {$info['max']}");
        $this->info("   باقیمانده: {$info['remaining']}");
        $this->info("   درصد: {$info['percentage']}%");
    }

    /**
     * دریافت کاربران واجد شرایط
     */
    protected function getEligibleUsers(int $limit, bool $force)
    {
        $query = BotUsers::whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->where('email_report_frequency', '!=', 'never');

        if (!$force) {
            // فقط کاربرانی که بیش از 7 روز از آخرین ایمیلشان گذشته
            $query->where(function($q) {
                $q->whereNull('last_email_report_sent_at')
                  ->orWhere('last_email_report_sent_at', '<', now()->subDays(7));
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * پردازش یک کاربر
     */
    protected function processUser(BotUsers $user): void
    {
        Log::info('📨 [SendPrayerWeeklyReports] Processing user', [
            'user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'email' => $user->email
        ]);

        // دریافت گزارش هفتگی
        $reportData = $this->prayerBotService->getWeeklyReport($user->chat_id, $user->origin);

        // ایجاد توکن unsubscribe اگر وجود ندارد
        if (!$user->email_unsubscribe_token) {
            $user->email_unsubscribe_token = Str::random(64);
            $user->save();
        }

        // ایجاد رکورد صف
        $queue = EmailReportQueue::create([
            'user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'email' => $user->email,
            'report_period_start' => now()->subWeek()->toDateString(),
            'report_period_end' => now()->toDateString(),
            'status' => 'pending',
        ]);

        // افزودن Job به صف
        SendPrayerReportEmailJob::dispatch(
            $queue->id,
            $reportData,
            $user->email,
            $user->email_unsubscribe_token
        );

        // به‌روزرسانی زمان آخرین ارسال
        $user->last_email_report_sent_at = now();
        $user->save();

        Log::info('✅ [SendPrayerWeeklyReports] User processed successfully', [
            'user_id' => $user->id,
            'queue_id' => $queue->id
        ]);
    }
}
