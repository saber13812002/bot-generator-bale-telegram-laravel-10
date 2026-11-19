<?php

namespace App\Console\Commands;

use App\Models\AiJob;
use App\Services\Ai\AiJobDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flush jobهای DEFERRED که زمان آن‌ها رسیده (WS-D).
 *
 * سیاست زمان (Asia/Tehran):
 *  - 09:00 → jobهای جدید عادی DEFERRED می‌شوند (due_at = 14:00)
 *  - 14:00 → این command آن‌ها را با اولویت به صف می‌رساند
 *  - flush روزانه دقیقاً 14:00 (10:30 UTC) + اجرا هر ۱۰ دقیقه برای
 *    تضمین «بدون تکرار و بدون جاافتادن» (acceptance scenario 1 و 4)
 *
 * بدون تکرار: فقط jobهایی با state=DEFERRED و due_at <= now انتخاب می‌شوند
 * و transactional با lockForUpdate به QUEUED تبدیل می‌شوند → حتی اجرای
 * هم‌زمان دو worker/دو cron رکوردی را دو بار push نمی‌کند.
 */
class AiQueueFlushDeferred extends Command
{
    protected $signature = 'ai:queue-flush-deferred {--limit=200 : سقف تعداد job در هر اجرا}';

    protected $description = 'ارسال jobهای DEFERRED سررسیدگشته به صف AI (14:00 Tehran + هر 10 دقیقه)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $flushed = 0;

        while (true) {
            $jobs = AiJob::query()
                ->byState(AiJob::STATE_DEFERRED)
                ->where(function ($q) {
                    $q->whereNull('due_at')->orWhere('due_at', '<=', now());
                })
                // به ترتیب اولویت بعداً FIFO (pro اول — برای jobهای pro که
                // به‌یادگار DEFERRED مانده‌اند؛ عادی‌ها deferred/low)
                ->orderByRaw("CASE priority WHEN 'pro' THEN 0 WHEN 'regular' THEN 1 WHEN 'deferred' THEN 2 ELSE 3 END")
                ->orderBy('created_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id']);

            if ($jobs->isEmpty()) {
                break;
            }

            // transactional transition + push
            \DB::transaction(function () use ($jobs, &$flushed) {
                foreach ($jobs as $row) {
                    $job = AiJob::where('id', $row->id)->lockForUpdate()->first();

                    if (! $job || $job->state !== AiJob::STATE_DEFERRED) {
                        continue; // دیگر توسط اجرا/worker دیگری مصرف شده (بدون تکرار)
                    }

                    if ($job->expires_at !== null && $job->expires_at->isPast()) {
                        $job->markExpired();
                        continue;
                    }

                    // اولویت: اگر pro بود بماند؛ وگرنه deferred → regular (حالا وقتش رسیده)
                    if ($job->priority === AiJob::PRIORITY_DEFERRED) {
                        $job->priority = AiJob::PRIORITY_REGULAR;
                    }

                    $job->due_at = null;
                    $job->markQueued();

                    AiJobDispatcher::pushToQueue($job);
                    $flushed++;
                }
            });

            // یک دور کافی در هر اجرا (باقی‌مانده در اجرای بعدی) — سقف زمانی cron
            break;
        }

        if ($flushed > 0) {
            $this->info("✅ {$flushed} job DEFERRED به صف AI ارسال شد.");
            Log::info('[AiQueue] Flush deferred', ['count' => $flushed]);
        } else {
            $this->line('job DEFERRED سررسیدگشته‌ای وجود ندارد.');
        }

        return 0;
    }
}
