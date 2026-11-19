<?php

namespace App\Repositories;

use App\Interfaces\Repositories\BookPageScanRepository;
use App\Models\BookPageScan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookPageScanRepositoryImpl implements BookPageScanRepository
{
    public function create(array $data): BookPageScan
    {
        Log::info('BookPageScanRepository - Creating scan', ['data' => $data]);
        
        try {
            $scan = BookPageScan::create($data);
            Log::info('BookPageScanRepository - Scan created successfully', ['id' => $scan->id]);
            return $scan;
        } catch (\Exception $e) {
            Log::error('BookPageScanRepository - Error creating scan', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?BookPageScan
    {
        return BookPageScan::find($id);
    }

    public function getPendingApproval(int $botId): Collection
    {
        return BookPageScan::where('bot_id', $botId)
            ->where('status', 'pending_approval')
            ->get();
    }

    public function getApproved(int $botId): Collection
    {
        return BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->get();
    }

    public function updateStatus(int $id, string $status, array $additionalData = []): bool
    {
        Log::info('BookPageScanRepository - Updating scan status', [
            'id' => $id,
            'status' => $status,
            'additional_data' => $additionalData
        ]);
        
        try {
            $scan = BookPageScan::findOrFail($id);
            $scan->status = $status;
            
            foreach ($additionalData as $key => $value) {
                $scan->$key = $value;
            }
            
            $scan->save();
            
            Log::info('BookPageScanRepository - Scan status updated successfully', ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            Log::error('BookPageScanRepository - Error updating scan status', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return false;
        }
    }

    public function getByUser(int $userId, int $botId): Collection
    {
        return BookPageScan::where('user_id', $userId)
            ->where('bot_id', $botId)
            ->get();
    }
}
