<?php

namespace App\Http\Middleware;

use App\Services\BotAdminKieService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleHiddenBotCommands
{
    public function __construct(
        private readonly BotAdminKieService $adminKieService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isWebhookRoute($request)) {
            return $next($request);
        }

        $handled = $this->adminKieService->tryHandleFromRequest($request);
        if ($handled !== null) {
            return $handled;
        }

        return $next($request);
    }

    private function isWebhookRoute(Request $request): bool
    {
        if (!$request->isMethod('POST')) {
            return false;
        }

        $path = $request->path();

        return str_starts_with($path, 'api/webhook-')
            || str_starts_with($path, 'webhook-')
            || in_array($path, ['api/gap', 'gap'], true);
    }
}
