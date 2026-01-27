<?php

namespace App\Interfaces\Repositories;

use App\Models\PoemLine;
use Illuminate\Database\Eloquent\Collection;

interface PoemLineRepository
{
    /**
     * Create a new poem line.
     */
    public function create(array $data): PoemLine;

    /**
     * Find a line by ID.
     */
    public function find(int $id): ?PoemLine;

    /**
     * Get all lines for a poem.
     */
    public function getByPoem(int $poemId): Collection;

    /**
     * Get lines by version.
     */
    public function getByVersion(int $versionId): Collection;

    /**
     * Update line.
     */
    public function update(PoemLine $line, array $data): bool;

    /**
     * Delete line.
     */
    public function delete(PoemLine $line): bool;

    /**
     * Reorder lines for a poem.
     */
    public function reorderLines(int $poemId, array $lineIds): bool;
}
