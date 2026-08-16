<?php

namespace App\Http\Controllers;

use App\Services\BotObservabilityService;
use App\Services\PrometheusMetricsExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
