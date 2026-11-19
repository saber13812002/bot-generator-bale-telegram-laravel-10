<?php

namespace App\Repositories;

use App\Interfaces\Repositories\BookRepository;
use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookRepositoryImpl implements BookRepository
{
    public function create(array $data): Book
    {
        Log::info('BookRepository - Creating book', ['data' => $data]);
        
        try {
            $book = Book::create($data);
            Log::info('BookRepository - Book created successfully', ['id' => $book->id]);
            return $book;
        } catch (\Exception $e) {
            Log::error('BookRepository - Error creating book', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?Book
    {
        return Book::find($id);
    }

    public function findByNameAndBot(string $name, int $botId): ?Book
    {
        return Book::where('name', $name)
            ->where('bot_id', $botId)
            ->first();
    }

    public function findByIsbnAndBot(string $isbn, int $botId): ?Book
    {
        return Book::where('isbn', $isbn)
            ->where('bot_id', $botId)
            ->first();
    }

    public function findByShabakAndBot(string $shabak, int $botId): ?Book
    {
        return Book::where('shabak', $shabak)
            ->where('bot_id', $botId)
            ->first();
    }

    public function getByBot(int $botId): Collection
    {
        return Book::where('bot_id', $botId)->get();
    }
}
