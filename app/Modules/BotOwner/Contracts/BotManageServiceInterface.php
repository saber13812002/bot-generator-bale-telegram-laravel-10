<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Modules\BotOwner\Models\BotOwner;

interface BotManageServiceInterface
{
    /**
     * Get management data for a single bot.
     *
     * @param Bot $bot
     * @param BotOwner $owner
     * @return array<string, mixed>
     */
    public function getManageData(Bot $bot, BotOwner $owner): array;

    /**
     * Get statistics for a bot.
     *
     * @param Bot $bot
     * @return array<string, mixed>
     */
    public function getBotStats(Bot $bot): array;
}
