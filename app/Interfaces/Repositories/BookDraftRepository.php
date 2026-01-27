<?php

namespace App\Interfaces\Repositories;

use App\Models\BookDraft;
use Illuminate\Database\Eloquent\Collection;

interface BookDraftRepository
{
    /**
     * Create a new draft.
     */
    public function create(array $data): BookDraft;

    /**
     * Find a draft by ID.
     */
    public function find(int $id): ?BookDraft;

    /**
     * Get all drafts for a user (by bot_id and chat_id).
     */
    public function getByUser(int $botId, int $chatId, ?string $status = null): Collection;

    /**
     * Get drafts by type.
     */
    public function getByType(int $botId, int $chatId, string $type, ?string $status = null): Collection;

    /**
     * Update a draft.
     */
    public function update(int $id, array $data): bool;

    /**
     * Attach draft to a book.
     */
    public function attachToBook(int $id, int $bookId): bool;

    /**
     * Delete a draft.
     */
    public function delete(int $id): bool;
}
