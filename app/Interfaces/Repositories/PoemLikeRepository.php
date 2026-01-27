<?php

namespace App\Interfaces\Repositories;

use App\Models\PoemLike;

interface PoemLikeRepository
{
    /**
     * Create a new like.
     */
    public function create(array $data): PoemLike;

    /**
     * Check if user has liked the poem.
     */
    public function exists(int $poemId, int $botUserId): bool;

    /**
     * Remove a like.
     */
    public function delete(int $poemId, int $botUserId): bool;

    /**
     * Get like count for a poem.
     */
    public function getCount(int $poemId): int;
}
