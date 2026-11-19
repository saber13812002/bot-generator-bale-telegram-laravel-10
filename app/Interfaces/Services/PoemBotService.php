<?php

namespace App\Interfaces\Services;

use App\Models\Poem;
use App\Models\PoemLine;
use App\Models\PoemVersion;
use Illuminate\Database\Eloquent\Collection;

interface PoemBotService
{
    /**
     * Create a new poem.
     */
    public function createPoem(array $data, array $lines): Poem;

    /**
     * Add line to poem.
     */
    public function addLine(int $poemId, string $content, string $lineType, ?int $versionId = null): PoemLine;

    /**
     * Update poem line.
     */
    public function updateLine(int $lineId, string $content): bool;

    /**
     * Delete poem line.
     */
    public function deleteLine(int $lineId): bool;

    /**
     * Reorder poem lines.
     */
    public function reorderLines(int $poemId, array $lineIds): bool;

    /**
     * Create a new version of poem.
     */
    public function createVersion(int $poemId, int $createdBy, ?int $parentVersionId = null): PoemVersion;

    /**
     * Like a poem.
     */
    public function likePoem(int $poemId, int $botUserId): bool;

    /**
     * Unlike a poem.
     */
    public function unlikePoem(int $poemId, int $botUserId): bool;

    /**
     * Check if user has liked poem.
     */
    public function isLiked(int $poemId, int $botUserId): bool;

    /**
     * Get user's poems.
     */
    public function getUserPoems(int $botUserId, int $botMotherId): Collection;

    /**
     * Get published poems.
     */
    public function getPublishedPoems(int $botMotherId, ?string $orderBy = 'likes_count'): Collection;

    /**
     * Publish poem.
     */
    public function publishPoem(int $poemId): bool;

    /**
     * Create suggestion for poem.
     */
    public function createSuggestion(int $poemId, int $suggestedBy, string $lineContent, ?int $suggestedLineNumber = null): \App\Models\PoemSuggestion;

    /**
     * Accept suggestion.
     */
    public function acceptSuggestion(int $suggestionId): bool;

    /**
     * Reject suggestion.
     */
    public function rejectSuggestion(int $suggestionId): bool;

    /**
     * Fork poem (create collaboration).
     */
    public function forkPoem(int $originalPoemId, int $forkedBy, array $newLines): Poem;
}
