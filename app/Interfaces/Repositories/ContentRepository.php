<?php

namespace App\Interfaces\Repositories;

use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;

interface ContentRepository
{
    /**
     * Create a new content.
     */
    public function create(array $data): Content;

    /**
     * Find a content by ID.
     */
    public function find(int $id): ?Content;

    /**
     * Get contents by mission ID.
     */
    public function getByMission(int $missionId): Collection;

    /**
     * Get contents by tenant ID.
     */
    public function getByTenant(int $tenantId): Collection;
}

