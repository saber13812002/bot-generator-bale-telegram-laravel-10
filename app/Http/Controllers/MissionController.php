<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\MissionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MissionController extends Controller
{
    private MissionService $missionService;

    public function __construct(MissionService $missionService)
    {
        $this->missionService = $missionService;
    }

    /**
     * Get list of available missions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $request->input('tenant_id');
            $missions = $this->missionService->getAvailableMissions($tenantId);

            return response()->json([
                'success' => true,
                'data' => $missions,
            ]);
        } catch (Exception $e) {
            Log::error('Error getting missions list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست ماموریت‌ها',
            ], 500);
        }
    }

    /**
     * Get mission details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $mission = \App\Models\Mission::with(['prompt', 'content', 'contents', 'tenant'])
                ->find($id);

            if (!$mission) {
                return response()->json([
                    'success' => false,
                    'message' => 'ماموریت یافت نشد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $mission,
            ]);
        } catch (Exception $e) {
            Log::error('Error getting mission details', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت جزئیات ماموریت',
            ], 500);
        }
    }

    /**
     * Request a mission.
     */
    public function request(Request $request, int $id): JsonResponse
    {
        try {
            $personnelId = $request->input('personnel_id');
            $mode = $request->input('mode', 'random'); // random or sequential

            if (!$personnelId) {
                return response()->json([
                    'success' => false,
                    'message' => 'شناسه پرسنل الزامی است',
                ], 400);
            }

            $mission = $this->missionService->requestMission($personnelId, $mode);

            if (!$mission) {
                return response()->json([
                    'success' => false,
                    'message' => 'ماموریت در دسترس نیست یا شما ماموریت فعالی دارید',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'ماموریت با موفقیت اختصاص یافت',
                'data' => $mission,
            ]);
        } catch (Exception $e) {
            Log::error('Error requesting mission', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطا در درخواست ماموریت',
            ], 500);
        }
    }

    /**
     * Cancel a mission.
     */
    public function cancel(Request $request, int $id = null): JsonResponse
    {
        try {
            $personnelId = $request->input('personnel_id');

            if (!$personnelId) {
                return response()->json([
                    'success' => false,
                    'message' => 'شناسه پرسنل الزامی است',
                ], 400);
            }

            $result = $this->missionService->cancelMission($personnelId, $id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'ماموریت فعالی برای کنسل کردن یافت نشد',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'ماموریت با موفقیت کنسل شد',
            ]);
        } catch (Exception $e) {
            Log::error('Error cancelling mission', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطا در کنسل کردن ماموریت',
            ], 500);
        }
    }

    /**
     * Submit result link.
     */
    public function submitResult(Request $request, int $id = null): JsonResponse
    {
        try {
            $personnelId = $request->input('personnel_id');
            $resultLink = $request->input('result_link');

            if (!$personnelId || !$resultLink) {
                return response()->json([
                    'success' => false,
                    'message' => 'شناسه پرسنل و لینک نتیجه الزامی است',
                ], 400);
            }

            if (!filter_var($resultLink, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لینک معتبر نیست',
                ], 400);
            }

            $result = $this->missionService->submitResult($personnelId, $resultLink, $id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'ماموریت فعالی یافت نشد',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'لینک نتیجه با موفقیت ثبت شد',
            ]);
        } catch (Exception $e) {
            Log::error('Error submitting result', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت لینک نتیجه',
            ], 500);
        }
    }
}
