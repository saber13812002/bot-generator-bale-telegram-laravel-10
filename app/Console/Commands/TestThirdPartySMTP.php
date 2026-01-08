<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class TestThirdPartySMTP extends Command
{
    protected $signature = 'test:thirdparty-smtp {email} {--provider=mailgun} {--code=123456}';
    protected $description = 'تست ارسال ایمیل با سرویس‌های ثالث (Mailgun, SendGrid, SES)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $provider = $this->option('provider');
        $code = $this->option('code');

        $this->info("📧 تست ارسال ایمیل با {$provider} به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        $config = $this->getProviderConfig($provider);
        
        if (!$config) {
            $this->error("❌ Provider نامعتبر است!");
            $this->warn("💡 گزینه‌های موجود: mailgun, sendgrid, ses");
            return 1;
        }

        $this->info("\n🔍 تنظیمات {$provider}:");
        foreach ($config as $key => $value) {
            if ($key !== 'password') {
                $this->line("   {$key}: {$value}");
            } else {
                $this->line("   password: " . ($value ? '✅ تنظیم شده' : '❌ تنظیم نشده'));
            }
        }

        if (!$config['password']) {
            $this->error("\n❌ رمز عبور یا API Key تنظیم نشده است!");
            $this->warn("\n💡 تنظیمات مورد نیاز در .env:");
            $this->showProviderEnvExample($provider);
            return 1;
        }

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_address'], $config['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = trans('bot.email_verification_subject');
            $mail->Body = view('emails.email-verification', ['code' => $code])->render();
            $mail->AltBody = "کد تایید ایمیل شما: {$code}";

            $this->info("\n📤 در حال ارسال ایمیل...");
            $mail->send();
            
            $this->info("✅ ایمیل با موفقیت ارسال شد!");
            Log::info("✅ [TestThirdPartySMTP] Email sent successfully", [
                'email' => $email,
                'provider' => $provider,
                'host' => $config['host']
            ]);
            return 0;
        } catch (PHPMailerException $e) {
            $this->error("❌ خطا در ارسال ایمیل:");
            $this->error($mail->ErrorInfo);
            $this->error("\n📋 جزئیات خطا:");
            $this->error("Message: " . $e->getMessage());
            
            Log::error("❌ [TestThirdPartySMTP] Error sending email", [
                'email' => $email,
                'provider' => $provider,
                'error' => $mail->ErrorInfo
            ]);
            
            $this->info("\n💡 راهنمای رفع مشکل:");
            $this->info("1. بررسی تنظیمات در .env");
            $this->info("2. بررسی API Key یا Password");
            $this->info("3. بررسی اینکه دامنه در {$provider} تایید شده است");
            
            return 1;
        }
    }

    protected function getProviderConfig(string $provider): ?array
    {
        $configs = [
            'mailgun' => [
                'host' => env('MAILGUN_HOST', 'smtp.mailgun.org'),
                'port' => env('MAILGUN_PORT', 587),
                'username' => env('MAILGUN_USERNAME', 'postmaster@yourdomain.com'),
                'password' => env('MAILGUN_PASSWORD'),
                'from_address' => env('MAIL_FROM_ADDRESS', env('MAILGUN_FROM_ADDRESS')),
                'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Bots')),
            ],
            'sendgrid' => [
                'host' => env('SENDGRID_HOST', 'smtp.sendgrid.net'),
                'port' => env('SENDGRID_PORT', 587),
                'username' => env('SENDGRID_USERNAME', 'apikey'),
                'password' => env('SENDGRID_API_KEY'),
                'from_address' => env('MAIL_FROM_ADDRESS', env('SENDGRID_FROM_ADDRESS')),
                'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Bots')),
            ],
            'ses' => [
                'host' => env('SES_HOST', 'email-smtp.us-east-1.amazonaws.com'),
                'port' => env('SES_PORT', 587),
                'username' => env('SES_USERNAME'),
                'password' => env('SES_PASSWORD'),
                'from_address' => env('MAIL_FROM_ADDRESS', env('SES_FROM_ADDRESS')),
                'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Bots')),
            ],
        ];

        return $configs[$provider] ?? null;
    }

    protected function showProviderEnvExample(string $provider): void
    {
        $examples = [
            'mailgun' => [
                'MAILGUN_HOST=smtp.mailgun.org',
                'MAILGUN_PORT=587',
                'MAILGUN_USERNAME=postmaster@yourdomain.com',
                'MAILGUN_PASSWORD=your-mailgun-smtp-password',
                'MAILGUN_FROM_ADDRESS=noreply@yourdomain.com',
            ],
            'sendgrid' => [
                'SENDGRID_HOST=smtp.sendgrid.net',
                'SENDGRID_PORT=587',
                'SENDGRID_USERNAME=apikey',
                'SENDGRID_API_KEY=your-sendgrid-api-key',
                'SENDGRID_FROM_ADDRESS=noreply@yourdomain.com',
            ],
            'ses' => [
                'SES_HOST=email-smtp.us-east-1.amazonaws.com',
                'SES_PORT=587',
                'SES_USERNAME=your-ses-username',
                'SES_PASSWORD=your-ses-password',
                'SES_FROM_ADDRESS=noreply@yourdomain.com',
            ],
        ];

        if (isset($examples[$provider])) {
            foreach ($examples[$provider] as $line) {
                $this->line("   {$line}");
            }
        }
    }
}
