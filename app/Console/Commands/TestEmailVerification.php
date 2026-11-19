<?php

namespace App\Console\Commands;

use App\Mail\EmailVerificationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class TestEmailVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-verification {email} {--code=123456}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تست ارسال ایمیل تایید';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $code = $this->option('code');

        $this->info("📧 تست ارسال ایمیل تایید به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        // بررسی تنظیمات ایمیل
        $this->info("\n🔍 بررسی تنظیمات ایمیل:");
        $this->checkMailConfig();

        // تلاش برای ارسال ایمیل
        $this->info("\n📤 در حال ارسال ایمیل...");
        
        try {
            Mail::to($email)->send(new EmailVerificationMail($code));
            
            $this->info("✅ ایمیل با موفقیت ارسال شد!");
            Log::info('✅ [TestEmailVerification] Email sent successfully', [
                'email' => $email,
                'code' => $code
            ]);
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ خطا در ارسال ایمیل:");
            $this->error($e->getMessage());
            $this->error("\n📋 جزئیات خطا:");
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            
            Log::error('❌ [TestEmailVerification] Error sending email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->info("\n💡 راهنمای رفع مشکل:");
            $this->info("1. بررسی تنظیمات MAIL_* در فایل .env");
            $this->info("2. برای Gmail: استفاده از App Password");
            $this->info("3. بررسی اتصال به اینترنت");
            $this->info("4. بررسی لاگ‌ها: storage/logs/laravel.log");
            
            return 1;
        }
    }

    /**
     * بررسی تنظیمات ایمیل
     */
    protected function checkMailConfig(): void
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $username = config('mail.mailers.smtp.username');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->line("   Mailer: " . ($mailer ?: '❌ تنظیم نشده'));
        $this->line("   Host: " . ($host ?: '❌ تنظیم نشده'));
        $this->line("   Port: " . ($port ?: '❌ تنظیم نشده'));
        $this->line("   Username: " . ($username ?: '❌ تنظیم نشده'));
        $this->line("   From Address: " . ($fromAddress ?: '❌ تنظیم نشده'));
        $this->line("   From Name: " . ($fromName ?: '❌ تنظیم نشده'));

        // بررسی تنظیمات ضروری
        $required = ['mailer', 'host', 'port', 'username', 'fromAddress'];
        $missing = [];

        if (!$mailer) $missing[] = 'MAIL_MAILER';
        if (!$host) $missing[] = 'MAIL_HOST';
        if (!$port) $missing[] = 'MAIL_PORT';
        if (!$username) $missing[] = 'MAIL_USERNAME';
        if (!$fromAddress) $missing[] = 'MAIL_FROM_ADDRESS';

        if (!empty($missing)) {
            $this->warn("\n⚠️  تنظیمات زیر در .env تنظیم نشده:");
            foreach ($missing as $item) {
                $this->warn("   - {$item}");
            }
        } else {
            $this->info("\n✅ همه تنظیمات ضروری تنظیم شده است");
        }
    }
}
