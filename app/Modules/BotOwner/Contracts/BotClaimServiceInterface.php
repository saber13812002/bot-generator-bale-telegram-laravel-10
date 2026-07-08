<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwner;

interface BotClaimServiceInterface
{
    /**
     * Get bots available for the user to claim.
     * These are bots where bot_owner_id is null OR not linked to this user.
     *
     * @param BotOwner $owner
     * @return \Illuminate\Support\Collection
     */
    public function getClaimableBots(BotOwner $owner): \Illuminate\Support\Collection;

    /**
     * Generate a claim code for a bot.
     *
     * @param BotOwner $owner
     * @param int $botId
     * @param string $claimType 'owner' or 'admin'
     * @return array{success: bool, message: string, claim?: \App\Models\BotOwnershipClaim}
     */
    public function generateClaim(BotOwner $owner, int $botId, string $claimType = 'owner'): array;

    /**
     * Get pending claims for this user.
     *
     * @param BotOwner $owner
     * @return \Illuminate\Support\Collection
     */
    public function getPendingClaims(BotOwner $owner): \Illuminate\Support\Collection;

    /**
     * Verify a claim code from messenger (called by AdminBots webhook).
     *
     * @param string $code The verification code
     * @param string $chatId The sender's chat_id from messenger
     * @param string $origin 'bale' or 'telegram'
     * @return array{success: bool, message: string}
     */
    public function verifyClaim(string $code, string $chatId, string $origin): array;

    /**
     * Get all bot IDs that this owner can access (owned + admin panel).
     *
     * @param BotOwner $owner
     * @return array<int>
     */
    public function getAccessibleBotIds(BotOwner $owner): array;
}
