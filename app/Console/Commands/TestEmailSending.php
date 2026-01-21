<?php

namespace App\Console\Commands;

use App\Interfaces\Services\EmailService;
use App\Services\EmailThresholdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEmailSending extends Command
{
    protected $signature = 'email:test-sending 
                            {--email= : آدرس ایمیل گیرنده}
                            {--type=verification : نوع ایمیل (verification, weekly-report, motivational)}
                            {--force-threshold : نادیده گرفتن Threshold}';

    protected $description = 'تست ارسال واقعی ایمیل';

    protected EmailService $emailService;
    protected EmailThresholdService $thresholdService;

    public function __construct(EmailService $emailService, EmailThresholdService $thresholdService)
    {
        parent::__construct();
        $this->emailService = $emailService;
        $this->thresholdService = $thresholdService;
    }

    public function handle(): int
    {
        $email = $this->option('email');
        $type = $this->option('type');
        $forceThreshold = $this->option('force-threshold');

        if (!$email) {
            $this->error('❌ آدرس ایمیل الزامی است (--email=test@example.com)');
            return 1;
        }

        $this->info('🧪 تست ارسال واقعی ایمیل');
        $this->info("📧 ایمیل: {$email}");
        $this->info("📋 نوع: {$type}");
        $this->newLine();

        // چک کردن Threshold
        if (!$forceThreshold) {
            $this->info('🔍 بررسی Threshold...');
            $thresholdInfo = $this->thresholdService->getThresholdInfo('daily');
            $this->info("   فعلی: {$thresholdInfo['current']} / {$thresholdInfo['max']}");
            $this->info("   باقیمانده: {$thresholdInfo['remaining']}");

            if (!$this->thresholdService->canSendEmail('daily')) {
                $this->error('❌ Threshold رد شده است. از --force-threshold استفاده کنید.');
                return 1;
            }
            $this->info('✅ Threshold OK');
        } else {
            $this->warn('⚠️  حالت force-threshold فعال است');
        }

        $this->newLine();

        try {
            switch ($type) {
                case 'verification':
                    $code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $this->info("🔑 کد تایید: {$code}");
                    $this->info('📤 در حال ارسال...');
                    $this->emailService->sendVerificationEmail($email, $code);
                    break;

                case 'weekly-report':
                    $reportData = [
                        'period_start' => now()->subWeek()->format('Y-m-d'),
                        'period_end' => now()->format('Y-m-d'),
                        'total_prayers' => 10,
                        'progress_percentage' => 50,
                        'prayers_by_type' => [
                            'صبح' => 3,
                            'ظهر' => 4,
                            'عصر' => 2,
                            'مغرب' => 1,
                        ]
                    ];
                    $unsubscribeToken = bin2hex(random_bytes(32));
                    $this->info('📤 در حال ارسال...');
                    $this->emailService->sendWeeklyReportEmail($email, $reportData, $unsubscribeToken, 1);
                    break;

                case 'motivational':
                    $message = "🌟 عالی کار می‌کنید!\n\nشما تا الان 10 نماز قضا ثبت کرده‌اید.\nادامه دهید! 💪";
                    $this->info('📤 در حال ارسال...');
                    $this->emailService->sendMotivationalEmail($email, $message, 'پیام انگیزشی - تست');
                    break;

                default:
                    $this->error("❌ نوع نامعتبر: {$type}");
                    $this->info('نوع‌های معتبر: verification, weekly-report, motivational');
                    return 1;
            }

            // افزایش Threshold
            if (!$forceThreshold) {
                $this->thresholdService->incrementSentCount('daily');
            }

            $this->info("\n✅ ایمیل با موفقیت ارسال شد!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('❌ [TestEmailSending] Error', [
                'email' => $email,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
