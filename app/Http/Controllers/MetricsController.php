<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\AiProvider;
use App\Services\BotObservabilityService;
use App\Services\PrometheusMetricsExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class MetricsController extends Controller
{
    use AssertsObservabilitySecret;

    public function index(
        Request $request,
        BotObservabilityService $observability,
        PrometheusMetricsExporter $exporter
    ): Response {
        $this->assertObservabilitySecret($request, 'observability.metrics_secret');

        $body = $exporter->render($observability->series());

        // اضافه کردن متریک‌های AI providers
        $body .= $this->aiProviderMetrics();

        // سری‌های مانیتورینگ jobهای AI (AI-PILOT-ROLLOUT-05 §6)
        $body .= $this->aiJobMetrics();

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function aiProviderMetrics(): string
    {
        if (! Schema::hasTable('ai_providers')) {
            return '';
        }

        $providers = AiProvider::active()->get();
        if ($providers->isEmpty()) {
            return '';
        }

        $lines = [];

        // ai_provider_up
        $lines[] = '# HELP ai_provider_up 1 if last health check was successful, 0 if failed';
        $lines[] = '# TYPE ai_provider_up gauge';
        foreach ($providers as $p) {
            $labels   = $this->providerLabels($p);
            $val      = $p->last_test_status === 'success' ? 1 : 0;
            $lines[]  = "ai_provider_up{$labels} {$val}";
        }

        // ai_provider_ping_ms
        $lines[] = '# HELP ai_provider_ping_ms Last ping latency in milliseconds';
        $lines[] = '# TYPE ai_provider_ping_ms gauge';
        foreach ($providers as $p) {
            $labels  = $this->providerLabels($p);
            $val     = $p->last_ping_ms ?? 0;
            $lines[] = "ai_provider_ping_ms{$labels} {$val}";
        }

        // ai_provider_models_count
        $lines[] = '# HELP ai_provider_models_count Number of available models';
        $lines[] = '# TYPE ai_provider_models_count gauge';
        foreach ($providers as $p) {
            $labels  = $this->providerLabels($p);
            $val     = is_array($p->available_models) ? count($p->available_models) : 0;
            $lines[] = "ai_provider_models_count{$labels} {$val}";
        }

        // ai_provider_last_test_timestamp
        $lines[] = '# HELP ai_provider_last_test_timestamp Unix timestamp of last health check';
        $lines[] = '# TYPE ai_provider_last_test_timestamp gauge';
        foreach ($providers as $p) {
            $labels  = $this->providerLabels($p);
            $val     = $p->last_tested_at ? $p->last_tested_at->getTimestamp() : 0;
            $lines[] = "ai_provider_last_test_timestamp{$labels} {$val}";
        }

        return "\n" . implode("\n", $lines) . "\n";
    }

    private function providerLabels(AiProvider $p): string
    {
        $name = str_replace(['"', '\\', "\n"], ['\"', '\\\\', '\\n'], $p->name);
        $url  = str_replace(['"', '\\', "\n"], ['\"', '\\\\', '\\n'], $p->base_url);

        return '{name="' . $name . '",base_url="' . $url . '"}';
    }

    /**
     * سری‌های مانیتورینگ jobهای AI (AI-PILOT-ROLLOUT-05 — R3، brief §6):
     *  - ai_job_total{state}                    — شمارش هر state (queue length)
     *  - ai_job_by_feature{feature}             — totals per capability
     *  - ai_job_dead_letter_total{window_hours} — FAILED در window
     *  - ai_job_latency_avg_ms / _p95_ms        — از started_at/completed_at (24h)
     *  - ai_job_tokens_total{window_hours}      — مجموع total_tokens (24h)
     *  - ai_job_est_cost_total                  — از config('ai.pricing') (default 0)
     *  - ai_job_queue_depth                     — PENDING+QUEUED+RETRYING+DEFERRED
     *
     * بدون جدول ai_jobs → خالی (safe).
     */
    private function aiJobMetrics(): string
    {
        if (! Schema::hasTable('ai_jobs')) {
            return '';
        }

        $lines = [];

        // ai_job_total per state
        $stateCounts = AiJob::query()
            ->selectRaw('state, COUNT(*) as c')
            ->groupBy('state')
            ->pluck('c', 'state');

        $lines[] = '# HELP ai_job_total AI job count per state';
        $lines[] = '# TYPE ai_job_total gauge';
        foreach (AiJob::STATES as $state) {
            $lines[] = 'ai_job_total{state="' . $state . '"} ' . (int) ($stateCounts[$state] ?? 0);
        }

        // ai_job_by_feature
        $featureCounts = AiJob::query()
            ->selectRaw('feature, COUNT(*) as c')
            ->groupBy('feature')
            ->pluck('c', 'feature');

        $lines[] = '# HELP ai_job_by_feature Total AI jobs per capability feature';
        $lines[] = '# TYPE ai_job_by_feature counter';
        foreach ($featureCounts as $feature => $count) {
            $lines[] = 'ai_job_by_feature{feature="' . $this->escapeLabel($feature) . '"} ' . (int) $count;
        }

        // dead letters + tokens + cost (24h window)
        $window  = 24;
        $since   = now()->subHours($window);

        $deadLetters = (int) AiJob::byState(AiJob::STATE_FAILED)
            ->where('completed_at', '>=', $since)
            ->count();

        $lines[] = '# HELP ai_job_dead_letter_total FAILED (dead letter) jobs in window';
        $lines[] = '# TYPE ai_job_dead_letter_total counter';
        $lines[] = "ai_job_dead_letter_total{window_hours=\"{$window}\"} {$deadLetters}";

        $usageRows = AiJob::byState(AiJob::STATE_COMPLETED)
            ->where('completed_at', '>=', $since)
            ->whereNotNull('usage')
            ->get(['usage', 'started_at', 'completed_at']);

        $tokens     = 0;
        $prompt     = 0;
        $completion = 0;
        $latencies  = [];
        foreach ($usageRows as $row) {
            $u = $row->usage ?? [];
            $tokens     += (int) ($u['total_tokens'] ?? 0);
            $prompt     += (int) ($u['prompt_tokens'] ?? 0);
            $completion += (int) ($u['completion_tokens'] ?? 0);
        }
        // latency روی همه COMPLETEDهای window (حتی بدون usage)
        $latencyRows = AiJob::byState(AiJob::STATE_COMPLETED)
            ->where('completed_at', '>=', $since)
            ->whereNotNull('started_at')
            ->get(['started_at', 'completed_at']);
        foreach ($latencyRows as $row) {
            $latencies[] = max(0, (int) $row->started_at->diffInMilliseconds($row->completed_at));
        }

        $lines[] = '# HELP ai_job_tokens_total Total tokens consumed in window';
        $lines[] = '# TYPE ai_job_tokens_total counter';
        $lines[] = "ai_job_tokens_total{window_hours=\"{$window}\"} {$tokens}";

        $cost = round(
            (
                $prompt * (float) config('ai.pricing.input_per_million', 0)
                + $completion * (float) config('ai.pricing.output_per_million', 0)
            ) / 1_000_000,
            6
        );
        $lines[] = '# HELP ai_job_est_cost_total Estimated cost in window (USD, config ai.pricing)';
        $lines[] = '# TYPE ai_job_est_cost_total counter';
        $lines[] = "ai_job_est_cost_total{window_hours=\"{$window}\",currency=\"USD\"} " . number_format($cost, 6, '.', '');

        $avg = $latencies ? (int) round(array_sum($latencies) / count($latencies)) : 0;
        $sorted = $latencies;
        sort($sorted);
        $p95 = $sorted ? $sorted[(int) ceil(0.95 * count($sorted)) - 1] : 0;

        $lines[] = '# HELP ai_job_latency_avg_ms Average job latency in window (ms)';
        $lines[] = '# TYPE ai_job_latency_avg_ms gauge';
        $lines[] = "ai_job_latency_avg_ms{window_hours=\"{$window}\"} {$avg}";

        $lines[] = '# HELP ai_job_latency_p95_ms 95th percentile job latency in window (ms)';
        $lines[] = '# TYPE ai_job_latency_p95_ms gauge';
        $lines[] = "ai_job_latency_p95_ms{window_hours=\"{$window}\"} {$p95}";

        $queueDepth = 0;
        foreach ([AiJob::STATE_PENDING, AiJob::STATE_QUEUED, AiJob::STATE_RETRYING, AiJob::STATE_DEFERRED] as $s) {
            $queueDepth += (int) ($stateCounts[$s] ?? 0);
        }
        $lines[] = '# HELP ai_job_queue_depth Current queue depth (non-terminal waiting states)';
        $lines[] = '# TYPE ai_job_queue_depth gauge';
        $lines[] = "ai_job_queue_depth {$queueDepth}";

        return "\n" . implode("\n", $lines) . "\n";
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['"', '\\', "\n"], ['\"', '\\\\', '\\n'], $value);
    }
}
