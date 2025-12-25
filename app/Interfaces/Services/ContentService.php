<?php

namespace App\Interfaces\Services;

use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;

interface ContentService
{
    /**
     * Send training media to personnel for a mission.
     */
    public function sendTrainingMedia(int $missionId, int $personnelId, string $type = 'telegram'): bool;

    /**
     * Get training media for a mission.
     */
    public function getTrainingMedia(int $missionId): Collection;
}

