<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Services\Ai\AiErrorRedactor;
use App\Services\AiProviderService;
use App\Services\BookPixel\BookPixelAsrService;
use App\Services\BookPixel\BookPixelOcrService;
use App\Services\GrowthCompanion\GrowthAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * پردازش یک job AI در صف 'ai' (WS-D).
 *
 * خط‌مشی: شکست AI هرگز نباید بیزینس بots را بشکند — همه‌ی خطاها
 * state مناسب را روی ai_jobs می‌گذارند و job فنی را یا retry می‌کنند
 * (با backoff) یا FAILED (dead letter) می‌کنند.
 *
 * ویژگی‌های تاب‌آوری:
 *  - $tries / $timeout / $backoff از config('ai.queue.*')
 *  - heartbeat روی ai_jobs (برای ai:queue-recover-stale)
 *  - check لغو/انقضا قبل از شروع اصلی (cancel-safe)
 *  - attempt_number روی ai_jobs (منابع بیزینسی)
 *  - خطاها با AiErrorRedactor redact شده log می‌شوند (نه‌کردن کلیدها)
 */
class ProcessAiJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    public int $timeout;
    /** @var int[] */
    public array $backoff;

    public string $jobId;

    /** کلید یکتایی صف (unique job → duplicate dispatch در صف فنی هم رد می‌شود) */
    public function uniqueId(): string
    {
        return $this->jobId;
    }

    public function __construct(string $jobId)
    {
        $this->jobId   = $jobId;
        $this->tries   = (int) config('ai.queue.tries', 3);
        $this->timeout = (int) config('ai.queue.timeout', 180);
        $this->backoff = (array) config('ai.queue.backoff', [30, 120, 300]);
    }

    public function handle(): void
    {
        $job = AiJob::where('job_id', $this->jobId)->first();

        // job ردیابی پیدا نشد (e.g. پاک‌سازی شده) → بی‌صدا skip
        if (! $job) {
            Log::info('[AiQueue] Tracking record not found, skipping', ['job_id' => $this->jobId]);
            return;
        }

        // ── state ترمینال قبل از شروع (race با cancel/prune/dead-letter) ──
        // یک retry فنی نباید business jobی که COMPLETED/FAILED/CANCELLED/EXPIRED
        // شده باشد دوباره execute کند.
        if ($job->isTerminal()) {
            Log::info('[AiQueue] Job already terminal, skipping', [
                'job_id' => $this->jobId,
                'state'  => $job->state,
            ]);
            return;
        }

        // ── backoff: اگر due_at هنوز نرسیده (RETRYING/DEFERRED) → release ──
        if ($job->due_at !== null && $job->due_at->isFuture()) {
            $this->release($job->due_at->diffInSeconds(now()) + 1);
            return;
        }

        // ── شروع پردازش ──
        $job->attempt_number = $job->attempt_number + 1;
        $job->markProcessing(gethostname() . ':' . getmypid());

        try {
            $result = $this->execute($job);
        } catch (Throwable $e) {
            $this->handleFailure($job, $e);
            return;
        }

        // ── بازچک terminal (در زمان اجرای طولانی لغو/انقضاشده) ──
        $job->refresh();
        if ($job->isTerminal()) {
            Log::info('[AiQueue] Job became terminal during execution, discarding result', [
                'job_id' => $this->jobId,
                'state'  => $job->state,
            ]);
            return;
        }

        if (! empty($result['error_code'])) {
            // خطای تفسیری (model_not_found, auth_failed, ...) → retry با backoff
            $this->handleFailure(
                $job,
                null,
                $result['error_code'],
                $result['message'] ?? null
            );
            return;
        }

        $job->markCompleted($result['result_reference'] ?? null);

        Log::info('[AiQueue] Job completed', [
            'job_id'   => $this->jobId,
            'feature'  => $job->feature,
            'attempt'  => $job->attempt_number,
        ]);
    }

    /**
     * اجرای واقعی بر اساس feature.
     * هر WS (F/G/H) executor خود را اضافه می‌کند؛ هر executor باید
     * array برگرداند: ['success' => bool, 'error_code' => ?string,
     * 'message' => ?string, 'result_reference' => ?string, ...data]
     *
     * @return array
     */
    protected function execute(AiJob $job): array
    {
        $payload = $job->payload ?? [];

        return match ($job->feature) {
            'llm_chat'         => $this->executeLlmChat($job, $payload),
            'ocr'              => $this->executeOcr($job, $payload),
            'asr'              => $this->executeAsr($job, $payload),
            'growth_companion' => $this->executeGrowthCompanion($job, $payload),
            // 'tts'            => ... (WS-G)
            // 'embedding'      => ... (WS-I)
            default            => [
                'success'      => false,
                'error_code'   => 'feature_not_supported',
                'message'      => "Executor برای feature «{$job->feature}» هنوز پیاده‌سازی نشده است.",
            ],
        };
    }

    protected function executeLlmChat(AiJob $job, array $payload): array
    {
        $service = new AiProviderService();

        $result = $service->chat(
            $payload['prompt'] ?? null,
            $payload['max_tokens'] ?? 500,
            $payload['temperature'] ?? 0.7,
            $payload['timeout'] ?? 120
        );

        if (! $result['success']) {
            // تشخیص error_code از متن خطای redact شده
            $message = $result['response'] ?? '';
            $code    = static::classifyChatError($message);

            return [
                'success'      => false,
                'error_code'   => $code,
                'message'      => $message,
            ];
        }

        // ذخیره پاسخ: اگر caller 'result_store' داده باشد، آن مقدار به‌عنوان
        // result_reference ثبت می‌شود؛ وگرنه نتیجه فقط در payload/usage می‌ماند
        // (WS-H خودش جدول نتیجه دارد).
        $reference = null;

        if (! empty($payload['result_store'])) {
            $reference = $payload['result_store'];
        }

        return [
            'success'          => true,
            'error_code'       => null,
            'result_reference' => $reference,
            'response'         => $result['response'],
            'usage'            => $result['usage'],
        ];
    }

    /**
     * WS-F — OCR اسکن صفحه Book Pixel.
     */
    protected function executeOcr(AiJob $job, array $payload): array
    {
        return app(BookPixelOcrService::class)->execute($job);
    }

    /**
     * WS-G — ASR وویس صفحه Book Pixel (صدا به متن، زبان fa).
     */
    protected function executeAsr(AiJob $job, array $payload): array
    {
        return app(BookPixelAsrService::class)->execute($job);
    }

    /**
     * WS-H — پاسخ دستیار رشدیار به متن آزاد کاربر.
     */
    protected function executeGrowthCompanion(AiJob $job, array $payload): array
    {
        return app(GrowthAssistantService::class)->execute($job);
    }

    protected static function classifyChatError(string $message): string
    {
        $m = mb_strtolower($message);

        if (str_contains($m, '401') || str_contains($m, 'token_not_found') || str_contains($m, 'unauthorized')) {
            return 'auth_failed';
        }
        if (str_contains($m, '404') || str_contains($m, 'model_not_found') || str_contains($m, 'does not exist')) {
            return 'model_not_found';
        }
        if (str_contains($m, '429') || str_contains($m, 'rate limit') || str_contains($m, 'too many')) {
            return 'rate_limited';
        }
        if (str_contains($m, '503') || str_contains($m, 'unavailable') || str_contains($m, 'timeout')) {
            return 'provider_unavailable';
        }

        return 'provider_error';
    }

    /**
     * مدیریت شکست: retry با backoff تا سقف attempts، بعد dead letter (FAILED).
     * اگر retry_after > expires_at → مستقیم FAILED (بی‌معنایی است منتظر ماند).
     */
    protected function handleFailure(AiJob $job, ?Throwable $e, ?string $errorCode = null, ?string $message = null): void
    {
        $errorCode = $errorCode
            ?? ($e ? static::classifyException($e) : 'unknown');

        $errorText = $e ? $e->getMessage() : ($message ?? 'unknown error');
        $errorText = AiErrorRedactor::redact($errorText);

        Log::warning('[AiQueue] Job failed', [
            'job_id'    => $this->jobId,
            'feature'   => $job->feature,
            'attempt'   => $job->attempt_number,
            'error'     => $errorCode,
            'detail'    => $errorText,
        ]);

        $maxAttempts = (int) config('ai.queue.max_attempts', 5);
        $backoff     = $this->backoff;
        $backoffSec  = $backoff[min($job->attempt_number - 1, count($backoff) - 1)] ?? 300;
        $retryAt     = now()->addSeconds($backoffSec);

        $isLast = $job->attempt_number >= $maxAttempts;
        $pastExpiry = $job->expires_at !== null && $retryAt->greaterThan($job->expires_at);

        if ($isLast || $pastExpiry) {
            // dead letter
            $job->error_code   = $errorCode;
            $job->completed_at = now();
            $job->locked_by    = null;
            $job->state        = AiJob::STATE_FAILED;
            $job->save();

            // نوتیفیکیشن به ادمین (بدون کلید) — فقط dead letter
            $msg = "🟠 AI Job DEAD LETTER\n"
                 . "Job: {$job->job_id}\n"
                 . "Feature: {$job->feature}\n"
                 . "Attempts: {$job->attempt_number}\n"
                 . "Error: {$errorCode} — {$errorText}";

            try {
                AiProviderService::notifyAdmin($msg);
            } catch (Throwable $notifyError) {
                Log::error('[AiQueue] notifyAdmin failed', ['error' => $notifyError->getMessage()]);
            }
        } else {
            $job->markRetry($retryAt, $errorCode);
        }

        // اگر retry بود → job فنی دوباره queue شود (available_at = retryAt)
        if ($job->state === AiJob::STATE_RETRYING) {
            $this->release((int) $backoffSec);
        } elseif ($e !== null && $job->state === AiJob::STATE_FAILED) {
            // job فنی هم mark fail شود (وارد failed_jobs شود) — فقط exception واقعی
            throw $e;
        }
    }

    protected static function classifyException(Throwable $e): string
    {
        $m = mb_strtolower($e->getMessage());

        if (str_contains($m, 'timed out') || str_contains($m, 'timeout')) {
            return 'timeout';
        }
        if (str_contains($m, 'ssl') || str_contains($m, 'certificate') || str_contains($m, 'tls')) {
            return 'tls_error';
        }
        if (str_contains($m, 'connection') || str_contains($m, 'curl')) {
            return 'connection_error';
        }

        return 'exception';
    }

    /**
     * failed job (بعد از tries exhausted در level فنی) — dead letter بیزینسی.
     * توسط Laravel framework صدا زده می‌شود.
     */
    public function failed(?Throwable $exception): void
    {
        $job = AiJob::where('job_id', $this->jobId)->first();

        if (! $job || in_array($job->state, [AiJob::STATE_FAILED, AiJob::STATE_CANCELLED], true)) {
            return;
        }

        $code = $exception ? static::classifyException($exception) : 'exhausted';
        $job->error_code   = $code;
        $job->completed_at = now();
        $job->state        = AiJob::STATE_FAILED;
        $job->locked_by    = null;
        $job->save();

        Log::error('[AiQueue] Job exhausted (framework level)', [
            'job_id' => $this->jobId,
            'feature' => $job->feature,
            'error'  => $code,
        ]);
    }
}
