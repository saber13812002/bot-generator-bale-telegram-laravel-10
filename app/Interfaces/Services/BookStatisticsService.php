<?php

namespace App\Interfaces\Services;

interface BookStatisticsService
{
    /**
     * Get full statistics for a bot.
     */
    public function getFullStats(int $botId): array;
}
