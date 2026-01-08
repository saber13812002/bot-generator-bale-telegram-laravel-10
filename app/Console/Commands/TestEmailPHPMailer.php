<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

class TestEmailPHPMailer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-phpmailer {email} {--code=123456} {--tls : استفاده از TLS به جای SSL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تست ارسال ایمیل با PHPMailer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $code = $this->option('code');

        $this->info("📧 تست ارسال ایمیل با PHPMailer به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        // بررسی تنظیمات ایمیل
        $this->info("\n🔍 بررسی تنظیمات ایمیل:");
        $this->checkMailConfig();

        // خواندن تنظیمات از .env
        $useTls = $this->option('tls');
        
        if ($useTls) {
            $this->info("⚠️  استفاده از TLS (پورت 587) به جای SSL (پورت 465)");
            $mailHost = env('MAIL_HOST', 'smtp.gmail.com');
            $mailPort = 587;
            $mailEncryption = 'tls';
        } else {
            $mailHost = env('MAIL_HOST', 'smtp.gmail.com');
            $mailPort = env('MAIL_PORT', 465);
            $mailEncryption = env('MAIL_ENCRYPTION', 'ssl');
        }
        
        $mailUsername = env('MAIL_USERNAME');
        $mailPassword = env('MAIL_PASSWORD');
        $mailFromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME'));
        $mailFromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Bots'));

        // بررسی تنظیمات ضروری
        if (!$mailUsername || !$mailPassword) {
            $this->error("❌ تنظیمات MAIL_USERNAME یا MAIL_PASSWORD در .env تنظیم نشده است!");
            return 1;
        }

        // تلاش برای ارسال ایمیل
        $this->info("\n📤 در حال ارسال ایمیل با PHPMailer...");
        
        try {
            $mail = new PHPMailer(true);

            // تنظیمات SMTP
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            
            // تنظیمات رمزنگاری
            if ($mailEncryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mailEncryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = (int) $mailPort;
            $mail->CharSet = 'UTF-8';
            
            // فعال‌سازی Debug (اختیاری - برای عیب‌یابی)
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = function($str, $level) {
            //     $this->line("   [PHPMailer Debug] $str");
            // };

            // تنظیمات فرستنده و گیرنده
            $mail->setFrom($mailFromAddress, $mailFromName);
            $mail->addAddress($email);

            // محتوای ایمیل
            $mail->isHTML(true);
            $mail->Subject = trans('bot.email_verification_subject');
            
            // استفاده از view موجود
            $mail->Body = view('emails.email-verification', ['code' => $code])->render();
            $mail->AltBody = "کد تایید ایمیل شما: {$code}";

            // ارسال ایمیل
            $mail->send();
            
            $this->info("✅ ایمیل با موفقیت ارسال شد!");
            Log::info('✅ [TestEmailPHPMailer] Email sent successfully', [
                'email' => $email,
                'code' => $code,
                'from' => $mailFromAddress
            ]);
            
            return 0;
        } catch (PHPMailerException $e) {
            $this->error("❌ خطا در ارسال ایمیل (PHPMailer):");
            $this->error($mail->ErrorInfo);
            $this->error("\n📋 جزئیات خطا:");
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            $this->error("Message: " . $e->getMessage());
            
            // بررسی نوع خطا
            $errorInfo = $mail->ErrorInfo;
            $this->info("\n🔍 تحلیل خطا:");
            
            if (str_contains($errorInfo, 'Could not authenticate') || str_contains($errorInfo, 'authentication')) {
                $this->warn("   ⚠️  مشکل احراز هویت:");
                $this->warn("   - App Password ممکن است اشتباه باشد");
                $this->warn("   - App Password ممکن است منقضی شده باشد");
                $this->warn("   - 2-Step Verification باید فعال باشد");
                $this->warn("   - Username باید آدرس ایمیل کامل باشد");
            } elseif (str_contains($errorInfo, 'Connection') || str_contains($errorInfo, 'timeout')) {
                $this->warn("   ⚠️  مشکل اتصال:");
                $this->warn("   - Host یا Port ممکن است اشتباه باشد");
                $this->warn("   - اتصال به اینترنت را بررسی کنید");
                $this->warn("   - Firewall ممکن است مانع شود");
            }
            
            Log::error('❌ [TestEmailPHPMailer] Error sending email', [
                'email' => $email,
                'error' => $mail->ErrorInfo,
                'phpmailer_error' => $e->getMessage(),
                'config' => [
                    'host' => $mailHost,
                    'port' => $mailPort,
                    'username' => $mailUsername,
                    'encryption' => $mailEncryption,
                    'from_address' => $mailFromAddress,
                ],
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->info("\n💡 راهنمای رفع مشکل:");
            $this->info("1. App Password جدید ایجاد کنید: https://myaccount.google.com/apppasswords");
            $this->info("2. مطمئن شوید فاصله‌ها در App Password حذف شده‌اند");
            $this->info("3. MAIL_PASSWORD را در .env به‌روزرسانی کنید");
            $this->info("4. اگر پورت 465 کار نمی‌کند، پورت 587 با TLS امتحان کنید");
            $this->info("5. بررسی لاگ‌ها: storage/logs/laravel.log");
            
            return 1;
        } catch (Exception $e) {
            $this->error("❌ خطا در ارسال ایمیل:");
            $this->error($e->getMessage());
            $this->error("\n📋 جزئیات خطا:");
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            
            Log::error('❌ [TestEmailPHPMailer] General error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }

    /**
     * بررسی تنظیمات ایمیل
     */
    protected function checkMailConfig(): void
    {
        $mailHost = env('MAIL_HOST', 'smtp.gmail.com');
        $mailPort = env('MAIL_PORT', 465);
        $mailUsername = env('MAIL_USERNAME');
        $mailPassword = env('MAIL_PASSWORD');
        $mailEncryption = env('MAIL_ENCRYPTION', 'ssl');
        $mailFromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME'));
        $mailFromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Bots'));

        $this->line("   Host: " . ($mailHost ?: '❌ تنظیم نشده'));
        $this->line("   Port: " . ($mailPort ?: '❌ تنظیم نشده'));
        $this->line("   Username: " . ($mailUsername ?: '❌ تنظیم نشده'));
        $this->line("   Password: " . ($mailPassword ? '✅ تنظیم شده' : '❌ تنظیم نشده'));
        $this->line("   Encryption: " . ($mailEncryption ?: '❌ تنظیم نشده'));
        $this->line("   From Address: " . ($mailFromAddress ?: '❌ تنظیم نشده'));
        $this->line("   From Name: " . ($mailFromName ?: '❌ تنظیم نشده'));

        // بررسی تنظیمات ضروری
        $required = ['mailHost', 'mailPort', 'mailUsername', 'mailPassword'];
        $missing = [];

        if (!$mailHost) $missing[] = 'MAIL_HOST';
        if (!$mailPort) $missing[] = 'MAIL_PORT';
        if (!$mailUsername) $missing[] = 'MAIL_USERNAME';
        if (!$mailPassword) $missing[] = 'MAIL_PASSWORD';

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
