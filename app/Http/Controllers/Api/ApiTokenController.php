<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiTokenController extends Controller
{
    /**
     * Generate a new API token (Super Admin only).
     */
    public function generateToken(Request $request)
    {
        // Only super_admin tokens may create new tokens
        if ($request->api_token->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super admin token required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:tenant,super_admin',
            'tenant_id' => 'required_if:type,tenant|exists:tenants,id',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            [$apiToken, $plainToken] = ApiToken::generate(
                $request->input('type'),
                $request->input('tenant_id'),
                $request->input('name')
            );

            Log::info('API token generated', [
                'token_id' => $apiToken->id,
                'type' => $apiToken->type,
                'tenant_id' => $apiToken->tenant_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token generated successfully',
                'data' => [
                    'token_id' => $apiToken->id,
                    'token' => $plainToken, // Show plain token only once
                    'type' => $apiToken->type,
                    'tenant_id' => $apiToken->tenant_id,
                    'warning' => '⚠️ Please save this token securely. It will not be shown again!'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error generating API token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List API tokens.
     * Super admin: all tokens. Tenant: only its own tokens.
     * Never exposes plain_token or the hash.
     */
    public function listTokens(Request $request)
    {
        $token = $request->api_token;

        $query = ApiToken::query();
        if ($token->type !== 'super_admin') {
            $query->where('tenant_id', $token->tenant_id);
        }

        $tokens = $query->with('tenant:id,tenant_name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (ApiToken $t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'type' => $t->type,
                    'tenant_id' => $t->tenant_id,
                    'tenant_name' => $t->tenant?->tenant_name,
                    'is_active' => $t->is_active,
                    'last_used_at' => $t->last_used_at,
                    'created_at' => $t->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tokens
        ], 200);
    }

    /**
     * Revoke an API token (soft delete — is_active=false).
     * Super admin can revoke any token. Tenant can only revoke its own.
     */
    public function revokeToken(Request $request, int $tokenId)
    {
        $token = $request->api_token;
        $target = ApiToken::find($tokenId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found'
            ], 404);
        }

        // Tenant can only revoke its own tokens
        if ($token->type !== 'super_admin' && (int) $target->tenant_id !== (int) $token->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $target->update(['is_active' => false]);

        Log::info('API token revoked', [
            'token_id' => $tokenId,
            'revoked_by_token_id' => $token->id,
            'revoked_by_type' => $token->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token revoked'
        ], 200);
    }
}
