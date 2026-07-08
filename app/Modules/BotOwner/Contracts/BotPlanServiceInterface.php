<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Models\LibraryPlanRequest;
use App\Modules\BotOwner\Models\BotOwner;

interface BotPlanServiceInterface
{
    /**
     * Get pending plan requests for a bot.
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getPendingRequests(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Approve a plan request.
     *
     * @param LibraryPlanRequest $planRequest
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function approve(LibraryPlanRequest $planRequest, BotOwner $owner): array;

    /**
     * Reject a plan request.
     *
     * @param LibraryPlanRequest $planRequest
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function reject(LibraryPlanRequest $planRequest, BotOwner $owner): array;
}
