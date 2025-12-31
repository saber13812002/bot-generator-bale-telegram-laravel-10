<?php

namespace App\Interfaces\Services;

interface QuranBotUserRankingService
{
    public function sendToAllUsers();

    public function specificUserReport($chatId, $bot = null);

    public function allUsersReportDailyWeeklyMonthly($type = null);

    public function getDailyStatistics(): array;

    public function getReferralStatistics(string $chatId): array;

    public function getDailyStatisticsMessage(): string;

    public function getReferralStatisticsMessage(string $chatId): string;
}
