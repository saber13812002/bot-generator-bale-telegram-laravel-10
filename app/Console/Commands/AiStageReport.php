<?php

namespace App\Console\Commands;

use App\Models\AiFeedback;
use App\Models\AiJob;
use App\Models\Bot;
use App\Services\Ai\AiAlertService;
use App\Services\Ai\AiFlagService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — گزارش مرحله‌ای rollout (brief §10).
 *
 * فیلدهای الزامی هر stage:
 *  - current_global_stage, stage, stage_name
 *  - allowlisted_users, bots_total, bots_by_stage
 *  - jobs_24h {total, completed, failed, deferred}, latency_24h {avg_ms, p95_ms}
 *  - tokens_24h, est_cost_24h (config ai.pricing؛ پیش‌فرض 0)
 *  - error_rate_24h, dead_letters_24h
 *  - feedback {total, by_category, open}
 *  - alerts_active
 *  - gate_criteria (brief §11 — آستانه‌های پیشنهادی + وضعیت واقعی)
 *
 * خروجی: جدول خوانا + --json. هرگز exit ≠ 0 برای داده ناکافی نمی‌دهد
 * (گزارش «صفر» معتبر است؛ فقط نبود جدول = 2).
 */
class AiStageReport extends Command
{
    protected $signature = 'ai:stage-report {--stage= : شماره stage گزارش (پیش‌فرض: stage سراسری config)} {--json : خروجی JSON}';

    protected $description = 'گزارش مرحله‌ای rollout با فیلدهای brief §10 (داده واقعی 24h)';

    private const STAGE_NAMES = [
        0 => 'Internal', 1 => 'Pilot', 2 => 'All Pro', 3 => 'Public',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('ai_jobs')) {
            $this->error('❌ جدول ai_jobs موجود نیست — ابتدا migrate کنید.');
            return 2;
        }

        $stage = $this->option('stage') !== null
            ? (int) $this->option('stage')
            : (int) config('ai.rollout.stage', 0);

        $report = $this->buildReport($stage);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return 0;
        }

        $this->line("=== گزارش Stage {$stage} — {$report['stage_name']} (AI-PILOT-ROLLOUT-05 §10) ===");
        $this->line(sprintf('  Stage سراسری فعلی: %d — stage گزارش: %d', $report['current_global_stage'], $report['stage']));
        $this->line(sprintf('  Allowlist: %d کاربر — Botها: %d (by stage: %s)',
            $report['allowlisted_users'],
            $report['bots_total'],
            json_encode($report['bots_by_stage'], JSON_UNESCAPED_UNICODE)
        ));
        $this->line(sprintf('  Jobs 24h: total=%d completed=%d failed=%d deferred=%d',
            $report['jobs_24h']['total'],
            $report['jobs_24h']['completed'],
            $report['jobs_24h']['failed'],
            $report['jobs_24h']['deferred']
        ));
        $this->line(sprintf('  Latency 24h: avg=%dms p95=%dms — tokens=%d — est.cost=$%.6f',
            $report['latency_24h']['avg_ms'],
            $report['latency_24h']['p95_ms'],
            $report['tokens_24h'],
            $report['est_cost_24h']
        ));
        $this->line(sprintf('  Error rate 24h: %s%% — dead letters 24h: %d',
            $report['error_rate_24h'],
            $report['dead_letters_24h']
        ));
        $this->line(sprintf('  Feedback: total=%d open=%d (by category: %s)',
            $report['feedback']['total'],
            $report['feedback']['open'],
            json_encode($report['feedback']['by_category'], JSON_UNESCAPED_UNICODE)
        ));
        $this->line(sprintf('  Alerts فعال در حال حاضر: %d', count($report['alerts_active'])));

        $this->line('');
        $this->line('  ── Gate criteria (brief §11 — داده واقعی vs آستانه پیشنهادی) ──');
        foreach ($report['gate_criteria'] as $c) {
            $this->line(sprintf('    %s %s: %s', $c['met'] ? '✅' : '⬜', $c['criterion'], $c['detail']));
        }

        return 0;
    }

    /**
     * @return array
     */
    private function buildReport(int $stage): array
    {
        $now   = Carbon::now();
        $since = $now->copy()->subHours(24);

        // ── jobs 24h ──────────────────────────────────────────────────
        $q      = fn (string $state) => AiJob::byState($state)
            ->where('completed_at', '>=', $since)
            ->count();
        $completed = $q(AiJob::STATE_COMPLETED);
        $failed    = $q(AiJob::STATE_FAILED);
        $deferred  = AiJob::byState(AiJob::STATE_DEFERRED)->count();
        $total     = $completed + $failed;

        // ── latency 24h (COMPLETED با started_at) ─────────────────────
        $latencies = AiJob::byState(AiJob::STATE_COMPLETED)
            ->where('completed_at', '>=', $since)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get(['started_at', 'completed_at'])
            ->map(fn ($j) => max(0, (int) $j->started_at->diffInMilliseconds($j->completed_at)))
            ->values()
            ->sort()
            ->all();

        $avg  = $latencies ? (int) round(array_sum($latencies) / count($latencies)) : 0;
        $p95  = $latencies ? $latencies[(int) ceil(0.95 * count($latencies)) - 1] : 0;

        // ── tokens + est. cost 24h ────────────────────────────────────
        $rows     = AiJob::byState(AiJob::STATE_COMPLETED)
            ->where('completed_at', '>=', $since)
            ->whereNotNull('usage')
            ->get(['usage']);
        $tokens   = 0;
        $prompt   = 0;
        $completion = 0;
        foreach ($rows as $r) {
            $u = $r->usage ?? [];
            $tokens     += (int) ($u['total_tokens'] ?? 0);
            $prompt     += (int) ($u['prompt_tokens'] ?? 0);
            $completion += (int) ($u['completion_tokens'] ?? 0);
        }
        $cost = round(
            (
                $prompt * (float) config('ai.pricing.input_per_million', 0)
                + $completion * (float) config('ai.pricing.output_per_million', 0)
            ) / 1_000_000,
            6
        );

        // ── error rate + dead letters 24h ─────────────────────────────
        $errorRate = $total > 0 ? round($failed * 100 / $total, 1) : 0.0;
        $deadLetters = (int) AiJob::byState(AiJob::STATE_FAILED)
            ->where('completed_at', '>=', $since)
            ->count();

        // ── bots + allowlist + stage distribution ─────────────────────
        $botsTotal    = 0;
        $botsByStage  = [];
        $allowlisted  = [];
        if (Schema::hasTable('bots')) {
            $botsTotal = Bot::count();
            foreach (Bot::all(['id', 'ai_settings']) as $bot) {
                $s   = $bot->aiRolloutStage() ?? (int) config('ai.rollout.stage', 0);
                $botsByStage[(string) $s] = ($botsByStage[(string) $s] ?? 0) + 1;
                $allowlisted = array_merge($allowlisted, (array) $bot->aiTestGroup());
            }
        }
        $allowlisted = array_values(array_unique(array_filter(array_map('intval', $allowlisted))));

        // ── feedback (brief §8) ───────────────────────────────────────
        $feedback = ['total' => 0, 'open' => 0, 'by_category' => []];
        if (Schema::hasTable('ai_feedback')) {
            $all = AiFeedback::all(['id', 'category', 'status']);
            $feedback['total'] = $all->count();
            $feedback['open']  = $all->whereIn('status', ['new', 'in_progress'])->count();
            foreach ($all as $f) {
                $feedback['by_category'][$f->category] = ($feedback['by_category'][$f->category] ?? 0) + 1;
            }
        }

        // ── alerts فعال همین الان (بدون ارسال — detect فقط) ───────────
        $alertsActive = AiAlertService::detect();

        // ── gate criteria (brief §11: آستانه پیشنهادی vs داده واقعی) ─
        $criteria = [];
        $criteria[] = [
            'criterion' => 'jobs_24h >= 20 (حجم حداقلی)',
            'met'       => $total >= 20,
            'detail'    => "actual: {$total}",
        ];
        $criteria[] = [
            'criterion' => 'error_rate_24h <= 5%',
            'met'       => $total > 0 && $errorRate <= 5.0,
            'detail'    => "actual: {$errorRate}%",
        ];
        $criteria[] = [
            'criterion' => 'dead_letters_24h == 0',
            'met'       => $deadLetters === 0,
            'detail'    => "actual: {$deadLetters}",
        ];
        $criteria[] = [
            'criterion' => 'p95_latency <= 10000ms (سقف پیشنهادی)',
            'met'       => $p95 <= 10000,
            'detail'    => "actual: {$p95}ms",
        ];
        $criteria[] = [
            'criterion' => 'kill switch سراسری قابل test است (ai_flags override)',
            'met'       => AiFlagService::knownKeys() !== [] && in_array('ai_global_enabled', AiFlagService::knownKeys(), true),
            'detail'    => 'Nova → AI Flags (بدون re-deploy)',
        ];

        return [
            'stage'               => $stage,
            'stage_name'          => self::STAGE_NAMES[$stage] ?? "stage {$stage}",
            'current_global_stage' => (int) config('ai.rollout.stage', 0),
            'allowlisted_users'   => count($allowlisted),
            'bots_total'          => $botsTotal,
            'bots_by_stage'       => $botsByStage,
            'jobs_24h'            => [
                'total'     => $total,
                'completed' => $completed,
                'failed'    => $failed,
                'deferred'  => $deferred,
            ],
            'latency_24h'         => ['avg_ms' => $avg, 'p95_ms' => $p95],
            'tokens_24h'          => $tokens,
            'est_cost_24h'        => $cost,
            'error_rate_24h'      => $errorRate,
            'dead_letters_24h'    => $deadLetters,
            'feedback'            => $feedback,
            'alerts_active'       => array_column($alertsActive, 'key'),
            'gate_criteria'       => $criteria,
        ];
    }
}
