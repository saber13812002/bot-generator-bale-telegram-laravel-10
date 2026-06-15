<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;

interface BookLibraryPlanService
{
    public function canDeliver(BotUsers $botUser, int $botId): bool;

    public function requestPlanUpgrade(BotUsers $botUser, int $botId, string $plan, string $userIdentifier): array;

    public function confirmPlanRequest(int $requestId, ?string $approvedBy = null): array;
}
