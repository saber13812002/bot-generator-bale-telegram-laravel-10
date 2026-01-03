<?php

namespace App\Services;

use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Services\ContentService;
use App\Jobs\SendMissionMediaJob;
use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ContentServiceImpl implements ContentService
{
    private ContentRepository $contentRepository;

    public function __construct(ContentRepository $contentRepository)
    {
        $this->contentRepository = $contentRepository;
    }

    /**
     * Send training media to personnel for a mission.
     */
    public function sendTrainingMedia(int $missionId, int $personnelId, string $type = 'telegram'): bool
    {
        Log::info('Sending training media', [
            'mission_id' => $missionId,
            'personnel_id' => $personnelId,
            'type' => $type
        ]);

        try {
            // Dispatch job to send media sequentially
            SendMissionMediaJob::dispatch($missionId, $personnelId, $type);

            Log::info('Training media job dispatched', [
                'mission_id' => $missionId,
                'personnel_id' => $personnelId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending training media', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get training media for a mission.
     */
    public function getTrainingMedia(int $missionId): Collection
    {
        return $this->contentRepository->getByMission($missionId);
    }
}

