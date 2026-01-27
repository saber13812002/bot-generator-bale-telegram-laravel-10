<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PoemRepository;
use App\Models\Poem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PoemRepositoryImpl implements PoemRepository
{
    public function create(array $data): Poem
    {
        Log::info('PoemRepository - Creating poem', ['data' => $data]);
        
        try {
            $poem = Poem::create($data);
            Log::info('PoemRepository - Poem created successfully', ['id' => $poem->id]);
            return $poem;
        } catch (\Exception $e) {
            Log::error('PoemRepository - Error creating poem', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?Poem
    {
        return Poem::find($id);
    }

    public function getByUser(int $botUserId, int $botMotherId): Collection
    {
        return Poem::where('bot_user_id', $botUserId)
            ->where('bot_mother_id', $botMotherId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPublished(int $botMotherId, ?string $orderBy = 'likes_count', ?string $direction = 'desc'): Collection
    {
        return Poem::where('bot_mother_id', $botMotherId)
            ->where('status', 'published')
            ->orderBy($orderBy, $direction)
            ->get();
    }

    public function getByType(string $poemType, int $botMotherId): Collection
    {
        return Poem::where('bot_mother_id', $botMotherId)
            ->where('poem_type', $poemType)
            ->where('status', 'published')
            ->orderBy('likes_count', 'desc')
            ->get();
    }

    public function update(Poem $poem, array $data): bool
    {
        Log::info('PoemRepository - Updating poem', ['id' => $poem->id, 'data' => $data]);
        return $poem->update($data);
    }

    public function delete(Poem $poem): bool
    {
        Log::info('PoemRepository - Deleting poem', ['id' => $poem->id]);
        return $poem->delete();
    }

    public function incrementLikes(Poem $poem): bool
    {
        $poem->increment('likes_count');
        return true;
    }

    public function decrementLikes(Poem $poem): bool
    {
        if ($poem->likes_count > 0) {
            $poem->decrement('likes_count');
        }
        return true;
    }
}
