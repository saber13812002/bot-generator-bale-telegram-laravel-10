<?php

namespace App\Console\Commands;

use App\Interfaces\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class TestEmailService extends Command
{
    protected $signature = 'email:test 
                            {type : Type of email (verification|weekly-report|motivational|connection)}
                            {email? : Email address for testing}
                            {--code=123456 : Verification code}';

    protected $description = 'تست ماژول Email Service';

    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle(): int
    {
        $type = $this->argument('type');
        $email = $this->argument('email');

        $this->info("🧪 تست ماژول Email Service");
        $this->info("📦 نوع: {$type}");
        $this->newLine();

        try {
            switch ($type) {
                case 'connection':
                    return $this->testConnection();
                case 'verification':
                    if (!$email) {
                        $this->error('❌ آدرس ایمیل الزامی است');
                        return 1;
                    }
                    return $this->testVerification($email);
                case 'weekly-report':
                    if (!$email) {
                        $this->error('❌ آدرس ایمیل الزامی است');
                        return 1;
                    }
                    return $this->testWeeklyReport($email);
                case 'motivational':
                    if (!$email) {
                        $this->error('❌ آدرس ایمیل الزامی است');
                        return 1;
                    }
                    return $this->testMotivational($email);
                default:
                    $this->error("❌ نوع نامعتبر: {$type}");
                    $this->info("نوع‌های معتبر: connection, verification, weekly-report, motivational");
                    return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('Email Service Test Failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function testConnection(): int
    {
        $this->info("🔍 در حال بررسی اتصال...");

        try {
            $result = $this->emailService->testConnection();
            
            if ($result) {
                $this->info("✅ اتصال موفق است!");
                return 0;
            } else {
                $this->error("❌ اتصال ناموفق");
                return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ خطا در اتصال: {$e->getMessage()}");
            return 1;
        }
    }

    protected function testVerification(string $email): int
    {
        $code = $this->option('code');
        
        $this->info("📧 ارسال ایمیل تایید به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        try {
            $this->emailService->sendVerificationEmail($email, $code);
            
            $this->info("✅ ایمیل با موفقیت ارسال شد!");
            return 0;
        } catch (Exception $e) {
            $this->error("❌ خطا در ارسال: {$e->getMessage()}");
            return 1;
        }
    }

    protected function testWeeklyReport(string $email): int
    {
        $this->info("📊 ارسال گزارش هفتگی به: {$email}");

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
        $templateVersion = rand(1, 5);

        try {
            $this->emailService->sendWeeklyReportEmail(
                $email,
                $reportData,
                $unsubscribeToken,
                $templateVersion
            );
            
            $this->info("✅ گزارش هفتگی با موفقیت ارسال شد!");
            $this->info("📋 تمپلیت: v{$templateVersion}");
            return 0;
        } catch (Exception $e) {
            $this->error("❌ خطا در ارسال: {$e->getMessage()}");
            return 1;
        }
    }

    protected function testMotivational(string $email): int
    {
        $this->info("🌟 ارسال ایمیل انگیزشی به: {$email}");

        $message = "🌟 عالی کار می‌کنید!\n\n";
        $message .= "شما تا الان 10 نماز قضا ثبت کرده‌اید.\n";
        $message .= "ادامه دهید! 💪\n\n";
        $message .= "هر قدم کوچک شما، یک قدم بزرگ به سوی هدف است.";

        try {
            $this->emailService->sendMotivationalEmail(
                $email,
                $message,
                'پیام انگیزشی - تست'
            );
            
            $this->info("✅ ایمیل انگیزشی با موفقیت ارسال شد!");
            return 0;
        } catch (Exception $e) {
            $this->error("❌ خطا در ارسال: {$e->getMessage()}");
            return 1;
        }
    }
}
