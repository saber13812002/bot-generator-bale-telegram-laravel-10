<?php

namespace App\Jobs;

use App\Mail\PrayerWeeklyReportMail;
use App\Models\EmailReportQueue;
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
            // ارسال ایمیل
            Mail::to($this->email)->send(
                new PrayerWeeklyReportMail($this->reportData, $this->unsubscribeToken)
            );

            // به‌روزرسانی وضعیت صف
            $queue = EmailReportQueue::find($this->queueId);
            if ($queue) {
                $queue->markAsSent();
            }

            Log::info('✅ [SendPrayerReportEmailJob] Email sent successfully', [
                'queue_id' => $this->queueId,
                'email' => $this->email
            ]);
        } catch (Exception $e) {
            Log::error('❌ [SendPrayerReportEmailJob] Error sending email', [
                'queue_id' => $this->queueId,
                'email' => $this->email,
                'error' => $e->getMessage()
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
}
