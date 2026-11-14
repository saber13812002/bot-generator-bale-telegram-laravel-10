<?php

namespace App\Services\Ai;

use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * دیسپاچر کارهای AI (WS-D) — نقطه‌ی واحد ارسال به صف.
 *
 * مسئولیت‌ها:
 *  1. ایجاد رکورد ai_jobs به‌صورت IDEMPOTENT (idempotency_key UNIQUE)
 *     → دو dispatch هم‌زمان/تکراری فقط یک job می‌سازند (acceptance scenario 4)
 *  2. اعمال سیاست زمان (Asia/Tehran):
 *       - Pro: همیشه فوری (priority=pro)
 *       - عادی 00–09 و 14–24: فوری (priority=regular)
 *       - عادی 09–14: DEFERRED تا 14:00 (flush روزانه)
 *  3. ارسال job فنی به connection 'database' / queue 'ai' (صریح و مستقل
 *     از QUEUE_CONNECTION پیش‌فرض که sync است)
 *  4. محاسبه expires_at (سقف کلی عمر) و due_at (برای deferred/retry)
 *
 * نکته‌ی معماری: اگر ارسال job فنی خطا بدهد، رکورد ai_jobs در state
 * مناسب (QUEUED/DEFERRED) می‌ماند تا watchdog recover آن را دوباره
 * به صف برساند (restart recovery).
 */
class AiJobDispatcher
{
    /**
     * ارسال یک کار AI به صف.
     *
     * @param string $feature      llm_chat|ocr|asr|tts|embedding|growth_companion
     * @param array  $payload      داده‌های قابل serialize برای executor
     * @param array  $options      [
     *   'bot_id' => int, 'customer_id' => int, 'user_id' => int,
     *   'is_pro' => bool, 'priority' => 'regular'|'low'|'pro',
     *   'provider' => ?string, 'model' => ?string,
     *   'due_at' => ?\DateTimeInterface (override), 'expires_hours' => ?int
     * ]
     *
     * @return array{job: AiJob, created: bool, deferred: bool}
     *   created=false یعنی job قبلاً با همین idempotency_key ارسال شده
     *   (تکرار → همان رکورد برگردانده می‌شود، job جدید ساخته نمی‌شود).
     */
    public static function dispatch(string $feature, array $payload, array $options = []): array
    {
        $botId        = isset($options['bot_id']) ? (int) $options['bot_id'] : null;
        $customerId   = isset($options['customer_id']) ? (int) $options['customer_id'] : null;
        $userId       = isset($options['user_id']) ? (int) $options['user_id'] : null;
        $isPro        = (bool) ($options['is_pro'] ?? false);
        $providerName = $options['provider'] ?? 'litellm';
        $model        = $options['model'] ?? null;

        $key = AiJob::idempotencyKeyFor($feature, $botId, $customerId, $userId, $payload);

        // ── ۱/۴: ایجاد idempotent رکورد ردیابی (race-safe روی UNIQUE) ──
        $job = DB::transaction(function () use ($feature, $key, $payload, $botId, $customerId, $userId, $isPro, $providerName, $model) {
            $existing = AiJob::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            return AiJob::create([
                'job_id'          => Str::uuid()->toString(),
                'idempotency_key' => $key,
                'bot_id'          => $botId,
                'customer_id'     => $customerId,
                'user_id'         => $userId,
                'feature'         => $feature,
                'priority'        => $isPro ? AiJob::PRIORITY_PRO : AiJob::PRIORITY_REGULAR,
                'state'           => AiJob::STATE_PENDING,
                'provider'        => $providerName,
                'model'           => $model,
                'payload'         => $payload,
            ]);
        });

        $created = $job->wasRecentlyCreated;

        // ── ۲/۴: سیاست زمان — فقط برای jobهای تازه (deferred روی job قدیمی بی‌معناست) ─
        $deferred = false;

        if ($created) {
            $now = now();
            $expiresAt = $now->copy()->addHours(
                (int) ($options['expires_hours'] ?? config('ai.queue.expires_after_hours', 24))
            );

            $dueAt = $options['due_at'] ?? null;

            // Pro همیشه فوری؛ عادی بین 09–14 Tehran → DEFERRED تا 14:00
            if (! $isPro && $dueAt === null && static::isRegularDeferredWindow()) {
                $dueAt   = static::nextFlushTime();
                $deferred = true;
            }

            $job->forceFill([
                'expires_at' => $expiresAt,
            ]);

            if ($dueAt) {
                $job->due_at = $dueAt;
                $job->state  = AiJob::STATE_DEFERRED;

                if ($job->priority === AiJob::PRIORITY_REGULAR) {
                    $job->priority = AiJob::PRIORITY_DEFERRED;
                }
            } else {
                $job->state = AiJob::STATE_QUEUED;
            }

            $job->save();
        }

        // ── ۳/۴: ارسال job فنی (فقط وقتی job هنوز در صف فنی نیست) ──
        // jobهای DEFERRED الان در صف فنی نمی‌روند؛ flush command (14:00 + هر 10 دقیقه)
        // آن‌ها را با due_at گذشته به state QUEUED می‌آورد و dispatch می‌کند.
        if ($created && $job->state === AiJob::STATE_QUEUED) {
            static::pushToQueue($job);
        }

        return [
            'job'      => $job->fresh(),
            'created'  => $created,
            'deferred' => $deferred,
        ];
    }

    /**
     * ارسال job فنی به صف 'ai' روی connection 'database'.
     * اگر push شکست بخورد، رکورد در state فعلی می‌ماند و
     * ai:queue-recover-stale آن را دوباره push می‌کند.
     */
    public static function pushToQueue(AiJob $job): void
    {
        try {
            ProcessAiJob::dispatch($job->job_id)
                ->onConnection(config('ai.queue.connection', 'database'))
                ->onQueue(config('ai.queue.queue', 'ai'));

            if ($job->state === AiJob::STATE_PENDING || $job->state === AiJob::STATE_DEFERRED) {
                $job->markQueued();
            }
        } catch (\Throwable $e) {
            // job فنی نرسید — رکورد ردیابی سالم است؛ recover دوباره امتحان می‌کند
            Log::warning('[AiQueue] Failed to push job to queue', [
                'job_id'  => $job->job_id,
                'feature' => $job->feature,
                'error'   => AiErrorRedactor::redact($e->getMessage()),
            ]);
        }
    }

    /**
     * آیا الان در پنجره‌ی DEFERRED برای کاربران عادی هستیم؟ (09–14 Asia/Tehran)
     * Pro همیشه فوری است (در dispatch بررسی می‌شود).
     */
    public static function isRegularDeferredWindow(?\DateTimeInterface $now = null): bool
    {
        $tz   = new \DateTimeZone('Asia/Tehran');
        $time = ($now ?? now())->setTimezone($tz)->format('H:i');

        return $time >= '09:00' && $time < '14:00';
    }

    /**
     * زمان flush بعدی (14:00 Asia/Tehran = 10:30 UTC).
     * اگر الان قبل از 09:00 باشیم، flush بعدی امروز 14:00 است
     * (برای due_at override در آینده).
     */
    public static function nextFlushTime(): \DateTimeInterface
    {
        $tz = new \DateTimeZone('Asia/Tehran');

        // از now() (Carbon) استفاده می‌کنیم تا test-now در تست‌ها رعایت شود
        $nowTehran = now()->copy()->setTimezone($tz);
        $flush     = \Illuminate\Support\Carbon::create(
            (int) $nowTehran->format('Y'),
            (int) $nowTehran->format('m'),
            (int) $nowTehran->format('d'),
            14, 0, 0,
            $tz
        );

        if ($nowTehran->format('H:i') >= '14:00') {
            $flush->addDay();
        }

        // تبدیل لحظه به UTC (تاریخ‌های DB در UTC نگهداری می‌شوند)
        return $flush->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * لغو یک job (قبل از شروع). اگر PROCESSING شد، heartbeat بعدی
     * در ProcessAiJob قبل از اجرای اصلی استेट CANCELLED را ببیند و skip کند.
     */
    public static function cancel(string $jobId): bool
    {
        $job = AiJob::where('job_id', $jobId)->first();

        if (! $job || $job->isTerminal()) {
            return false;
        }

        $job->markCancelled();

        return true;
    }
}
