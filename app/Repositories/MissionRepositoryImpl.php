<?php

namespace App\Repositories;

use App\Interfaces\Repositories\MissionRepository;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionRepositoryImpl implements MissionRepository
{
    /**
     * Create a new mission.
     */
    public function create(array $data): Mission
    {
        Log::info('Creating mission', ['data' => $data]);

        try {
            $mission = Mission::create($data);
            Log::info('Mission created successfully', ['id' => $mission->id]);
            return $mission;
        } catch (\Exception $e) {
            Log::error('Error creating mission', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Find a mission by ID.
     */
    public function find(int $id): ?Mission
    {
        return Mission::find($id);
    }

    /**
     * Find available missions (active and not full).
     */
    public function findAvailable(int $tenantId = null): Collection
    {
        $query = Mission::available();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Assign a mission to personnel.
     */
    public function assignToPersonnel(int $missionId, int $personnelId): bool
    {
        Log::info('Assigning mission to personnel', [
            'mission_id' => $missionId,
            'personnel_id' => $personnelId
        ]);

        try {
            DB::beginTransaction();

            $mission = Mission::findOrFail($missionId);

            // Check if mission is available
            if ($mission->current_personnel_count >= $mission->max_personnel) {
                Log::warning('Mission is full', ['mission_id' => $missionId]);
                DB::rollBack();
                return false;
            }

            // Check if personnel already has this mission
            $existing = $mission->missionPersonnel()
                ->where('personnel_id', $personnelId)
                ->whereIn('status', ['reserved', 'in_progress', 'pending_approval'])
                ->first();

            if ($existing) {
                Log::warning('Personnel already has this mission', [
                    'mission_id' => $missionId,
                    'personnel_id' => $personnelId
                ]);
                DB::rollBack();
                return false;
            }

            // Create mission personnel assignment
            $mission->missionPersonnel()->create([
                'personnel_id' => $personnelId,
                'status' => 'reserved',
                'started_at' => now(),
            ]);

            // Update personnel count
            $mission->increment('current_personnel_count');

            DB::commit();
            Log::info('Mission assigned successfully', [
                'mission_id' => $missionId,
                'personnel_id' => $personnelId
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning mission', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get missions assigned to a personnel.
     */
    public function getByPersonnel(int $personnelId, string $status = null): Collection
    {
        $query = Mission::whereHas('missionPersonnel', function ($q) use ($personnelId, $status) {
            $q->where('personnel_id', $personnelId);
            if ($status) {
                $q->where('status', $status);
            }
        });

        return $query->get();
    }

    /**
     * Update mission personnel count.
     */
    public function updatePersonnelCount(int $missionId, int $increment = 1): bool
    {
        try {
            $mission = Mission::findOrFail($missionId);
            $mission->increment('current_personnel_count', $increment);
            return true;
        } catch (\Exception $e) {
            Log::error('Error updating personnel count', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

