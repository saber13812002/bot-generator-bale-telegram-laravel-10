<?php

namespace App\Interfaces\Services;

use App\Models\BookDraft;
use Illuminate\Database\Eloquent\Collection;

interface BookDraftService
{
    /**
     * Save a draft automatically (called when user sends any message).
     */
    public function saveDraft(int $botId, int $chatId, string $type, ?string $fileId = null, ?string $fileUniqueId = null, ?string $isbn = null, ?string $bookName = null, ?int $pageNumber = null): BookDraft;

    /**
     * Get all drafts for a user.
     */
    public function getDrafts(int $botId, int $chatId, ?string $type = null, ?string $status = null): Collection;

    /**
     * Attach a draft to a book.
     */
    public function attachDraftToBook(int $draftId, int $bookId): bool;

    /**
     * Search books by query (name or ISBN).
     */
    public function searchBooks(int $botId, string $query): Collection;
}
