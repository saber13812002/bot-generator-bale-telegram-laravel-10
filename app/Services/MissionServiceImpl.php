<?php

namespace App\Services;

use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Repositories\MissionRepository;
use App\Interfaces\Services\MissionService;
use App\Models\Mission;
use App\Models\MissionPersonnel;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionServiceImpl implements MissionService
{
    private MissionRepository $missionRepository;
    private ContentRepository $contentRepository;

    public function __construct(
        MissionRepository $missionRepository,
        ContentRepository $contentRepository
    ) {
        $this->missionRepository = $missionRepository;
        $this->contentRepository = $contentRepository;
    }

    /**
     * Request a mission (random or sequential).
     */
    public function requestMission(int $personnelId, string $mode = 'random'): ?Mission
    {
        Log::info('Requesting mission', [
            'personnel_id' => $personnelId,
            'mode' => $mode
        ]);

        try {
            $personnel = Personnel::findOrFail($personnelId);

            // Check if personnel has an active mission
            $activeMission = $this->missionRepository->getByPersonnel(
                $personnelId,
                null
            )->first(function ($mission) {
                $pivot = $mission->pivot;
                return in_array($pivot->status ?? null, ['reserved', 'in_progress', 'pending_approval']);
            });

            if ($activeMission) {
                Log::warning('Personnel already has an active mission', [
                    'personnel_id' => $personnelId,
                    'mission_id' => $activeMission->id
                ]);
                return null;
            }

            // Get available missions for tenant
            $availableMissions = $this->missionRepository->findAvailable($personnel->tenant_id);

            if ($availableMissions->isEmpty()) {
                Log::info('No available missions', ['personnel_id' => $personnelId]);
                return null;
            }

            // Select mission based on mode
            if ($mode === 'random') {
                $mission = $availableMissions->random();
            } else {
                $mission = $availableMissions->first();
            }

            // Assign mission
            if ($this->missionRepository->assignToPersonnel($mission->id, $personnelId)) {
                Log::info('Mission requested successfully', [
                    'mission_id' => $mission->id,
                    'personnel_id' => $personnelId
                ]);
                return $mission->fresh();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error requesting mission', [
                'error' => $e->getMessage(),
                'personnel_id' => $personnelId
            ]);
            throw $e;
        }
    }

    /**
     * Assign a mission to personnel.
     */
    public function assignMission(int $missionId, int $personnelId): bool
    {
        return $this->missionRepository->assignToPersonnel($missionId, $personnelId);
    }

    /**
     * Cancel a mission assignment.
     */
    public function cancelMission(int $personnelId, int $missionId = null): bool
    {
        Log::info('Cancelling mission', [
            'personnel_id' => $personnelId,
            'mission_id' => $missionId
        ]);

        try {
            DB::beginTransaction();

            $query = MissionPersonnel::where('personnel_id', $personnelId)
                ->whereIn('status', ['reserved', 'in_progress']);

            if ($missionId) {
                $query->where('mission_id', $missionId);
            }

            $missionPersonnel = $query->first();

            if (!$missionPersonnel) {
                Log::warning('No active mission found to cancel', [
                    'personnel_id' => $personnelId,
                    'mission_id' => $missionId
                ]);
                DB::rollBack();
                return false;
            }

            // Cancel the mission
            $missionPersonnel->cancel();

            // Decrement personnel count
            $mission = $missionPersonnel->mission;
            $mission->decrement('current_personnel_count');

            DB::commit();
            Log::info('Mission cancelled successfully', [
                'mission_id' => $missionPersonnel->mission_id,
                'personnel_id' => $personnelId
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling mission', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Submit result link for a mission.
     */
    public function submitResult(int $personnelId, string $resultLink, int $missionId = null): bool
    {
        Log::info('Submitting result', [
            'personnel_id' => $personnelId,
            'mission_id' => $missionId,
            'result_link' => $resultLink
        ]);

        try {
            $query = MissionPersonnel::where('personnel_id', $personnelId)
                ->whereIn('status', ['reserved', 'in_progress']);

            if ($missionId) {
                $query->where('mission_id', $missionId);
            }

            $missionPersonnel = $query->first();

            if (!$missionPersonnel) {
                Log::warning('No active mission found', [
                    'personnel_id' => $personnelId,
                    'mission_id' => $missionId
                ]);
                return false;
            }

            $missionPersonnel->update([
                'result_link' => $resultLink,
                'status' => 'pending_approval',
            ]);

            Log::info('Result submitted successfully', [
                'mission_id' => $missionPersonnel->mission_id,
                'personnel_id' => $personnelId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error submitting result', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get available missions for a tenant.
     */
    public function getAvailableMissions(int $tenantId = null): Collection
    {
        return $this->missionRepository->findAvailable($tenantId);
    }
}

