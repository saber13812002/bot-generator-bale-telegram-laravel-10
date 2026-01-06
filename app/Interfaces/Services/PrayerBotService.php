<?php

namespace App\Interfaces\Services;

use App\Models\PrayerRecord;
use App\Models\PrayerEstimate;

interface PrayerBotService
{
    /**
     * ثبت رکعات نماز
     * 
     * @param int $chatId
     * @param int $rakats
     * @param string $origin
     * @param int|null $botId
     * @param int|null $messageId
     * @param string|null $textContext
     * @return PrayerRecord
     */
    public function recordPrayer(
        int $chatId,
        int $rakats,
        string $origin,
        ?int $botId = null,
        ?int $messageId = null,
        ?string $textContext = null
    ): PrayerRecord;

    /**
     * حذف رکعات ثبت شده
     * 
     * @param int $recordId
     * @param int $chatId
     * @param string $origin
     * @return bool
     */
    public function removePrayer(int $recordId, int $chatId, string $origin): bool;

    /**
     * دریافت آمار کاربر
     * 
     * @param int $chatId
     * @param string $origin
     * @return array
     */
    public function getUserStats(int $chatId, string $origin): array;

    /**
     * تولید پیام آمار
     * 
     * @param int $chatId
     * @param string $origin
     * @return string
     */
    public function generateStatsMessage(int $chatId, string $origin): string;

    /**
     * ثبت یا به‌روزرسانی تخمین نماز قضا
     * 
     * @param int $chatId
     * @param int $totalMissedPrayers
     * @param string|null $notes
     * @return PrayerEstimate
     */
    public function setEstimate(int $chatId, int $totalMissedPrayers, ?string $notes = null): PrayerEstimate;

    /**
     * دریافت پیشرفت کاربر نسبت به تخمین
     * 
     * @param int $chatId
     * @param string $origin
     * @return array|null
     */
    public function getProgress(int $chatId, string $origin): ?array;

    /**
     * تولید پیام پیشرفت
     * 
     * @param int $chatId
     * @param string $origin
     * @return string|null
     */
    public function generateProgressMessage(int $chatId, string $origin): ?string;

    /**
     * دریافت گزارش هفتگی کاربر
     * 
     * @param int $chatId
     * @param string $origin
     * @return array
     */
    public function getWeeklyReport(int $chatId, string $origin): array;
}
