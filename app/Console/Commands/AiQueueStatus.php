<?php

namespace App\Console\Commands;

use App\Models\AiJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * مانیتورینگ صف AI (WS-D) — گزارش وضعیت برای ادمین/داشبورد.
 *
 * خروجی: شمارش هر state، قدیمی‌ترین jobهای غیرترمینال، dead letterهای اخیر،
 * و sanity-check وجود جدول jobs/ai_jobs.
 */
class AiQueueStatus extends Command
{
    protected $signature = 'ai:queue-status {--json : خروجی به‌صورت JSON برای اسکریپت‌نویسی}';

    protected $description = 'گزارش وضعیت صف AI (states، dead letter، کهنه‌ها)';

    public function handle(): int
    {
        $missing = array_values(array_filter(
            ['jobs', 'ai_jobs'],
            fn (string $t) => ! Schema::hasTable($t)
        ));

        if ($missing) {
            $msg = '❌ جدول(s) موجود نیست: ' . implode(', ', $missing)
                 . ' — ابتدا php artisan migrate اجرا کنید (docs/QUEUE_AI_WORKER.md)';
            $this->error($msg);
            return 2;
        }

        $stateCounts = AiJob::query()
            ->selectRaw('state, COUNT(*) as c')
            ->groupBy('state')
            ->pluck('c', 'state');

        $byState = [];
        foreach (AiJob::STATES as $state) {
            $byState[$state] = (int) ($stateCounts[$state] ?? 0);
        }

        $pendingSince  = AiJob::whereNotIn('state', [
                AiJob::STATE_COMPLETED, AiJob::STATE_FAILED,
                AiJob::STATE_CANCELLED, AiJob::STATE_EXPIRED,
            ])->min('created_at');

        $deadLetters = AiJob::byState(AiJob::STATE_FAILED)
            ->latest('completed_at')
            ->limit(10)
            ->get(['job_id', 'feature', 'bot_id', 'error_code', 'attempt_number', 'completed_at'])
            ->map(fn ($j) => [
                'job_id'    => $j->job_id,
                'feature'   => $j->feature,
                'bot_id'    => $j->bot_id,
                'error'     => $j->error_code,
                'attempts'  => $j->attempt_number,
                'failed_at' => $j->completed_at?->toIso8601String(),
            ]);

        $staleCount = AiJob::staleProcessing(
            (int) config('ai.queue.stale_after_minutes', 20)
        )->count();

        $report = [
            'states'         => $byState,
            'non_terminal'   => array_sum($byState) - $byState[AiJob::STATE_COMPLETED] - $byState[AiJob::STATE_FAILED]
                              - $byState[AiJob::STATE_CANCELLED] - $byState[AiJob::STATE_EXPIRED],
            'oldest_pending' => $pendingSince !== null
                ? \Illuminate\Support\Carbon::parse($pendingSince)->toIso8601String()
                : null,
            'stale_processing' => $staleCount,
            'dead_letters'   => $deadLetters,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->line('=== وضعیت صف AI (ai_jobs) ===');
            foreach ($byState as $state => $count) {
                $marker = match ($state) {
                    AiJob::STATE_COMPLETED => '✅',
                    AiJob::STATE_FAILED    => '🟠',
                    AiJob::STATE_PROCESSING => '🔄',
                    AiJob::STATE_DEFERRED  => '⏸️',
                    default                => '⬜',
                };
                $this->line(sprintf('  %s %-12s %6d', $marker, $state, $count));
            }
            $this->line(sprintf('  ⏱️  قدیمی‌ترین job در انتظار: %s', $report['oldest_pending'] ?? '-'));
            $this->line(sprintf('  🩺 PROCESSING کهنه (نیاز به recover): %d', $staleCount));

            if ($deadLetters->isNotEmpty()) {
                $this->line('  🟠 آخرین dead letterها:');
                foreach ($deadLetters as $dl) {
                    $this->line(sprintf(
                        '    %s [%s] bot=%s error=%s attempts=%d at=%s',
                        $dl['job_id'], $dl['feature'], $dl['bot_id'] ?? '-',
                        $dl['error'], $dl['attempts'], $dl['failed_at'] ?? '-'
                    ));
                }
            }

            // هشدار سلامت
            if ($staleCount > 0) {
                $this->warn('⚠️  PROCESSING کهنه وجود دارد — ai:queue-recover-stale اجرا شود.');
            }
            if (($stateCounts[AiJob::STATE_QUEUED] ?? 0) > 100) {
                $this->warn('⚠️  بیش از 100 job در صف — worker فعال است؟ (cron queue:work)');
            }
        }

        return 0;
    }
}
