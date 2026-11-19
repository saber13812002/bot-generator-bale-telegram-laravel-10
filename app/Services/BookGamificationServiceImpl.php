<?php

namespace App\Services;

use App\Interfaces\Repositories\BookUserScoreRepository;
use App\Interfaces\Services\BookGamificationService;
use App\Models\BookUserScore;
use Illuminate\Support\Facades\Log;

class BookGamificationServiceImpl implements BookGamificationService
{
    public function __construct(
        private BookUserScoreRepository $scoreRepository
    ) {}

    public function awardScanPoints(int $botId, int $userId): BookUserScore
    {
        Log::info('BookGamificationService - Awarding scan points', [
            'bot_id' => $botId,
            'user_id' => $userId,
            'points' => 100
        ]);

        return $this->scoreRepository->addPoints($botId, $userId, 100, 'scan');
    }

    public function awardVoicePoints(int $botId, int $userId): BookUserScore
    {
        Log::info('BookGamificationService - Awarding voice points', [
            'bot_id' => $botId,
            'user_id' => $userId,
            'points' => 200
        ]);

        return $this->scoreRepository->addPoints($botId, $userId, 200, 'voice');
    }

    public function getUserScore(int $botId, int $userId): ?BookUserScore
    {
        return $this->scoreRepository->getUserScore($botId, $userId);
    }

    public function getTopUsers(int $botId, int $limit = 10): array
    {
        return $this->scoreRepository->getTopUsers($botId, $limit);
    }
}
