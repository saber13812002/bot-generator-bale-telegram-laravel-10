<?php

namespace App\Interfaces\Repositories;

use App\Models\BookScanMission;
use Illuminate\Database\Eloquent\Collection;

interface BookScanMissionRepository
{
    /**
     * Create a new mission.
     */
    public function create(array $data): BookScanMission;

    /**
     * Find a mission by ID.
     */
    public function find(int $id): ?BookScanMission;

    /**
     * Get pending missions for a bot.
     */
    public function getPending(int $botId): Collection;

    /**
     * Get missions assigned to a user.
     */
    public function getAssignedToUser(int $botId, int $chatId): Collection;

    /**
     * Assign a mission to a user.
     */
    public function assignToUser(int $id, int $chatId): bool;

    /**
     * Complete a mission.
     */
    public function complete(int $id, int $approvedByChatId): bool;

    /**
     * Cancel a mission.
     */
    public function cancel(int $id): bool;
}
