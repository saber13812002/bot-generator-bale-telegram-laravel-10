<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PrayerRecordRepository;
use App\Models\PrayerRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PrayerRecordRepositoryImpl implements PrayerRecordRepository
{
    /**
     * ایجاد رکورد جدید نماز
     */
    public function create(array $data): PrayerRecord
    {
        Log::info('PrayerRecordRepository - Creating prayer record', ['data' => $data]);

        try {
            $record = PrayerRecord::create($data);
            
            Log::info('PrayerRecordRepository - Prayer record created successfully', [
                'id' => $record->id,
                'chat_id' => $record->chat_id
            ]);

            return $record;
        } catch (Exception $e) {
            Log::error('PrayerRecordRepository - Error creating prayer record', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * یافتن رکورد با شناسه
     */
    public function findById(int $id): ?PrayerRecord
    {
        return PrayerRecord::find($id);
    }

    /**
     * حذف رکورد با شناسه — فقط اگر رکورد متعلق به همان کاربر (chat_id + origin) باشد.
     * در غیر این صورت حذفی انجام نمی‌شود و false برمی‌گردد.
     */
    public function deleteById(int $id, int $chatId, string $origin): bool
    {
        Log::info('PrayerRecordRepository - Deleting prayer record', [
            'id' => $id,
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        try {
            $deleted = PrayerRecord::where('id', $id)
                ->where('chat_id', $chatId)
                ->where('origin', $origin)
                ->delete();

            if ($deleted) {
                Log::info('PrayerRecordRepository - Prayer record deleted successfully', ['id' => $id]);
                return true;
            }

            Log::warning('PrayerRecordRepository - Prayer record not found or not owned by user', [
                'id' => $id,
                'chat_id' => $chatId
            ]);
            return false;
        } catch (Exception $e) {
            Log::error('PrayerRecordRepository - Error deleting prayer record', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    /**
     * دریافت آمار کاربر
     */
    public function getUserStats(int $chatId, string $origin): array
    {
        Log::info('PrayerRecordRepository - Getting user stats', [
            'chat_id' => $chatId,
            'origin' => $origin
        ]);

        try {
            $totalRecords = PrayerRecord::byUser($chatId, $origin)->count();
            $totalRakats = PrayerRecord::byUser($chatId, $origin)->sum('rakats');
            $lastWeekRecords = PrayerRecord::byUser($chatId, $origin)->lastWeek()->count();
            $lastWeekRakats = PrayerRecord::byUser($chatId, $origin)->lastWeek()->sum('rakats');

            $stats = [
                'total_records' => $totalRecords,
                'total_rakats' => $totalRakats,
                'last_week_records' => $lastWeekRecords,
                'last_week_rakats' => $lastWeekRakats,
            ];

            Log::info('PrayerRecordRepository - User stats retrieved', $stats);

            return $stats;
        } catch (Exception $e) {
            Log::error('PrayerRecordRepository - Error getting user stats', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            throw $e;
        }
    }

    /**
     * دریافت رکوردهای یک دوره زمانی
     */
    public function getUserRecordsForPeriod(int $chatId, string $origin, Carbon $from, Carbon $to): Collection
    {
        Log::info('PrayerRecordRepository - Getting user records for period', [
            'chat_id' => $chatId,
            'origin' => $origin,
            'from' => $from->toDateString(),
            'to' => $to->toDateString()
        ]);

        return PrayerRecord::byUser($chatId, $origin)
            ->byPeriod($from, $to)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت آمار به تفکیک نوع نماز
     */
    public function getStatsByPrayerType(int $chatId, string $origin, ?Carbon $from = null, ?Carbon $to = null): array
    {
        Log::info('PrayerRecordRepository - Getting stats by prayer type', [
            'chat_id' => $chatId,
            'origin' => $origin,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString()
        ]);

        $query = PrayerRecord::byUser($chatId, $origin);

        if ($from && $to) {
            $query->byPeriod($from, $to);
        }

        $stats = $query->selectRaw('prayer_type, COUNT(*) as count, SUM(rakats) as total_rakats')
            ->groupBy('prayer_type')
            ->get()
            ->keyBy('prayer_type')
            ->map(fn($item) => [
                'count' => $item->count,
                'total_rakats' => $item->total_rakats
            ])
            ->toArray();

        Log::info('PrayerRecordRepository - Stats by prayer type retrieved', $stats);

        return $stats;
    }

    /**
     * دریافت مجموع رکعات کاربر
     */
    public function getTotalRakats(int $chatId, string $origin): int
    {
        return (int) PrayerRecord::byUser($chatId, $origin)->sum('rakats');
    }
}
