<?php

namespace App\Interfaces\Repositories;

use App\Models\PoemSuggestion;
use Illuminate\Database\Eloquent\Collection;

interface PoemSuggestionRepository
{
    /**
     * Create a new suggestion.
     */
    public function create(array $data): PoemSuggestion;

    /**
     * Find a suggestion by ID.
     */
    public function find(int $id): ?PoemSuggestion;

    /**
     * Get suggestions for a poem.
     */
    public function getByPoem(int $poemId, ?string $status = null): Collection;

    /**
     * Update suggestion status.
     */
    public function updateStatus(PoemSuggestion $suggestion, string $status): bool;
}
