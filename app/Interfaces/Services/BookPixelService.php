<?php

namespace App\Interfaces\Services;

use App\Models\Book;
use App\Models\BookPage;
use App\Models\BookPageScan;

interface BookPixelService
{
    /**
     * Find or create a book by name, ISBN, or Shabak.
     */
    public function findOrCreateBook(string $name, ?string $isbn, ?string $shabak, int $botId, ?int $userId = null): Book;

    /**
     * Create a book page.
     */
    public function createBookPage(int $bookId, int $pageNumber): BookPage;

    /**
     * Create a book page scan.
     */
    public function createScan(int $bookPageId, int $userId, int $botId, int $pageNumber, string $fileId, ?string $fileUniqueId = null): BookPageScan;

    /**
     * Send scan to moderation group.
     */
    public function sendToModerationGroup(BookPageScan $scan, int $botId, string $type): bool;

    /**
     * Create a book page voice.
     */
    public function createVoice(int $bookPageScanId, int $userId, int $botId, string $fileId, ?string $fileUniqueId = null): \App\Models\BookPageVoice;
}
