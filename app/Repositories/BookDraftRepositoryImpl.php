<?php

namespace App\Repositories;

use App\Interfaces\Repositories\BookDraftRepository;
use App\Models\BookDraft;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookDraftRepositoryImpl implements BookDraftRepository
{
    public function create(array $data): BookDraft
    {
        Log::info('BookDraftRepository - Creating draft', ['data' => $data]);
        
        try {
            $draft = BookDraft::create($data);
            Log::info('BookDraftRepository - Draft created successfully', ['id' => $draft->id]);
            return $draft;
        } catch (\Exception $e) {
            Log::error('BookDraftRepository - Error creating draft', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?BookDraft
    {
        return BookDraft::find($id);
    }

    public function getByUser(int $botId, int $chatId, ?string $status = null): Collection
    {
        $query = BookDraft::where('bot_id', $botId)
            ->where('chat_id', $chatId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getByType(int $botId, int $chatId, string $type, ?string $status = null): Collection
    {
        $query = BookDraft::where('bot_id', $botId)
            ->where('chat_id', $chatId)
            ->where('type', $type);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function update(int $id, array $data): bool
    {
        $draft = BookDraft::find($id);
        if (!$draft) {
            return false;
        }

        return $draft->update($data);
    }

    public function attachToBook(int $id, int $bookId): bool
    {
        return $this->update($id, [
            'book_id' => $bookId,
            'status' => 'attached'
        ]);
    }

    public function delete(int $id): bool
    {
        $draft = BookDraft::find($id);
        if (!$draft) {
            return false;
        }

        return $draft->delete();
    }
}
