<?php

namespace App\Interfaces\Services;

use App\Models\Mission;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;

interface MissionService
{
    /**
     * Request a mission (random or sequential).
     */
    public function requestMission(int $personnelId, string $mode = 'random'): ?Mission;

    /**
     * Assign a mission to personnel.
     */
    public function assignMission(int $missionId, int $personnelId): bool;

    /**
     * Cancel a mission assignment.
     */
    public function cancelMission(int $personnelId, int $missionId = null): bool;

    /**
     * Submit result link for a mission.
     */
    public function submitResult(int $personnelId, string $resultLink, int $missionId = null): bool;

    /**
     * Get available missions for a tenant.
     */
    public function getAvailableMissions(int $tenantId = null): Collection;

    /**
     * Get missions by duration.
     */
    public function getMissionsByDuration(int $duration, int $tenantId = null): Collection;

    /**
     * Get missions by minimum points.
     */
    public function getMissionsByMinPoints(int $minPoints, int $tenantId = null): Collection;

    /**
     * Get missions by tags.
     */
    public function getMissionsByTags(array $tagIds, int $tenantId = null): Collection;

    /**
     * Get missions by filters.
     */
    public function getMissionsByFilters(array $filters, int $tenantId = null): Collection;
}

