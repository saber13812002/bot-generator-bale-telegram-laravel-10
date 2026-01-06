<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class PrayerWeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $reportData;
    public string $unsubscribeToken;
    public int $templateVersion;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, string $unsubscribeToken)
    {
        $this->reportData = $reportData;
        $this->unsubscribeToken = $unsubscribeToken;
        
        // انتخاب رندوم از میان 5 تمپلیت مختلف برای جلوگیری از اسپم
        $this->templateVersion = rand(1, 5);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // موضوع‌های متنوع برای جلوگیری از اسپم
        $subjects = [
            '📊 گزارش هفتگی نماز قضا شما',
            '🕌 پیشرفت هفتگی نماز قضا',
            '📈 آمار نماز قضای هفته گذشته',
            '✨ گزارش نمازهای ثبت شده این هفته',
            '🌟 یادآوری هفتگی نماز قضا',
        ];

        $subject = $subjects[array_rand($subjects)];

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // انتخاب تمپلیت مناسب
        $view = "emails.prayer-weekly-report-v{$this->templateVersion}";

        return new Content(
            view: $view,
        );
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        $unsubscribeUrl = url("/email/unsubscribe/{$this->unsubscribeToken}");

        return new Headers(
            // هدر استاندارد List-Unsubscribe برای جلوگیری از اسپم
            text: [
                'List-Unsubscribe' => "<{$unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
