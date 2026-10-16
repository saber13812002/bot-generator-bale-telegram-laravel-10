<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token') ?? $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API token is required'
            ], 401);
        }

        $apiToken = ApiToken::findByToken($token);

        if (!$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API token'
            ], 401);
        }

        // Update last used timestamp
        $apiToken->updateLastUsed();

        // Attach token info to request.
        // tenant_id: for tenant tokens, always from the token (client cannot override).
        // For super_admin tokens, tenant_id from the body is preserved (target tenant).
        $merge = [
            'api_token' => $apiToken,
            'is_super_admin' => $apiToken->type === 'super_admin',
        ];

        if ($apiToken->type !== 'super_admin' && $apiToken->tenant_id !== null) {
            $merge['tenant_id'] = $apiToken->tenant_id;
        }

        $request->merge($merge);

        return $next($request);
    }
}
