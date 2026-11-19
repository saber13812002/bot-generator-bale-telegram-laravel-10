<?php

namespace App\Interfaces\Repositories;

use App\Models\Poem;
use Illuminate\Database\Eloquent\Collection;

interface PoemRepository
{
    /**
     * Create a new poem.
     */
    public function create(array $data): Poem;

    /**
     * Find a poem by ID.
     */
    public function find(int $id): ?Poem;

    /**
     * Get all poems by user.
     */
    public function getByUser(int $botUserId, int $botMotherId): Collection;

    /**
     * Get published poems.
     */
    public function getPublished(int $botMotherId, ?string $orderBy = 'likes_count', ?string $direction = 'desc'): Collection;

    /**
     * Get poems by type.
     */
    public function getByType(string $poemType, int $botMotherId): Collection;

    /**
     * Update poem.
     */
    public function update(Poem $poem, array $data): bool;

    /**
     * Delete poem.
     */
    public function delete(Poem $poem): bool;

    /**
     * Increment likes count.
     */
    public function incrementLikes(Poem $poem): bool;

    /**
     * Decrement likes count.
     */
    public function decrementLikes(Poem $poem): bool;
}
