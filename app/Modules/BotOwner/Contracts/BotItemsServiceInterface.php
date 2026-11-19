<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;

interface BotItemsServiceInterface
{
    /**
     * Get items grouped by category for a bot.
     *
     * @param Bot $bot
     * @param int|null $categoryId
     * @return \Illuminate\Support\Collection
     */
    public function getItems(Bot $bot, ?int $categoryId = null): \Illuminate\Support\Collection;

    /**
     * Reorder items within a category.
     *
     * @param Bot $bot
     * @param array<int, array{id: int, queue_order: int}> $order
     * @return array{success: bool, message: string}
     */
    public function reorder(Bot $bot, array $order): array;
}
