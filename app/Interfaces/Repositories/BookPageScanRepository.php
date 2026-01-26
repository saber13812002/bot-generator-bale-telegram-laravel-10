<?php

namespace App\Interfaces\Repositories;

use App\Models\BookPageScan;
use Illuminate\Database\Eloquent\Collection;

interface BookPageScanRepository
{
    /**
     * Create a new book page scan.
     */
    public function create(array $data): BookPageScan;

    /**
     * Find a scan by ID.
     */
    public function find(int $id): ?BookPageScan;

    /**
     * Get pending approval scans for a bot.
     */
    public function getPendingApproval(int $botId): Collection;

    /**
     * Get approved scans for a bot.
     */
    public function getApproved(int $botId): Collection;

    /**
     * Update scan status.
     */
    public function updateStatus(int $id, string $status, array $additionalData = []): bool;

    /**
     * Get scans by user.
     */
    public function getByUser(int $userId, int $botId): Collection;
}
