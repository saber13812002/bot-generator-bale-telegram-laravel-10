<?php

namespace App\Console\Commands;

use App\Models\AiJob;
use App\Services\Ai\AiJobDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * بازیابی بعد از restart/مرگ worker (WS-D — acceptance scenario 7).
 *
 * ۱. PROCESSING با heartbeat/started_at کهنه‌تر از stale_after_minutes
 *    (worker وسط کار مُرده، e.g. max-time/timeout cron یا OOM)
 *    → attempt_number را برمی‌شمارد و دوباره queue می‌کند (RETRYING).
 *    سقف attempt دارد؛ بعد از max_attempts → FAILED (بدون infinite loop).
 *
 * ۲. QUEUED/RETRYING/DEFERRED که expires_at گذشته → EXPIRED.
 *    (jobهای قدیمی که دیگر هیچ کاربری منتظرشان نیست)
 *
 * ۳. jobهای QUEUED که رکورد فنی‌شان از جدول jobs گم شده
 *    (e.g. worker وسط reserve مُرده و Laravel job را discard کرد)
 *    → دوباره push می‌شود (بدون تکرار: شرط state=QUEUED + نبود رکورد فنی).
 *
 * اجرا: scheduler هر ۱۰ دقیقه (cron cPanel).
 */
class AiQueueRecoverStale extends Command
{
    protected $signature = 'ai:queue-recover-stale {--limit=100 : سقف job در هر اجرا}';

    protected $description = 'بازیابی jobهای PROCESSING کهنه + EXPIRED کردن قدیمی‌ها + push مجدد گم‌شده‌ها';

    public function handle(): int
    {
        if (! Schema::hasTable('ai_jobs')) {
            $this->error('جدول ai_jobs موجود نیست — php artisan migrate اجرا کنید.');
            return 2;
        }

        $limit         = (int) $this->option('limit');
        $staleMinutes  = (int) config('ai.queue.stale_after_minutes', 20);
        $maxAttempts   = (int) config('ai.queue.max_attempts', 5);

        $requeued = 0;
        $expired  = 0;
        $repushed = 0;

        // ── ۱: PROCESSING کهنه (worker مرده) ──
        $stale = AiJob::staleProcessing($staleMinutes)->limit($limit)->get();

        foreach ($stale as $job) {
            // اگر worker زنده‌ای هنوز heartbeat بفرستد، دست نزن (race)
            $fresh = AiJob::where('job_id', $job->job_id)->lockForUpdate()->first();
            if (! $fresh || $fresh->state !== AiJob::STATE_PROCESSING) {
                continue;
            }

            if ($fresh->attempt_number >= $maxAttempts) {
                $fresh->state        = AiJob::STATE_FAILED;
                $fresh->error_code   = $fresh->error_code ?? 'stale_exhausted';
                $fresh->completed_at = now();
                $fresh->locked_by    = null;
                $fresh->save();
            } else {
                // رکورد فنی کهنه (reserved و گم‌شده) را پاک کن تا unique-lock
                // push جدید را نگیرد، سپس job تازه به صف بفرست
                static::purgeTechnicalRow($fresh->job_id);

                $fresh->markRetry(now()->addSeconds(30), 'stale_worker_lost');
                AiJobDispatcher::pushToQueue($fresh);
            }

            $requeued++;
            $this->line("🩺 recovery: {$fresh->job_id} ({$fresh->feature}) attempt={$fresh->attempt_number}");
        }

        // ── ۲: انقضای jobهای صبرکننده ──
        $expiredJobs = AiJob::expired()->limit($limit)->get(['id']);
        foreach ($expiredJobs as $row) {
            $job = AiJob::where('id', $row->id)->lockForUpdate()->first();
            if ($job && ! $job->isTerminal()) {
                $job->markExpired();
                $expired++;
            }
        }

        // ── ۳: QUEUED بدون رکورد فنی (worker وسط reserve مُرده) ──
        if (Schema::hasTable('jobs')) {
            $queued = AiJob::byState(AiJob::STATE_QUEUED)
                ->where('created_at', '<=', now()->subSeconds(90)) // فقط بعد از grace period
                ->limit($limit)
                ->get(['job_id']);

            foreach ($queued as $row) {
                if (! static::hasTechnicalRow($row->job_id)) {
                    $aiJob = AiJob::where('job_id', $row->job_id)->lockForUpdate()->first();
                    if ($aiJob && $aiJob->state === AiJob::STATE_QUEUED) {
                        AiJobDispatcher::pushToQueue($aiJob);
                        $repushed++;
                        $this->line("🔁 re-push: {$aiJob->job_id} ({$aiJob->feature})");
                    }
                }
            }
        }

        $summary = "recovery: requeued={$requeued} expired={$expired} repushed={$repushed}";
        $this->info("✅ {$summary}");

        if ($requeued > 0 || $repushed > 0) {
            Log::warning('[AiQueue] Stale recovery ran', [
                'requeued' => $requeued,
                'expired'  => $expired,
                'repushed' => $repushed,
            ]);
        }

        return 0;
    }

    /**
     * آیا job فنی (جدول jobs) برای این jobId در صف 'ai' موجود است؟
     * payload فنی Laravel jobId ما را به‌عنوان property serialize می‌کند،
     * پس جست‌وجوی UUID (unique) در متن payload کافی و بدون کاذب است.
     */
    protected static function hasTechnicalRow(string $jobId): bool
    {
        return \DB::table('jobs')
            ->where('queue', config('ai.queue.queue', 'ai'))
            ->where('payload', 'like', '%' . $jobId . '%')
            ->exists();
    }

    /**
     * حذف رکورد فنی کهنه برای jobId (بعد از مرگ worker).
     * رکورد reserved بدون worker تا ابد می‌ماند و unique-lock
     * ShouldBeUnique برای push جدید را می‌گیرد.
     */
    protected static function purgeTechnicalRow(string $jobId): void
    {
        \DB::table('jobs')
            ->where('queue', config('ai.queue.queue', 'ai'))
            ->where('payload', 'like', '%' . $jobId . '%')
            ->delete();
    }
}
