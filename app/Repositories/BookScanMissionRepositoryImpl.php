<?php

namespace App\Repositories;

use App\Interfaces\Repositories\BookScanMissionRepository;
use App\Models\BookScanMission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookScanMissionRepositoryImpl implements BookScanMissionRepository
{
    public function create(array $data): BookScanMission
    {
        Log::info('BookScanMissionRepository - Creating mission', ['data' => $data]);
        
        try {
            $mission = BookScanMission::create($data);
            Log::info('BookScanMissionRepository - Mission created successfully', ['id' => $mission->id]);
            return $mission;
        } catch (\Exception $e) {
            Log::error('BookScanMissionRepository - Error creating mission', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?BookScanMission
    {
        return BookScanMission::find($id);
    }

    public function getPending(int $botId): Collection
    {
        return BookScanMission::where('bot_id', $botId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getAssignedToUser(int $botId, int $chatId): Collection
    {
        return BookScanMission::where('bot_id', $botId)
            ->where('assigned_to_chat_id', $chatId)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderBy('assigned_at', 'desc')
            ->get();
    }

    public function assignToUser(int $id, int $chatId): bool
    {
        $mission = BookScanMission::find($id);
        if (!$mission) {
            return false;
        }

        return $mission->update([
            'assigned_to_chat_id' => $chatId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    public function complete(int $id, int $approvedByChatId): bool
    {
        $mission = BookScanMission::find($id);
        if (!$mission) {
            return false;
        }

        return $mission->update([
            'status' => 'completed',
            'approved_by_chat_id' => $approvedByChatId,
            'completed_at' => now(),
        ]);
    }

    public function cancel(int $id): bool
    {
        $mission = BookScanMission::find($id);
        if (!$mission) {
            return false;
        }

        return $mission->update([
            'status' => 'cancelled',
        ]);
    }
}
