<?php

namespace App\Repositories;

use App\Interfaces\Repositories\BookUserScoreRepository;
use App\Models\BookUserScore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BookUserScoreRepositoryImpl implements BookUserScoreRepository
{
    public function getOrCreate(int $botId, int $userId): BookUserScore
    {
        return BookUserScore::firstOrCreate(
            ['bot_id' => $botId, 'user_id' => $userId],
            [
                'total_points' => 0,
                'scans_count' => 0,
                'voices_count' => 0,
            ]
        );
    }

    public function addPoints(int $botId, int $userId, int $points, string $type = 'scan'): BookUserScore
    {
        Log::info('BookUserScoreRepository - Adding points', [
            'bot_id' => $botId,
            'user_id' => $userId,
            'points' => $points,
            'type' => $type
        ]);
        
        try {
            $score = $this->getOrCreate($botId, $userId);
            
            $score->total_points += $points;
            
            if ($type === 'scan') {
                $score->scans_count += 1;
            } elseif ($type === 'voice') {
                $score->voices_count += 1;
            }
            
            $score->save();
            
            Log::info('BookUserScoreRepository - Points added successfully', [
                'bot_id' => $botId,
                'user_id' => $userId,
                'total_points' => $score->total_points
            ]);
            
            return $score;
        } catch (\Exception $e) {
            Log::error('BookUserScoreRepository - Error adding points', [
                'error' => $e->getMessage(),
                'bot_id' => $botId,
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    public function getUserScore(int $botId, int $userId): ?BookUserScore
    {
        return BookUserScore::where('bot_id', $botId)
            ->where('user_id', $userId)
            ->first();
    }

    public function getTopUsers(int $botId, int $limit = 10): array
    {
        return BookUserScore::where('bot_id', $botId)
            ->orderBy('total_points', 'desc')
            ->limit($limit)
            ->with('user')
            ->get()
            ->toArray();
    }
}
