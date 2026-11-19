<?php

namespace App\Services;

use App\Interfaces\Repositories\BookDraftRepository;
use App\Interfaces\Repositories\BookRepository;
use App\Interfaces\Services\BookDraftService;
use App\Models\Book;
use App\Models\BookDraft;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookDraftServiceImpl implements BookDraftService
{
    public function __construct(
        private BookDraftRepository $draftRepository,
        private BookRepository $bookRepository
    ) {}

    public function saveDraft(int $botId, int $chatId, string $type, ?string $fileId = null, ?string $fileUniqueId = null, ?string $isbn = null, ?string $bookName = null, ?int $pageNumber = null): BookDraft
    {
        Log::info('BookDraftService - Saving draft', [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'type' => $type
        ]);

        return $this->draftRepository->create([
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'type' => $type,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'isbn' => $isbn,
            'book_name' => $bookName,
            'page_number' => $pageNumber,
            'status' => 'draft',
        ]);
    }

    public function getDrafts(int $botId, int $chatId, ?string $type = null, ?string $status = null): Collection
    {
        if ($type) {
            return $this->draftRepository->getByType($botId, $chatId, $type, $status);
        }

        return $this->draftRepository->getByUser($botId, $chatId, $status);
    }

    public function attachDraftToBook(int $draftId, int $bookId): bool
    {
        Log::info('BookDraftService - Attaching draft to book', [
            'draft_id' => $draftId,
            'book_id' => $bookId
        ]);

        return $this->draftRepository->attachToBook($draftId, $bookId);
    }

    public function searchBooks(int $botId, string $query): Collection
    {
        Log::info('BookDraftService - Searching books', [
            'bot_id' => $botId,
            'query' => $query
        ]);

        // Search by name (partial match)
        $booksByName = $this->bookRepository->getByBot($botId)
            ->filter(function ($book) use ($query) {
                return stripos($book->name, $query) !== false;
            });

        // Search by ISBN
        $bookByIsbn = $this->bookRepository->findByIsbnAndBot($query, $botId);
        if ($bookByIsbn) {
            $booksByName->push($bookByIsbn);
        }

        // Search by Shabak
        $bookByShabak = $this->bookRepository->findByShabakAndBot($query, $botId);
        if ($bookByShabak) {
            $booksByName->push($bookByShabak);
        }

        return $booksByName->unique('id')->values();
    }
}
