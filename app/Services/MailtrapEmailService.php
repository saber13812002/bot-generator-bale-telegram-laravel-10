<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MailtrapEmailService
{
    protected string $apiToken;
    protected ?string $inboxId;
    protected bool $useSandbox;
    protected string $fromAddress;
    protected string $fromName;

    public function __construct()
    {
        $this->apiToken = trim(env('MAILTRAP_API_TOKEN', ''));
        $this->inboxId = env('MAILTRAP_INBOX_ID');
        $this->useSandbox = env('MAILTRAP_USE_SANDBOX', true);
        $this->fromAddress = env('MAIL_FROM_ADDRESS', 'hello@pardisania.ir');
        $this->fromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Bots'));

        // بررسی اعتبار آدرس ایمیل
        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->fromAddress = 'hello@pardisania.ir';
        }
    }

    /**
     * ارسال ایمیل تایید
     */
    public function sendVerificationEmail(string $to, string $code, string $htmlBody = null, string $textBody = null): bool
    {
        if (!$this->apiToken) {
            Log::error('❌ [MailtrapEmailService] API Token تنظیم نشده است');
            throw new Exception('MAILTRAP_API_TOKEN در .env تنظیم نشده است');
        }

        // بررسی فاصله در Token
        if (str_contains($this->apiToken, ' ')) {
            Log::error('❌ [MailtrapEmailService] API Token دارای فاصله است');
            throw new Exception('API Token نباید فاصله داشته باشد');
        }

        // تنظیم URL و Header
        if ($this->useSandbox) {
            if (!$this->inboxId) {
                throw new Exception('MAILTRAP_INBOX_ID برای Sandbox ضروری است');
            }
            $apiUrl = "https://sandbox.api.mailtrap.io/api/send/{$this->inboxId}";
            $authHeader = 'Api-Token';
            $authValue = $this->apiToken;
        } else {
            $apiUrl = "https://send.api.mailtrap.io/api/send";
            $authHeader = 'Authorization';
            $authValue = "Bearer {$this->apiToken}";
        }

        // تولید محتوا
        if (!$htmlBody) {
            try {
                $htmlBody = view('emails.email-verification', ['code' => $code])->render();
            } catch (Exception $e) {
                $htmlBody = "<html><body><h1>کد تایید ایمیل</h1><p>کد تایید شما: <strong>{$code}</strong></p></body></html>";
            }
        }

        if (!$textBody) {
            $textBody = "کد تایید ایمیل شما: {$code}";
        }

        $subject = trans('bot.email_verification_subject');

        try {
            $response = Http::withHeaders([
                $authHeader => $authValue,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($apiUrl, [
                'from' => [
                    'email' => $this->fromAddress,
                    'name' => $this->fromName,
                ],
                'to' => [
                    [
                        'email' => $to,
                    ]
                ],
                'subject' => $subject,
                'text' => $textBody,
                'html' => $htmlBody,
                'category' => 'Email Verification',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = $responseData['message_ids'][0] ?? null;

                Log::info('✅ [MailtrapEmailService] Email sent successfully', [
                    'to' => $to,
                    'message_id' => $messageId,
                    'mode' => $this->useSandbox ? 'sandbox' : 'transactional',
                    'inbox_id' => $this->inboxId
                ]);

                return true;
            } else {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0] ?? $response->body();

                Log::error('❌ [MailtrapEmailService] Error sending email', [
                    'to' => $to,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'mode' => $this->useSandbox ? 'sandbox' : 'transactional',
                ]);

                throw new Exception("خطا در ارسال ایمیل: {$errorMessage}");
            }
        } catch (Exception $e) {
            Log::error('❌ [MailtrapEmailService] Exception occurred', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * ارسال ایمیل عمومی
     */
    public function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = null, string $category = 'General'): bool
    {
        if (!$this->apiToken) {
            throw new Exception('MAILTRAP_API_TOKEN در .env تنظیم نشده است');
        }

        // تنظیم URL و Header
        if ($this->useSandbox) {
            if (!$this->inboxId) {
                throw new Exception('MAILTRAP_INBOX_ID برای Sandbox ضروری است');
            }
            $apiUrl = "https://sandbox.api.mailtrap.io/api/send/{$this->inboxId}";
            $authHeader = 'Api-Token';
            $authValue = $this->apiToken;
        } else {
            $apiUrl = "https://send.api.mailtrap.io/api/send";
            $authHeader = 'Authorization';
            $authValue = "Bearer {$this->apiToken}";
        }

        if (!$textBody) {
            $textBody = strip_tags($htmlBody);
        }

        try {
            $response = Http::withHeaders([
                $authHeader => $authValue,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($apiUrl, [
                'from' => [
                    'email' => $this->fromAddress,
                    'name' => $this->fromName,
                ],
                'to' => [
                    [
                        'email' => $to,
                    ]
                ],
                'subject' => $subject,
                'text' => $textBody,
                'html' => $htmlBody,
                'category' => $category,
            ]);

            if ($response->successful()) {
                Log::info('✅ [MailtrapEmailService] Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'mode' => $this->useSandbox ? 'sandbox' : 'transactional',
                ]);

                return true;
            } else {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0] ?? $response->body();

                Log::error('❌ [MailtrapEmailService] Error sending email', [
                    'to' => $to,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                ]);

                throw new Exception("خطا در ارسال ایمیل: {$errorMessage}");
            }
        } catch (Exception $e) {
            Log::error('❌ [MailtrapEmailService] Exception occurred', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
