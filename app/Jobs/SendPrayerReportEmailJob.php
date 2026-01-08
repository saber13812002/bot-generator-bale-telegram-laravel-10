<?php

namespace App\Jobs;

use App\Mail\PrayerWeeklyReportMail;
use App\Models\EmailReportQueue;
use App\Services\MailtrapEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class SendPrayerReportEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $queueId;
    protected array $reportData;
    protected string $email;
    protected string $unsubscribeToken;

    /**
     * تعداد دفعات تلاش مجدد
     */
    public int $tries = 3;

    /**
     * تعداد ثانیه انتظار بین تلاش‌های مجدد
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $queueId,
        array $reportData,
        string $email,
        string $unsubscribeToken
    ) {
        $this->queueId = $queueId;
        $this->reportData = $reportData;
        $this->email = $email;
        $this->unsubscribeToken = $unsubscribeToken;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('📧 [SendPrayerReportEmailJob] Starting to send email', [
            'queue_id' => $this->queueId,
            'email' => $this->email
        ]);

        try {
            // تولید محتوای ایمیل
            $mailable = new PrayerWeeklyReportMail($this->reportData, $this->unsubscribeToken);
            $envelope = $mailable->envelope();
            $subject = $envelope->subject;
            
            // تولید HTML از view
            $view = "emails.prayer-weekly-report-v{$mailable->templateVersion}";
            $htmlBody = view($view, [
                'reportData' => $this->reportData,
                'unsubscribeToken' => $this->unsubscribeToken,
                'unsubscribeUrl' => url("/email/unsubscribe/{$this->unsubscribeToken}")
            ])->render();
            
            // تولید متن ساده
            $textBody = $this->generateTextReport($this->reportData);

            // ارسال ایمیل با Mailtrap API
            $mailtrapService = new MailtrapEmailService();
            $mailtrapService->sendEmail(
                $this->email,
                $subject,
                $htmlBody,
                $textBody,
                'Weekly Prayer Report'
            );

            // به‌روزرسانی وضعیت صف
            $queue = EmailReportQueue::find($this->queueId);
            if ($queue) {
                $queue->markAsSent();
            }

            Log::info('✅ [SendPrayerReportEmailJob] Email sent successfully via Mailtrap', [
                'queue_id' => $this->queueId,
                'email' => $this->email
            ]);
        } catch (Exception $e) {
            Log::error('❌ [SendPrayerReportEmailJob] Error sending email', [
                'queue_id' => $this->queueId,
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // به‌روزرسانی وضعیت خطا
            $queue = EmailReportQueue::find($this->queueId);
            if ($queue) {
                $queue->markAsFailed($e->getMessage());
            }

            throw $e; // دوباره throw می‌کنیم تا Job مجدداً تلاش کند
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('❌ [SendPrayerReportEmailJob] Job failed after all retries', [
            'queue_id' => $this->queueId,
            'email' => $this->email,
            'error' => $exception->getMessage()
        ]);

        // علامت‌گذاری نهایی به عنوان ناموفق
        $queue = EmailReportQueue::find($this->queueId);
        if ($queue) {
            $queue->markAsFailed('Failed after ' . $this->tries . ' attempts: ' . $exception->getMessage());
        }
    }

    /**
     * تولید گزارش متنی ساده
     */
    protected function generateTextReport(array $reportData): string
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
        
        $text .= "\n🔗 برای لغو اشتراک: " . url("/email/unsubscribe/{$this->unsubscribeToken}");
        
        return $text;
    }
}
