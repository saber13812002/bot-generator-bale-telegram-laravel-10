<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PrayerEstimateRepository;
use App\Models\PrayerEstimate;
use Illuminate\Support\Facades\Log;
use Exception;

class PrayerEstimateRepositoryImpl implements PrayerEstimateRepository
{
    /**
     * ایجاد یا به‌روزرسانی تخمین کاربر
     */
    public function createOrUpdate(array $data): PrayerEstimate
    {
        Log::info('PrayerEstimateRepository - Creating or updating estimate', ['data' => $data]);

        try {
            $estimate = PrayerEstimate::updateOrCreate(
                ['chat_id' => $data['chat_id']],
                $data
            );

            Log::info('PrayerEstimateRepository - Estimate saved successfully', [
                'id' => $estimate->id,
                'chat_id' => $estimate->chat_id
            ]);

            return $estimate;
        } catch (Exception $e) {
            Log::error('PrayerEstimateRepository - Error saving estimate', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * یافتن تخمین کاربر
     */
    public function findByChatId(int $chatId): ?PrayerEstimate
    {
        return PrayerEstimate::where('chat_id', $chatId)->first();
    }

    /**
     * حذف تخمین کاربر
     */
    public function deleteByChatId(int $chatId): bool
    {
        Log::info('PrayerEstimateRepository - Deleting estimate', ['chat_id' => $chatId]);

        try {
            $deleted = PrayerEstimate::where('chat_id', $chatId)->delete();

            if ($deleted) {
                Log::info('PrayerEstimateRepository - Estimate deleted successfully', ['chat_id' => $chatId]);
                return true;
            }

            Log::warning('PrayerEstimateRepository - Estimate not found', ['chat_id' => $chatId]);
            return false;
        } catch (Exception $e) {
            Log::error('PrayerEstimateRepository - Error deleting estimate', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            throw $e;
        }
    }

    /**
     * دریافت پیشرفت کاربر
     */
    public function getProgress(int $chatId, string $origin): ?array
    {
        Log::info('PrayerEstimateRepository - Getting progress', [
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        $estimate = $this->findByChatId($chatId);

        if (!$estimate) {
            Log::warning('PrayerEstimateRepository - No estimate found', ['chat_id' => $chatId]);
            return null;
        }

        $remaining = $estimate->calculateRemaining($origin);
        $percentage = $estimate->getProgressPercentage($origin);
        $completedPrayers = $estimate->getCompletedPrayers($origin);

        $progress = [
            'total_missed_prayers' => $estimate->total_missed_prayers,
            'total_missed_rakats' => $estimate->total_missed_rakats,
            'estimate' => $estimate->total_missed_rakats,
            'remaining_rakats' => $remaining,
            'progress_percentage' => $percentage,
            'completed_prayers' => $completedPrayers,
            'start_date' => $estimate->start_date?->toDateString(),
        ];

        Log::info('PrayerEstimateRepository - Progress retrieved', $progress);

        return $progress;
    }
}
