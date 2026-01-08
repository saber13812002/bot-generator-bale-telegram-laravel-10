<?php

namespace App\Interfaces\Services;

use App\Services\Email\EmailData;

interface EmailService
{
    /**
     * ارسال ایمیل تایید
     *
     * @param string $to آدرس ایمیل گیرنده
     * @param string $code کد تایید 6 رقمی
     * @param string|null $htmlBody محتوای HTML (اختیاری)
     * @param string|null $textBody محتوای متنی (اختیاری)
     * @return bool
     * @throws \Exception
     */
    public function sendVerificationEmail(string $to, string $code, ?string $htmlBody = null, ?string $textBody = null): bool;

    /**
     * ارسال ایمیل گزارش هفتگی
     *
     * @param string $to آدرس ایمیل گیرنده
     * @param array $reportData داده‌های گزارش
     * @param string $unsubscribeToken توکن لغو اشتراک
     * @param int $templateVersion نسخه تمپلیت (1-5)
     * @return bool
     * @throws \Exception
     */
    public function sendWeeklyReportEmail(string $to, array $reportData, string $unsubscribeToken, int $templateVersion = 1): bool;

    /**
     * ارسال ایمیل انگیزشی
     *
     * @param string $to آدرس ایمیل گیرنده
     * @param string $message پیام انگیزشی
     * @param string|null $subject موضوع ایمیل (اختیاری)
     * @return bool
     * @throws \Exception
     */
    public function sendMotivationalEmail(string $to, string $message, ?string $subject = null): bool;

    /**
     * ارسال ایمیل عمومی
     *
     * @param EmailData $emailData داده‌های ایمیل
     * @return bool
     * @throws \Exception
     */
    public function sendEmail(EmailData $emailData): bool;

    /**
     * بررسی اتصال و صحت تنظیمات
     *
     * @return bool
     * @throws \Exception
     */
    public function testConnection(): bool;
}
