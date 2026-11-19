<?php

namespace App\Interfaces\Repositories;

use App\Models\BookUserScore;

interface BookUserScoreRepository
{
    /**
     * Get or create user score.
     */
    public function getOrCreate(int $botId, int $userId): BookUserScore;

    /**
     * Add points to user score.
     */
    public function addPoints(int $botId, int $userId, int $points, string $type = 'scan'): BookUserScore;

    /**
     * Get user score.
     */
    public function getUserScore(int $botId, int $userId): ?BookUserScore;

    /**
     * Get top users by points.
     */
    public function getTopUsers(int $botId, int $limit = 10): array;
}
