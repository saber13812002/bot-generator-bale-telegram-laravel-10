<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Modules\BotOwner\Models\BotOwner;

interface BotUploadsServiceInterface
{
    /**
     * Get pending uploads for a bot.
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getPendingUploads(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Get categories for the bot (for assign dropdown).
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getCategories(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Approve a pending upload and assign to category.
     *
     * @param Bot $bot
     * @param int $uploadId
     * @param int $categoryId
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function approve(Bot $bot, int $uploadId, int $categoryId, BotOwner $owner): array;

    /**
     * Reject a pending upload.
     *
     * @param Bot $bot
     * @param int $uploadId
     * @param BotOwner $owner
     * @return array{success: bool, message: string}
     */
    public function reject(Bot $bot, int $uploadId, BotOwner $owner): array;
}
