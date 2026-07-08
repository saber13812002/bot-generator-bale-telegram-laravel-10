<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Modules\BotOwner\Models\BotOwner;

interface BotAdminPanelUserServiceInterface
{
    /**
     * Get admin panel users for a bot.
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getAdmins(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Add an admin panel user to a bot.
     *
     * @param Bot $bot
     * @param int $botOwnerId
     * @param BotOwner $addedBy
     * @return array{success: bool, message: string}
     */
    public function addAdmin(Bot $bot, int $botOwnerId, BotOwner $addedBy): array;

    /**
     * Remove an admin panel user from a bot.
     *
     * @param Bot $bot
     * @param int $adminId
     * @param BotOwner $removedBy
     * @return array{success: bool, message: string}
     */
    public function removeAdmin(Bot $bot, int $adminId, BotOwner $removedBy): array;

    /**
     * Search bot owners by phone for adding as admin.
     *
     * @param string $query
     * @return \Illuminate\Support\Collection
     */
    public function searchOwners(string $query): \Illuminate\Support\Collection;
}
