<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
use App\Models\Prompt;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MissionApiController extends Controller
{
    /**
     * Create a new mission with prompt and content.
     */
    public function createMission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prompt_content' => 'required|string',
            'content_title' => 'nullable|string|max:255',
            'content_url' => 'nullable|string|max:255',
            'content_description' => 'nullable|string',
            'content_type' => 'nullable|in:text,video,image,audio,pdf',
            'points' => 'nullable|integer|min:0',
            'duration' => 'nullable|integer|min:1',
            'max_personnel' => 'nullable|integer|min:1',
            'ai_id' => 'nullable|exists:ai_llms,id',
            'tenant_id' => 'required_if:is_super_admin,true|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Determine tenant_id
            $tenantId = null;
            
            // If super admin, tenant_id must be provided
            if ($request->input('is_super_admin')) {
                $tenantId = $request->input('tenant_id');
                if (!$tenantId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tenant ID is required for super admin'
                    ], 422);
                }
            } else {
                // For tenant token, use token's tenant_id
                $tenantId = $request->api_token->tenant_id;
            }

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID is required'
                ], 422);
            }

            // Verify tenant exists
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }

            // Create prompt
            $prompt = Prompt::create([
                'tenant_id' => $tenantId,
                'content' => $request->input('prompt_content'),
                'mission_id' => null, // Will be updated after mission creation
            ]);

            // Create mission
            $mission = Mission::create([
                'tenant_id' => $tenantId,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'prompt_id' => $prompt->id,
                'content_id' => null,
                'ai_id' => $request->input('ai_id'),
                'points' => $request->input('points', 0),
                'duration' => $request->input('duration'),
                'max_personnel' => $request->input('max_personnel', 1),
                'current_personnel_count' => 0,
                'status' => 'active',
            ]);

            // Update prompt with mission_id
            $prompt->update(['mission_id' => $mission->id]);

            // Create content if provided
            $content = null;
            if ($request->has('content_title') || $request->has('content_url')) {
                $content = Content::create([
                    'tenant_id' => $tenantId,
                    'title' => $request->input('content_title', ''),
                    'content_type' => $request->input('content_type', 'text'),
                    'content_url' => $request->input('content_url'),
                    'description' => $request->input('content_description'),
                    'sort_order' => 1,
                ]);

                // Attach content to mission
                MissionContent::create([
                    'mission_id' => $mission->id,
                    'content_id' => $content->id,
                    'sort_order' => 1,
                ]);

                // Update mission content_id
                $mission->update(['content_id' => $content->id]);
            }

            DB::commit();

            Log::info('Mission created via API', [
                'mission_id' => $mission->id,
                'tenant_id' => $tenantId,
                'api_token_id' => $request->api_token->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mission created successfully',
                'data' => [
                    'mission' => $mission->load(['prompt', 'content', 'ai']),
                    'prompt' => $prompt,
                    'content' => $content,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating mission via API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create mission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add content to an existing mission.
     */
    public function addContent(Request $request, $missionId)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content_url' => 'nullable|string|max:255',
            'content_type' => 'nullable|in:text,video,image,audio,pdf',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mission = Mission::findOrFail($missionId);

            // Check tenant access
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;
            if ($mission->tenant_id != $tenantId && !$request->input('is_super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Create content
            $content = Content::create([
                'tenant_id' => $mission->tenant_id,
                'title' => $request->input('title'),
                'content_type' => $request->input('content_type', 'text'),
                'content_url' => $request->input('content_url'),
                'description' => $request->input('description'),
                'sort_order' => $request->input('sort_order', 1),
            ]);

            // Attach content to mission
            MissionContent::create([
                'mission_id' => $mission->id,
                'content_id' => $content->id,
                'sort_order' => $request->input('sort_order', 1),
            ]);

            Log::info('Content added to mission via API', [
                'mission_id' => $mission->id,
                'content_id' => $content->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Content added successfully',
                'data' => $content
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding content via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List missions for the tenant.
     */
    public function listMissions(Request $request)
    {
        try {
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;

            $missions = Mission::where('tenant_id', $tenantId)
                ->with(['prompt', 'content', 'ai', 'contents'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $missions
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error listing missions via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list missions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific mission.
     */
    public function getMission(Request $request, $missionId)
    {
        try {
            $mission = Mission::with(['prompt', 'content', 'ai', 'contents'])
                ->findOrFail($missionId);

            // Check tenant access
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;
            if ($mission->tenant_id != $tenantId && !$request->input('is_super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $mission
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting mission via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Mission not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Submit result link for a mission (by personnel).
     */
    public function submitResult(Request $request, $missionId)
    {
        $validator = Validator::make($request->all(), [
            'result_link' => 'required|url',
            'personnel_id' => 'required|exists:personnel,id',
            'selected_ai_id' => 'nullable|exists:ai_llms,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mission = Mission::findOrFail($missionId);
            $personnel = \App\Models\Personnel::findOrFail($request->input('personnel_id'));

            // Check tenant access
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;
            if ($mission->tenant_id != $tenantId && !$request->input('is_super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Check if personnel belongs to tenant
            if ($personnel->tenant_id != $mission->tenant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Personnel does not belong to this tenant'
                ], 403);
            }

            // Find active mission personnel
            $missionPersonnel = \App\Models\MissionPersonnel::where('mission_id', $missionId)
                ->where('personnel_id', $personnel->id)
                ->whereIn('status', ['reserved', 'in_progress'])
                ->first();

            if (!$missionPersonnel) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active mission assignment found'
                ], 404);
            }

            // Update mission personnel
            $missionPersonnel->update([
                'result_link' => $request->input('result_link'),
                'status' => 'pending_approval',
                'selected_ai_id' => $request->input('selected_ai_id'),
            ]);

            Log::info('Mission result submitted via API', [
                'mission_id' => $missionId,
                'personnel_id' => $personnel->id,
                'mission_personnel_id' => $missionPersonnel->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Result submitted successfully',
                'data' => [
                    'mission_personnel_id' => $missionPersonnel->id,
                    'status' => $missionPersonnel->status,
                    'result_link' => $missionPersonnel->result_link,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error submitting result via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mission status and approval information.
     */
    public function getMissionStatus(Request $request, $missionId)
    {
        try {
            $mission = Mission::with(['prompt', 'content', 'ai', 'contents'])
                ->findOrFail($missionId);

            // Check tenant access
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;
            if ($mission->tenant_id != $tenantId && !$request->input('is_super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Get mission personnel records
            $missionPersonnel = \App\Models\MissionPersonnel::where('mission_id', $missionId)
                ->with(['personnel', 'selectedAi'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'mission' => $mission,
                    'assignments' => $missionPersonnel->map(function ($mp) {
                        return [
                            'id' => $mp->id,
                            'personnel' => [
                                'id' => $mp->personnel->id,
                                'name' => $mp->personnel->first_name . ' ' . $mp->personnel->last_name,
                            ],
                            'status' => $mp->status,
                            'result_link' => $mp->result_link,
                            'selected_ai' => $mp->selectedAi ? [
                                'id' => $mp->selectedAi->id,
                                'name' => $mp->selectedAi->name,
                            ] : null,
                            'approved_at' => $mp->approved_at,
                            'rejected_at' => $mp->rejected_at,
                            'rejection_reason' => $mp->rejection_reason,
                        ];
                    }),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting mission status via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get mission status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign mission to personnel.
     */
    public function assignMission(Request $request, $missionId)
    {
        $validator = Validator::make($request->all(), [
            'personnel_id' => 'required|exists:personnel,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mission = Mission::findOrFail($missionId);
            $personnel = \App\Models\Personnel::findOrFail($request->input('personnel_id'));

            // Check tenant access
            $tenantId = $request->input('tenant_id') ?? $request->api_token->tenant_id;
            if ($mission->tenant_id != $tenantId && !$request->input('is_super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Check if personnel belongs to tenant
            if ($personnel->tenant_id != $mission->tenant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Personnel does not belong to this tenant'
                ], 403);
            }

            // Use MissionService to assign
            $missionService = app(\App\Interfaces\Services\MissionService::class);
            $assigned = $missionService->assignMission($missionId, $personnel->id);

            if (!$assigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign mission. Mission may be full or personnel has active mission.'
                ], 400);
            }

            Log::info('Mission assigned via API', [
                'mission_id' => $missionId,
                'personnel_id' => $personnel->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mission assigned successfully',
                'data' => [
                    'mission_id' => $missionId,
                    'personnel_id' => $personnel->id,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error assigning mission via API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign mission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
