<?php

namespace App\Services;

use App\Services\MailtrapEmailServiceImpl;

/**
 * @deprecated استفاده از MailtrapEmailServiceImpl توصیه می‌شود
 * این کلاس برای سازگاری با کدهای قدیمی نگه داشته شده است
 */
class MailtrapEmailService
{
    protected MailtrapEmailServiceImpl $service;

    public function __construct()
    {
        $this->service = new MailtrapEmailServiceImpl();
    }

    /**
     * ارسال ایمیل تایید
     */
    public function sendVerificationEmail(string $to, string $code, ?string $htmlBody = null, ?string $textBody = null): bool
    {
        return $this->service->sendVerificationEmail($to, $code, $htmlBody, $textBody);
    }

    /**
     * ارسال ایمیل عمومی
     */
    public function sendEmail(string $to, string $subject, string $htmlBody, ?string $textBody = null, string $category = 'General'): bool
    {
        $emailData = new \App\Services\Email\EmailData($to, $subject, $htmlBody, $textBody, $category);
        return $this->service->sendEmail($emailData);
    }
}
