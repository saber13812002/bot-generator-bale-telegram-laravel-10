<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwner;

interface BotOwnerDashboardServiceInterface
{
    /**
     * @return array{bots: \Illuminate\Support\Collection, stats: array<string, int|bool>}
     */
    public function getDashboardData(BotOwner $owner): array;
}
