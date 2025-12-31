<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiLlm;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetadataApiController extends Controller
{
    /**
     * Get all metadata needed for client applications.
     * This endpoint returns all necessary data for setting up missions.
     */
    public function getMetadata(Request $request)
    {
        try {
            // Get tenant_id from token or request
            $apiToken = $request->api_token ?? null;
            $tenantId = $request->input('tenant_id') ?? ($apiToken ? $apiToken->tenant_id : null);
            $isSuperAdmin = $apiToken ? ($apiToken->type === 'super_admin') : $request->input('is_super_admin', false);

            // Get all tenants (if super admin) or current tenant
            $tenants = [];
            if ($isSuperAdmin) {
                $tenants = Tenant::select('id', 'tenant_name')->get();
            } elseif ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $tenants = [$tenant];
                }
            } else {
                // If no token and no tenant_id, return all tenants (for public metadata)
                $tenants = Tenant::select('id', 'tenant_name')->get();
            }

            // Get all active AI/LLMs
            $aiLmms = AiLlm::active()
                ->select('id', 'name', 'slug', 'description', 'url', 'sort_order')
                ->orderBy('sort_order')
                ->get();

            // Get content types
            $contentTypes = [
                ['value' => 'text', 'label' => 'Text'],
                ['value' => 'video', 'label' => 'Video'],
                ['value' => 'image', 'label' => 'Image'],
                ['value' => 'audio', 'label' => 'Audio'],
                ['value' => 'pdf', 'label' => 'PDF'],
            ];

            // Get mission statuses
            $missionStatuses = [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ];

            // Get assignment statuses
            $assignmentStatuses = [
                ['value' => 'reserved', 'label' => 'Reserved'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'pending_approval', 'label' => 'Pending Approval'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ];

            Log::info('Metadata requested via API', [
                'tenant_id' => $tenantId,
                'is_super_admin' => $isSuperAdmin
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'tenants' => $tenants,
                    'ai_llms' => $aiLmms,
                    'content_types' => $contentTypes,
                    'mission_statuses' => $missionStatuses,
                    'assignment_statuses' => $assignmentStatuses,
                    'api_info' => [
                        'base_url' => url('/api/api/v1'),
                        'version' => '1.0.0',
                        'authentication' => 'Bearer Token or X-API-Token header',
                    ],
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting metadata via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get metadata',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test API token validity.
     */
    public function testToken(Request $request)
    {
        try {
            $apiToken = $request->api_token;

            return response()->json([
                'success' => true,
                'message' => 'Token is valid',
                'data' => [
                    'token_id' => $apiToken->id,
                    'type' => $apiToken->type,
                    'tenant_id' => $apiToken->tenant_id,
                    'tenant_name' => $apiToken->tenant ? $apiToken->tenant->tenant_name : null,
                    'name' => $apiToken->name,
                    'is_active' => $apiToken->is_active,
                    'last_used_at' => $apiToken->last_used_at?->toDateTimeString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error testing token via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
