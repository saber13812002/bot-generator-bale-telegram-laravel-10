<?php

namespace App\Modules\BotOwner\Contracts;

use App\Models\Bot;
use App\Models\ContentCategory;
use App\Modules\BotOwner\Models\BotOwner;

interface BotCategoryServiceInterface
{
    /**
     * Get all categories for a bot.
     *
     * @param Bot $bot
     * @return \Illuminate\Support\Collection
     */
    public function getCategories(Bot $bot): \Illuminate\Support\Collection;

    /**
     * Create a new category.
     *
     * @param Bot $bot
     * @param array $data
     * @return array{success: bool, message: string, category?: ContentCategory}
     */
    public function create(Bot $bot, array $data): array;

    /**
     * Update a category.
     *
     * @param ContentCategory $category
     * @param array $data
     * @return array{success: bool, message: string}
     */
    public function update(ContentCategory $category, array $data): array;

    /**
     * Delete a category.
     *
     * @param ContentCategory $category
     * @return array{success: bool, message: string}
     */
    public function delete(ContentCategory $category): array;

    /**
     * Reorder categories.
     *
     * @param Bot $bot
     * @param array<int, array{id: int, sort_order: int}> $order
     * @return array{success: bool, message: string}
     */
    public function reorder(Bot $bot, array $order): array;
}
