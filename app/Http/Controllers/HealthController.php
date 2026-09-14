<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Services\BotObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    use AssertsObservabilitySecret;

    public function index(Request $request, BotObservabilityService $observability): JsonResponse
    {
        $this->assertObservabilitySecret($request, 'observability.health_secret');

        $payload = $observability->healthPayload();

        // اضافه کردن وضعیت AI providers
        $payload['ai_providers'] = $this->aiProvidersHealth();

        return response()->json($payload);
    }

    private function aiProvidersHealth(): array
    {
        if (! Schema::hasTable('ai_providers')) {
            return [];
        }

        return AiProvider::active()->get()->map(function (AiProvider $p) {
            return [
                'name'           => $p->name,
                'base_url'       => $p->base_url,
                'status'         => $p->last_test_status ?? 'unknown',
                'ping_ms'        => $p->last_ping_ms,
                'last_tested_at' => $p->last_tested_at?->toIso8601String(),
                'models_count'   => is_array($p->available_models) ? count($p->available_models) : 0,
            ];
        })->values()->all();
    }
}
