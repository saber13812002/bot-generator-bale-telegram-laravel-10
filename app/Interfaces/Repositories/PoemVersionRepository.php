<?php

namespace App\Interfaces\Repositories;

use App\Models\PoemVersion;
use Illuminate\Database\Eloquent\Collection;

interface PoemVersionRepository
{
    /**
     * Create a new poem version.
     */
    public function create(array $data): PoemVersion;

    /**
     * Find a version by ID.
     */
    public function find(int $id): ?PoemVersion;

    /**
     * Get all versions for a poem.
     */
    public function getByPoem(int $poemId): Collection;

    /**
     * Get latest version for a poem.
     */
    public function getLatestVersion(int $poemId): ?PoemVersion;

    /**
     * Get version by number.
     */
    public function getByVersionNumber(int $poemId, int $versionNumber): ?PoemVersion;
}
