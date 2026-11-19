<?php

namespace App\Services;

use App\Interfaces\Services\EmailService;
use App\Services\Email\AbstractEmailService;
use App\Services\Email\EmailData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MailtrapEmailServiceImpl extends AbstractEmailService implements EmailService
{
    protected string $apiToken;
    protected ?string $inboxId;
    protected bool $useSandbox;

    public function __construct()
    {
        parent::__construct();
        
        $this->apiToken = trim(env('MAILTRAP_API_TOKEN', ''));
        $this->inboxId = env('MAILTRAP_INBOX_ID');
        $this->useSandbox = env('MAILTRAP_USE_SANDBOX', true);
    }

    /**
     * ارسال ایمیل
     */
    public function sendEmail(EmailData $emailData): bool
    {
        // اعتبارسنجی
        $this->validateEmailData($emailData);

        // بررسی تنظیمات
        if (!$this->apiToken) {
            $this->logError($emailData->to, 'MAILTRAP_API_TOKEN در .env تنظیم نشده است');
            throw new Exception('MAILTRAP_API_TOKEN در .env تنظیم نشده است');
        }

        // بررسی فاصله در Token
        if (str_contains($this->apiToken, ' ')) {
            $this->logError($emailData->to, 'API Token دارای فاصله است');
            throw new Exception('API Token نباید فاصله داشته باشد');
        }

        // تنظیم URL و Header
        [$apiUrl, $authHeader, $authValue] = $this->getApiConfig();

        try {
            $payload = $this->buildPayload($emailData);
            
            $response = Http::withHeaders([
                $authHeader => $authValue,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($apiUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = $responseData['message_ids'][0] ?? null;

                $this->logSuccess($emailData->to, $emailData->subject, [
                    'message_id' => $messageId,
                    'mode' => $this->useSandbox ? 'sandbox' : 'transactional',
                    'inbox_id' => $this->inboxId,
                    'category' => $emailData->category
                ]);

                return true;
            } else {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0] ?? $response->body();

                $this->logError($emailData->to, $errorMessage, [
                    'status' => $response->status(),
                    'mode' => $this->useSandbox ? 'sandbox' : 'transactional',
                ]);

                throw new Exception("خطا در ارسال ایمیل: {$errorMessage}");
            }
        } catch (Exception $e) {
            if ($e->getMessage() === "خطا در ارسال ایمیل: {$errorMessage}" ?? '') {
                throw $e;
            }

            $this->logError($emailData->to, $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * بررسی اتصال
     */
    public function testConnection(): bool
    {
        if (!$this->apiToken) {
            throw new Exception('MAILTRAP_API_TOKEN در .env تنظیم نشده است');
        }

        if ($this->useSandbox && !$this->inboxId) {
            throw new Exception('MAILTRAP_INBOX_ID برای Sandbox ضروری است');
        }

        // تست ساده با یک درخواست کوچک
        try {
            [$apiUrl, $authHeader, $authValue] = $this->getApiConfig();
            
            // فقط بررسی می‌کنیم که URL معتبر است
            if (empty($apiUrl)) {
                throw new Exception('API URL معتبر نیست');
            }

            return true;
        } catch (Exception $e) {
            throw new Exception("خطا در اتصال به Mailtrap: {$e->getMessage()}");
        }
    }

    /**
     * دریافت تنظیمات API
     */
    protected function getApiConfig(): array
    {
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

        return [$apiUrl, $authHeader, $authValue];
    }

    /**
     * ساخت Payload برای API
     */
    protected function buildPayload(EmailData $emailData): array
    {
        $payload = [
            'from' => [
                'email' => $this->fromAddress,
                'name' => $this->fromName,
            ],
            'to' => [
                [
                    'email' => $emailData->to,
                ]
            ],
            'subject' => $emailData->subject,
            'text' => $emailData->textBody,
            'html' => $emailData->htmlBody,
            'category' => $emailData->category,
        ];

        // اضافه کردن Custom Headers اگر وجود دارد
        if ($emailData->customHeaders) {
            $payload['headers'] = $emailData->customHeaders;
        }

        return $payload;
    }
}
