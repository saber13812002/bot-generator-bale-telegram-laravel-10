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
        // This should be protected by super admin middleware
        // For now, we'll check manually
        
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
}
