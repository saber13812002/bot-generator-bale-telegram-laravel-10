<?php

namespace App\Interfaces\Services;

use App\Models\BookPageScan;
use App\Models\BookScanMission;

interface BookScanMissionService
{
    /**
     * Create a mission for a scan.
     */
    public function createMission(BookPageScan $scan): BookScanMission;

    /**
     * Get next pending mission.
     */
    public function getNextPendingMission(int $botId): ?BookScanMission;

    /**
     * Assign a mission to a user.
     */
    public function assignMission(int $missionId, int $chatId): bool;

    /**
     * Complete a mission (approve scan).
     */
    public function completeMission(int $missionId, int $approvedByChatId): bool;
}
