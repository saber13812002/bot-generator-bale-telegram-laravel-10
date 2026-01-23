<?php

namespace App\Services\Email;

use App\Interfaces\Services\EmailService;
use App\Services\Email\EmailData;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class AbstractEmailService implements EmailService
{
    protected string $fromAddress;
    protected string $fromName;

    public function __construct()
    {
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
    public function sendVerificationEmail(string $to, string $code, ?string $htmlBody = null, ?string $textBody = null): bool
    {
        $emailData = EmailData::verification($to, $code, $htmlBody, $textBody);
        return $this->sendEmail($emailData);
    }

    /**
     * ارسال ایمیل گزارش هفتگی
     */
    public function sendWeeklyReportEmail(string $to, array $reportData, string $unsubscribeToken, int $templateVersion = 1, ?string $lang = null): bool
    {
        $emailData = EmailData::weeklyReport($to, $reportData, $unsubscribeToken, $templateVersion, $lang);
        return $this->sendEmail($emailData);
    }

    /**
     * ارسال ایمیل انگیزشی
     */
    public function sendMotivationalEmail(string $to, string $message, ?string $subject = null): bool
    {
        $emailData = EmailData::motivational($to, $message, $subject);
        return $this->sendEmail($emailData);
    }

    /**
     * ارسال ایمیل - باید در کلاس فرزند پیاده‌سازی شود
     */
    abstract public function sendEmail(EmailData $emailData): bool;

    /**
     * بررسی اتصال - باید در کلاس فرزند پیاده‌سازی شود
     */
    abstract public function testConnection(): bool;

    /**
     * لاگ موفقیت
     */
    protected function logSuccess(string $to, string $subject, array $context = []): void
    {
        Log::info('✅ [' . static::class . '] Email sent successfully', array_merge([
            'to' => $to,
            'subject' => $subject,
        ], $context));
    }

    /**
     * لاگ خطا
     */
    protected function logError(string $to, string $error, array $context = []): void
    {
        Log::error('❌ [' . static::class . '] Error sending email', array_merge([
            'to' => $to,
            'error' => $error,
        ], $context));
    }

    /**
     * اعتبارسنجی آدرس ایمیل
     */
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * اعتبارسنجی داده‌های ایمیل
     */
    protected function validateEmailData(EmailData $emailData): void
    {
        if (!$this->validateEmail($emailData->to)) {
            throw new Exception("آدرس ایمیل گیرنده معتبر نیست: {$emailData->to}");
        }

        if (empty($emailData->subject)) {
            throw new Exception("موضوع ایمیل نمی‌تواند خالی باشد");
        }

        if (empty($emailData->htmlBody) && empty($emailData->textBody)) {
            throw new Exception("محتوای ایمیل نمی‌تواند خالی باشد");
        }
    }
}
