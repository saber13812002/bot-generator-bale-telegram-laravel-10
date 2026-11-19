<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class TestCPanelSMTP extends Command
{
    protected $signature = 'test:cpanel-smtp {email} {--code=123456}';
    protected $description = 'تست ارسال ایمیل با cPanel SMTP';

    public function handle(): int
    {
        $email = $this->argument('email');
        $code = $this->option('code');

        $this->info("📧 تست ارسال ایمیل با cPanel SMTP به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        // خواندن تنظیمات از .env
        $mailHost = env('MAIL_HOST', 'mail.yourdomain.com');
        $mailPort = env('MAIL_PORT', 587);
        $mailUsername = env('MAIL_USERNAME');
        $mailPassword = env('MAIL_PASSWORD');
        $mailEncryption = env('MAIL_ENCRYPTION', 'tls');
        $mailFromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME'));
        $mailFromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Bots'));

        $this->info("\n🔍 تنظیمات cPanel SMTP:");
        $this->line("   Host: {$mailHost}");
        $this->line("   Port: {$mailPort}");
        $this->line("   Username: {$mailUsername}");
        $this->line("   Encryption: {$mailEncryption}");

        if (!$mailUsername || !$mailPassword) {
            $this->error("❌ تنظیمات MAIL_USERNAME یا MAIL_PASSWORD در .env تنظیم نشده است!");
            $this->warn("\n💡 تنظیمات مورد نیاز در .env:");
            $this->warn("   MAIL_HOST=mail.yourdomain.com");
            $this->warn("   MAIL_PORT=587");
            $this->warn("   MAIL_USERNAME=your-email@yourdomain.com");
            $this->warn("   MAIL_PASSWORD=your-email-password");
            $this->warn("   MAIL_ENCRYPTION=tls");
            return 1;
        }

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            
            if ($mailEncryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mailEncryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = (int) $mailPort;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($mailFromAddress, $mailFromName);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = trans('bot.email_verification_subject');
            $mail->Body = view('emails.email-verification', ['code' => $code])->render();
            $mail->AltBody = "کد تایید ایمیل شما: {$code}";

            $this->info("\n📤 در حال ارسال ایمیل...");
            $mail->send();
            
            $this->info("✅ ایمیل با موفقیت ارسال شد!");
            Log::info('✅ [TestCPanelSMTP] Email sent successfully', [
                'email' => $email,
                'host' => $mailHost,
                'port' => $mailPort
            ]);
            return 0;
        } catch (PHPMailerException $e) {
            $this->error("❌ خطا در ارسال ایمیل:");
            $this->error($mail->ErrorInfo);
            $this->error("\n📋 جزئیات خطا:");
            $this->error("Message: " . $e->getMessage());
            
            Log::error('❌ [TestCPanelSMTP] Error sending email', [
                'email' => $email,
                'error' => $mail->ErrorInfo,
                'host' => $mailHost,
                'port' => $mailPort
            ]);
            
            $this->info("\n💡 راهنمای رفع مشکل:");
            $this->info("1. بررسی تنظیمات در .env");
            $this->info("2. بررسی رمز عبور ایمیل در cPanel");
            $this->info("3. بررسی اینکه پورت {$mailPort} باز است");
            $this->info("4. تست پورت: php artisan test:smtp-ports {$mailHost}");
            
            return 1;
        }
    }
}
