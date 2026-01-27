<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PoemLikeRepository;
use App\Models\PoemLike;
use Illuminate\Support\Facades\Log;

class PoemLikeRepositoryImpl implements PoemLikeRepository
{
    public function create(array $data): PoemLike
    {
        Log::info('PoemLikeRepository - Creating like', ['data' => $data]);
        
        try {
            $like = PoemLike::create($data);
            Log::info('PoemLikeRepository - Like created successfully', ['id' => $like->id]);
            return $like;
        } catch (\Exception $e) {
            Log::error('PoemLikeRepository - Error creating like', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function exists(int $poemId, int $botUserId): bool
    {
        return PoemLike::where('poem_id', $poemId)
            ->where('bot_user_id', $botUserId)
            ->exists();
    }

    public function delete(int $poemId, int $botUserId): bool
    {
        Log::info('PoemLikeRepository - Deleting like', [
            'poem_id' => $poemId,
            'bot_user_id' => $botUserId
        ]);
        
        return PoemLike::where('poem_id', $poemId)
            ->where('bot_user_id', $botUserId)
            ->delete() > 0;
    }

    public function getCount(int $poemId): int
    {
        return PoemLike::where('poem_id', $poemId)->count();
    }
}
