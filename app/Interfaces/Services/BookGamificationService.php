<?php

namespace App\Interfaces\Services;

use App\Models\BookUserScore;

interface BookGamificationService
{
    /**
     * Award points for a scan (100 points).
     */
    public function awardScanPoints(int $botId, int $userId): BookUserScore;

    /**
     * Award points for a voice (200 points).
     */
    public function awardVoicePoints(int $botId, int $userId): BookUserScore;

    /**
     * Get user score.
     */
    public function getUserScore(int $botId, int $userId): ?BookUserScore;

    /**
     * Get top users.
     */
    public function getTopUsers(int $botId, int $limit = 10): array;
}
