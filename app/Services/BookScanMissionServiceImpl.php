<?php

namespace App\Services;

use App\Interfaces\Repositories\BookScanMissionRepository;
use App\Interfaces\Services\BookScanMissionService;
use App\Models\BookPageScan;
use App\Models\BookScanMission;
use Illuminate\Support\Facades\Log;

class BookScanMissionServiceImpl implements BookScanMissionService
{
    public function __construct(
        private BookScanMissionRepository $missionRepository
    ) {}

    public function createMission(BookPageScan $scan): BookScanMission
    {
        Log::info('BookScanMissionService - Creating mission', [
            'scan_id' => $scan->id,
            'bot_id' => $scan->bot_id
        ]);

        return $this->missionRepository->create([
            'bot_id' => $scan->bot_id,
            'book_page_scan_id' => $scan->id,
            'status' => 'pending',
        ]);
    }

    public function getNextPendingMission(int $botId): ?BookScanMission
    {
        $missions = $this->missionRepository->getPending($botId);
        return $missions->first();
    }

    public function assignMission(int $missionId, int $chatId): bool
    {
        Log::info('BookScanMissionService - Assigning mission', [
            'mission_id' => $missionId,
            'chat_id' => $chatId
        ]);

        return $this->missionRepository->assignToUser($missionId, $chatId);
    }

    public function completeMission(int $missionId, int $approvedByChatId): bool
    {
        Log::info('BookScanMissionService - Completing mission', [
            'mission_id' => $missionId,
            'approved_by_chat_id' => $approvedByChatId
        ]);

        return $this->missionRepository->complete($missionId, $approvedByChatId);
    }
}
