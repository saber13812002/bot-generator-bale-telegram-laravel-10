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

    /**
     * ست کردن state برای کاربر
     * 
     * @param int $botUserId
     * @param int $botMotherId
     * @param string $state
     * @param array|null $data
     * @param int $expiresInMinutes
     * @return \App\Models\BotUserState
     */
    public function setState(
        int $botUserId,
        int $botMotherId,
        string $state,
        ?array $data = null,
        int $expiresInMinutes = 10
    );

    /**
     * دریافت state کاربر
     * 
     * @param int $botUserId
     * @param string|null $state
     * @return \App\Models\BotUserState|null
     */
    public function getState(int $botUserId, ?string $state = null);

    /**
     * پاک کردن state کاربر
     * 
     * @param int $botUserId
     * @param string|null $state
     * @return bool
     */
    public function clearState(int $botUserId, ?string $state = null): bool;

    /**
     * پاک کردن state های منقضی شده
     * 
     * @return int تعداد state های پاک شده
     */
    public function clearExpiredStates(): int;

    /**
     * تبدیل مقدار به رکعت بر اساس واحد
     * 
     * @param int $value
     * @param string $unit (day, week, month, year, rakat)
     * @return int
     */
    public function convertToRakats(int $value, string $unit): int;

    /**
     * محاسبه معادل‌های مختلف برای تعداد رکعت
     * 
     * @param int $rakats
     * @return array
     */
    public function calculateEquivalents(int $rakats): array;
}
