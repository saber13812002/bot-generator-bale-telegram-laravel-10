<?php

namespace App\Interfaces\Repositories;

use App\Models\Mission;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;

interface MissionRepository
{
    /**
     * Create a new mission.
     */
    public function create(array $data): Mission;

    /**
     * Find a mission by ID.
     */
    public function find(int $id): ?Mission;

    /**
     * Find available missions (active and not full).
     */
    public function findAvailable(int $tenantId = null): Collection;

    /**
     * Assign a mission to personnel.
     */
    public function assignToPersonnel(int $missionId, int $personnelId): bool;

    /**
     * Get missions assigned to a personnel.
     */
    public function getByPersonnel(int $personnelId, string $status = null): Collection;

    /**
     * Update mission personnel count.
     */
    public function updatePersonnelCount(int $missionId, int $increment = 1): bool;
}

