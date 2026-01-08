<?php

namespace App\Services\Email;

class EmailData
{
    public string $to;
    public string $subject;
    public string $htmlBody;
    public ?string $textBody;
    public string $category;
    public ?array $attachments;
    public ?array $customHeaders;

    public function __construct(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        string $category = 'General',
        ?array $attachments = null,
        ?array $customHeaders = null
    ) {
        $this->to = $to;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
        $this->textBody = $textBody ?? strip_tags($htmlBody);
        $this->category = $category;
        $this->attachments = $attachments;
        $this->customHeaders = $customHeaders;
    }

    /**
     * ایجاد EmailData برای ایمیل تایید
     */
    public static function verification(string $to, string $code, ?string $htmlBody = null, ?string $textBody = null): self
    {
        if (!$htmlBody) {
            try {
                $htmlBody = view('emails.email-verification', ['code' => $code])->render();
            } catch (\Exception $e) {
                $htmlBody = "<html><body><h1>کد تایید ایمیل</h1><p>کد تایید شما: <strong>{$code}</strong></p></body></html>";
            }
        }

        if (!$textBody) {
            $textBody = "کد تایید ایمیل شما: {$code}";
        }

        return new self(
            to: $to,
            subject: trans('bot.email_verification_subject'),
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Email Verification'
        );
    }

    /**
     * ایجاد EmailData برای گزارش هفتگی
     */
    public static function weeklyReport(
        string $to,
        array $reportData,
        string $unsubscribeToken,
        int $templateVersion = 1
    ): self {
        $view = "emails.prayer-weekly-report-v{$templateVersion}";
        
        try {
            $htmlBody = view($view, [
                'reportData' => $reportData,
                'unsubscribeToken' => $unsubscribeToken,
                'unsubscribeUrl' => url("/email/unsubscribe/{$unsubscribeToken}")
            ])->render();
        } catch (\Exception $e) {
            $htmlBody = "<html><body><h1>گزارش هفتگی</h1><p>گزارش هفتگی شما آماده است.</p></body></html>";
        }

        $subjects = [
            '📊 گزارش هفتگی نماز قضا شما',
            '🕌 پیشرفت هفتگی نماز قضا',
            '📈 آمار نماز قضای هفته گذشته',
            '✨ گزارش نمازهای ثبت شده این هفته',
            '🌟 یادآوری هفتگی نماز قضا',
        ];

        $subject = $subjects[array_rand($subjects)];

        $textBody = self::generateTextReport($reportData, $unsubscribeToken);

        return new self(
            to: $to,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Weekly Prayer Report',
            customHeaders: [
                'List-Unsubscribe' => "<" . url("/email/unsubscribe/{$unsubscribeToken}") . ">",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ]
        );
    }

    /**
     * ایجاد EmailData برای ایمیل انگیزشی
     */
    public static function motivational(string $to, string $message, ?string $subject = null): self
    {
        $htmlBody = view('emails.motivational', ['message' => $message])->render();

        return new self(
            to: $to,
            subject: $subject ?? trans('bot.motivational_email_subject', [], 'fa'),
            htmlBody: $htmlBody,
            textBody: strip_tags($message),
            category: 'Motivational'
        );
    }

    /**
     * تولید گزارش متنی ساده
     */
    public static function generateTextReport(array $reportData, string $unsubscribeToken): string
    {
        $text = "📊 گزارش هفتگی نماز قضا\n\n";
        $text .= "📅 دوره: {$reportData['period_start']} تا {$reportData['period_end']}\n\n";
        $text .= "📈 آمار کلی:\n";
        $text .= "   ✅ نمازهای ثبت شده: {$reportData['total_prayers']}\n";
        $text .= "   📊 پیشرفت: {$reportData['progress_percentage']}%\n\n";
        
        if (!empty($reportData['prayers_by_type'])) {
            $text .= "📋 تفکیک بر اساس نوع:\n";
            foreach ($reportData['prayers_by_type'] as $type => $count) {
                $text .= "   • {$type}: {$count}\n";
            }
        }
        
        $text .= "\n🔗 برای لغو اشتراک: " . url("/email/unsubscribe/{$unsubscribeToken}");
        
        return $text;
    }
}
