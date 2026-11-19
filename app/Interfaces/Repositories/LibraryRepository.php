<?php

namespace App\Interfaces\Repositories;

use App\Models\LibraryBook;
use App\Models\LibraryGenre;
use Illuminate\Support\Collection;

interface LibraryRepository
{
    public function getGenresForPage(int $botId, int $page): Collection;

    public function getMaxGenrePage(int $botId): int;

    public function findGenre(int $genreId, int $botId): ?LibraryGenre;

    public function getBooksForGenre(int $botId, int $genreId, int $page, int $perPage): Collection;

    public function countBooksForGenre(int $botId, int $genreId): int;

    public function findBook(int $bookId, int $botId): ?LibraryBook;

    public function getRandomBook(int $botId, ?int $genreId = null): ?LibraryBook;

    public function getUserBooks(int $botUserId, int $botId, int $limit = 10): Collection;
}
