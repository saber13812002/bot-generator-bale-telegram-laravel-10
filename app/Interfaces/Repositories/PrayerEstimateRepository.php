<?php

namespace App\Interfaces\Repositories;

use App\Models\PrayerEstimate;

interface PrayerEstimateRepository
{
    /**
     * ایجاد یا به‌روزرسانی تخمین کاربر
     * 
     * @param array $data
     * @return PrayerEstimate
     */
    public function createOrUpdate(array $data): PrayerEstimate;

    /**
     * یافتن تخمین کاربر
     * 
     * @param int $chatId
     * @return PrayerEstimate|null
     */
    public function findByChatId(int $chatId): ?PrayerEstimate;

    /**
     * حذف تخمین کاربر
     * 
     * @param int $chatId
     * @return bool
     */
    public function deleteByChatId(int $chatId): bool;

    /**
     * دریافت پیشرفت کاربر
     * 
     * @param int $chatId
     * @param string $origin
     * @return array|null
     */
    public function getProgress(int $chatId, string $origin): ?array;
}
