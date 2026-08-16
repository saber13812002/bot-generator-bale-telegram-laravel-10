<?php

namespace App\Http\Controllers;

use App\Services\BotObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    use AssertsObservabilitySecret;

    public function index(Request $request, BotObservabilityService $observability): JsonResponse
    {
        $this->assertObservabilitySecret($request, 'observability.health_secret');

        return response()->json($observability->healthPayload());
    }
}
