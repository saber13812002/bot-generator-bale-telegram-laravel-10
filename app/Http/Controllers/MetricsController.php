<?php

namespace App\Http\Controllers;

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
}
