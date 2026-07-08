<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Modules\BotOwner\Models\BotOwner;

interface BotAdminKieServiceInterface
{
    /**
     * Get pending admin requests for a bot.
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getPendingRequests(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Approve an admin kie request.
     *
     * @param BotAdminKieRequest $request
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function approve(BotAdminKieRequest $request, BotOwner $owner): array;

    /**
     * Reject an admin kie request.
     *
     * @param BotAdminKieRequest $request
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function reject(BotAdminKieRequest $request, BotOwner $owner): array;
}
