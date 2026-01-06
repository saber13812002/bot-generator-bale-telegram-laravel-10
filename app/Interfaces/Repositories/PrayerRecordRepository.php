<?php

namespace App\Interfaces\Repositories;

use App\Models\PrayerRecord;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface PrayerRecordRepository
{
    /**
     * ایجاد رکورد جدید نماز
     * 
     * @param array $data
     * @return PrayerRecord
     */
    public function create(array $data): PrayerRecord;

    /**
     * یافتن رکورد با شناسه
     * 
     * @param int $id
     * @return PrayerRecord|null
     */
    public function findById(int $id): ?PrayerRecord;

    /**
     * حذف رکورد با شناسه (فقط برای کاربر خودش)
     * 
     * @param int $id
     * @param int $chatId
     * @param string $origin
     * @return bool
     */
    public function deleteById(int $id, int $chatId, string $origin): bool;

    /**
     * دریافت آمار کاربر
     * 
     * @param int $chatId
     * @param string $origin
     * @return array
     */
    public function getUserStats(int $chatId, string $origin): array;

    /**
     * دریافت رکوردهای یک دوره زمانی
     * 
     * @param int $chatId
     * @param string $origin
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection
     */
    public function getUserRecordsForPeriod(int $chatId, string $origin, Carbon $from, Carbon $to): Collection;

    /**
     * دریافت آمار به تفکیک نوع نماز
     * 
     * @param int $chatId
     * @param string $origin
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @return array
     */
    public function getStatsByPrayerType(int $chatId, string $origin, ?Carbon $from = null, ?Carbon $to = null): array;

    /**
     * دریافت مجموع رکعات کاربر
     * 
     * @param int $chatId
     * @param string $origin
     * @return int
     */
    public function getTotalRakats(int $chatId, string $origin): int;
}
