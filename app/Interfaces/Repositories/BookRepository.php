<?php

namespace App\Interfaces\Repositories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

interface BookRepository
{
    /**
     * Create a new book.
     */
    public function create(array $data): Book;

    /**
     * Find a book by ID.
     */
    public function find(int $id): ?Book;

    /**
     * Find a book by name and bot_id.
     */
    public function findByNameAndBot(string $name, int $botId): ?Book;

    /**
     * Find a book by ISBN and bot_id.
     */
    public function findByIsbnAndBot(string $isbn, int $botId): ?Book;

    /**
     * Find a book by Shabak and bot_id.
     */
    public function findByShabakAndBot(string $shabak, int $botId): ?Book;

    /**
     * Get all books for a bot.
     */
    public function getByBot(int $botId): Collection;
}
