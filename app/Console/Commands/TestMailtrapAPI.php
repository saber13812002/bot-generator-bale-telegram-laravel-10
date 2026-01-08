<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TestMailtrapAPI extends Command
{
    protected $signature = 'test:mailtrap-api {email} {--code=123456} {--token=} {--inbox-id=}';
    protected $description = 'تست ارسال ایمیل با Mailtrap API';

    public function handle(): int
    {
        $email = $this->argument('email');
        $code = $this->option('code');
        $token = $this->option('token') ?: env('MAILTRAP_API_TOKEN');
        $inboxId = $this->option('inbox-id') ?: env('MAILTRAP_INBOX_ID', '1439975');

        $this->info("📧 تست ارسال ایمیل با Mailtrap API به: {$email}");
        $this->info("🔑 کد تایید: {$code}");

        // بررسی تنظیمات
        $this->info("\n🔍 بررسی تنظیمات Mailtrap:");
        $this->line("   API Token: " . ($token ? '✅ تنظیم شده' : '❌ تنظیم نشده'));
        $this->line("   Inbox ID: {$inboxId}");

        if (!$token) {
            $this->error("\n❌ API Token تنظیم نشده است!");
            $this->warn("\n💡 تنظیمات مورد نیاز در .env:");
            $this->warn("   MAILTRAP_API_TOKEN=your-api-token-here");
            $this->warn("   MAILTRAP_INBOX_ID=1439975  # اختیاری");
            $this->newLine();
            $this->info("یا از طریق option استفاده کنید:");
            $this->info("   php artisan test:mailtrap-api {$email} --token=your-token --inbox-id=1439975");
            return 1;
        }

        // بررسی فاصله در Token
        if (str_contains($token, ' ')) {
            $this->error("\n❌ خطا: API Token دارای فاصله است!");
            $this->warn("⚠️  API Token نباید فاصله داشته باشد");
            $this->warn("💡 تعداد فاصله: " . substr_count($token, ' ') . " فاصله");
            $this->newLine();
            $this->info("🔧 راه‌حل:");
            $this->info("1. Token را از Mailtrap Dashboard کپی کنید");
            $this->info("2. همه فاصله‌ها را حذف کنید");
            $this->info("3. MAILTRAP_API_TOKEN را در .env به‌روزرسانی کنید");
            return 1;
        }

        // خواندن view و تبدیل به HTML
        try {
            $htmlBody = view('emails.email-verification', ['code' => $code])->render();
        } catch (Exception $e) {
            $this->warn("⚠️  خطا در خواندن view، از متن ساده استفاده می‌کنیم");
            $htmlBody = "<html><body><h1>کد تایید ایمیل</h1><p>کد تایید شما: <strong>{$code}</strong></p></body></html>";
        }

        $textBody = "کد تایید ایمیل شما: {$code}";

        // تنظیمات Mailtrap API
        $apiUrl = "https://send.api.mailtrap.io/api/send/{$inboxId}";
        
        $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@mailtrap.io');
        $fromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Bots'));

        // اگر آدرس ایمیل معتبر نیست، یک آدرس پیش‌فرض استفاده کن
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = 'noreply@mailtrap.io';
        }

        $this->info("\n📤 در حال ارسال ایمیل از طریق Mailtrap API...");
        $this->line("   From: {$fromAddress} ({$fromName})");
        $this->line("   To: {$email}");
        $this->line("   Subject: " . trans('bot.email_verification_subject'));

        try {
            $response = Http::withHeaders([
                'Api-Token' => $token,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'from' => [
                    'email' => $fromAddress,
                    'name' => $fromName,
                ],
                'to' => [
                    [
                        'email' => $email,
                    ]
                ],
                'subject' => trans('bot.email_verification_subject'),
                'text' => $textBody,
                'html' => $htmlBody,
                'category' => 'Email Verification',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                $this->info("\n✅ ایمیل با موفقیت ارسال شد!");
                $this->line("   Message ID: " . ($responseData['message_ids'][0] ?? 'N/A'));
                
                Log::info('✅ [TestMailtrapAPI] Email sent successfully', [
                    'email' => $email,
                    'message_id' => $responseData['message_ids'][0] ?? null,
                    'inbox_id' => $inboxId
                ]);
                
                $this->newLine();
                $this->info("💡 نکته:");
                $this->info("   ایمیل در Mailtrap Sandbox ذخیره شده است");
                $this->info("   برای مشاهده: https://mailtrap.io/inboxes/{$inboxId}/messages");
                
                return 0;
            } else {
                $this->error("\n❌ خطا در ارسال ایمیل:");
                $this->error("Status Code: " . $response->status());
                $this->error("Response: " . $response->body());
                
                $errorData = $response->json();
                if (isset($errorData['errors'])) {
                    $this->error("\n📋 جزئیات خطا:");
                    foreach ($errorData['errors'] as $error) {
                        $this->error("   - " . ($error['message'] ?? json_encode($error)));
                    }
                }
                
                Log::error('❌ [TestMailtrapAPI] Error sending email', [
                    'email' => $email,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'inbox_id' => $inboxId
                ]);
                
                $this->newLine();
                $this->info("💡 راهنمای رفع مشکل:");
                $this->info("1. بررسی API Token در .env");
                $this->info("2. بررسی Inbox ID");
                $this->info("3. بررسی اینکه Token معتبر است");
                $this->info("4. بررسی لاگ‌ها: storage/logs/laravel.log");
                
                return 1;
            }
        } catch (Exception $e) {
            $this->error("\n❌ خطا در ارسال ایمیل:");
            $this->error($e->getMessage());
            $this->error("\n📋 جزئیات خطا:");
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            
            Log::error('❌ [TestMailtrapAPI] Exception occurred', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}
