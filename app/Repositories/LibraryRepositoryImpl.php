<?php

namespace App\Repositories;

use App\Interfaces\Repositories\LibraryRepository;
use App\Models\LibraryBook;
use App\Models\LibraryGenre;
use App\Models\LibraryUserBook;
use Illuminate\Support\Collection;

class LibraryRepositoryImpl implements LibraryRepository
{
    public function getGenresForPage(int $botId, int $page): Collection
    {
        return LibraryGenre::where('bot_id', $botId)
            ->where('page', $page)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getMaxGenrePage(int $botId): int
    {
        return (int) LibraryGenre::where('bot_id', $botId)
            ->where('is_active', true)
            ->max('page') ?: 1;
    }

    public function findGenre(int $genreId, int $botId): ?LibraryGenre
    {
        return LibraryGenre::where('id', $genreId)
            ->where('bot_id', $botId)
            ->where('is_active', true)
            ->first();
    }

    public function getBooksForGenre(int $botId, int $genreId, int $page, int $perPage): Collection
    {
        return LibraryBook::where('library_books.bot_id', $botId)
            ->where('library_books.is_active', true)
            ->whereHas('genres', fn ($q) => $q->where('library_genres.id', $genreId))
            ->orderBy('library_books.id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
    }

    public function countBooksForGenre(int $botId, int $genreId): int
    {
        return LibraryBook::where('bot_id', $botId)
            ->where('is_active', true)
            ->whereHas('genres', fn ($q) => $q->where('library_genres.id', $genreId))
            ->count();
    }

    public function findBook(int $bookId, int $botId): ?LibraryBook
    {
        return LibraryBook::where('id', $bookId)
            ->where('bot_id', $botId)
            ->where('is_active', true)
            ->with('media')
            ->first();
    }

    public function getRandomBook(int $botId, ?int $genreId = null): ?LibraryBook
    {
        $query = LibraryBook::where('bot_id', $botId)
            ->where('is_active', true)
            ->where('random_eligible', true);

        if ($genreId) {
            $query->whereHas('genres', fn ($q) => $q->where('library_genres.id', $genreId));
        }

        return $query->inRandomOrder()->with('media')->first();
    }

    public function getUserBooks(int $botUserId, int $botId, int $limit = 10): Collection
    {
        return LibraryUserBook::where('bot_user_id', $botUserId)
            ->where('bot_id', $botId)
            ->with('book')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
