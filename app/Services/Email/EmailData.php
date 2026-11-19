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
        int $templateVersion = 1,
        ?string $lang = null
    ): self {
        // تنظیم زبان
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $view = "emails.prayer-weekly-report-v{$templateVersion}";
        
        // ساخت URL گزارش وب از reportData
        $reportUrl = $reportData['report_url'] ?? null;
        
        // لاگ برای دیباگ
        if (empty($reportUrl)) {
            \Log::warning('⚠️ [EmailData::weeklyReport] report_url در reportData وجود ندارد', [
                'report_data_keys' => array_keys($reportData),
                'has_report_url' => isset($reportData['report_url']),
            ]);
        } else {
            \Log::info('✅ [EmailData::weeklyReport] report_url موجود است', [
                'report_url' => $reportUrl,
            ]);
        }
        
        try {
            $htmlBody = view($view, [
                'reportData' => $reportData,
                'unsubscribeToken' => $unsubscribeToken,
                'unsubscribeUrl' => url("/email/unsubscribe/{$unsubscribeToken}"),
                'reportUrl' => $reportUrl,
                'hasEstimate' => $reportData['has_estimate'] ?? false,
                'lang' => $lang ?? app()->getLocale(),
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
        
        if (isset($reportData['total_rakats'])) {
            $text .= "   📊 تعداد رکعات: {$reportData['total_rakats']}\n";
        }
        
        $text .= "   📊 پیشرفت: {$reportData['progress_percentage']}%\n\n";
        
        // نمودار 7 روز گذشته (متن)
        if (!empty($reportData['daily_stats'])) {
            $text .= "📊 فعالیت 7 روز گذشته:\n";
            foreach ($reportData['daily_stats'] as $day) {
                $bar = str_repeat('█', min($day['rakats'], 20)); // حداکثر 20 کاراکتر
                $text .= "   {$day['day_name']}: {$bar} {$day['rakats']} رکعت\n";
            }
            $text .= "\n";
        }
        
        // مقایسه با هفته گذشته
        if (!empty($reportData['weekly_comparison'])) {
            $change = $reportData['weekly_comparison']['change_vs_last_week'] ?? 0;
            $text .= "📈 مقایسه:\n";
            $text .= "   این هفته: {$reportData['weekly_comparison']['current']['rakats']} رکعت\n";
            $text .= "   هفته گذشته: {$reportData['weekly_comparison']['last_week']['rakats']} رکعت\n";
            if ($change > 0) {
                $text .= "   ⬆️ {$change} رکعت بیشتر از هفته گذشته!\n";
            } elseif ($change < 0) {
                $text .= "   ⬇️ " . abs($change) . " رکعت کمتر از هفته گذشته\n";
            }
            $text .= "\n";
        }
        
        // اطلاعات پیک
        if (!empty($reportData['peak_activity']) && !empty($reportData['completion_time'])) {
            $text .= "🔥 بهترین عملکرد:\n";
            $text .= "   در {$reportData['peak_activity']['month_label']}: {$reportData['peak_activity']['rakats']} رکعت\n";
            if ($reportData['completion_time']['months'] > 0) {
                $text .= "   ⏱️ با همان سرعت: " . number_format($reportData['completion_time']['months'], 1) . " ماه دیگر تمام می‌شود!\n";
            }
            $text .= "\n";
        }
        
        // Top 10
        if (!empty($reportData['top_10_users'])) {
            $topRakats = $reportData['top_10_users'][0]['rakats'] ?? 0;
            $userRakats = $reportData['total_rakats'] ?? 0;
            $text .= "🏆 رتبه‌بندی:\n";
            $text .= "   برترین: {$topRakats} رکعت\n";
            $text .= "   شما: {$userRakats} رکعت\n";
            if ($topRakats > $userRakats) {
                $text .= "   💪 " . ($topRakats - $userRakats) . " رکعت دیگر تا رتبه اول!\n";
            } else {
                $text .= "   🌟 شما در بین برترین‌ها هستید!\n";
            }
            $text .= "\n";
        }
        
        if (!empty($reportData['prayers_by_type'])) {
            $text .= "📋 تفکیک بر اساس نوع:\n";
            foreach ($reportData['prayers_by_type'] as $type => $count) {
                $text .= "   • {$type}: {$count}\n";
            }
            $text .= "\n";
        }
        
        // پیام انگیزشی
        if (!empty($reportData['motivational_message'])) {
            $text .= "\n" . $reportData['motivational_message'] . "\n\n";
        }
        
        // لینک به صفحه گزارش وب
        if (!empty($reportData['report_url'])) {
            $text .= "\n🌐 برای مشاهده گزارش کامل با نمودارهای تعاملی:\n";
            $text .= "   " . $reportData['report_url'] . "\n\n";
            $text .= "📊 در صفحه وب می‌توانید:\n";
            $text .= "   • نمودار 7 روز گذشته را ببینید\n";
            $text .= "   • مقایسه با هفته‌های گذشته را مشاهده کنید\n";
            $text .= "   • آمار Top 10 کاربران را ببینید\n";
            $text .= "   • جزئیات بیشتری از پیشرفت خود دریافت کنید\n\n";
        }
        
        $text .= "🔗 برای لغو اشتراک: " . url("/email/unsubscribe/{$unsubscribeToken}");
        
        return $text;
    }
}
