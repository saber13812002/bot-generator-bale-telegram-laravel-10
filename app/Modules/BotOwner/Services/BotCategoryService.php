<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\ContentCategory;
use App\Modules\BotOwner\Contracts\BotCategoryServiceInterface;

class BotCategoryService implements BotCategoryServiceInterface
{
    public function getCategories(Bot $bot): \Illuminate\Support\Collection
    {
        return ContentCategory::where('bot_id', $bot->id)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(Bot $bot, array $data): array
    {
        try {
            $maxOrder = ContentCategory::where('bot_id', $bot->id)->max('sort_order') ?? 0;

            $category = ContentCategory::create([
                'bot_id' => $bot->id,
                'title' => $data['title'],
                'sort_order' => $maxOrder + 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return [
                'success' => true,
                'message' => trans('bot-owner.category_created'),
                'category' => $category,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.category_create_failed')];
        }
    }

    public function update(ContentCategory $category, array $data): array
    {
        try {
            $category->update([
                'title' => $data['title'] ?? $category->title,
                'is_active' => $data['is_active'] ?? $category->is_active,
                'page' => $data['page'] ?? $category->page,
            ]);

            return ['success' => true, 'message' => trans('bot-owner.category_updated')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.category_update_failed')];
        }
    }

    public function delete(ContentCategory $category): array
    {
        try {
            // Also remove items in this category
            $category->items()->delete();
            $category->delete();

            return ['success' => true, 'message' => trans('bot-owner.category_deleted')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.category_delete_failed')];
        }
    }

    public function reorder(Bot $bot, array $order): array
    {
        try {
            foreach ($order as $item) {
                ContentCategory::where('id', $item['id'])
                    ->where('bot_id', $bot->id)
                    ->update(['sort_order' => $item['sort_order']]);
            }

            return ['success' => true, 'message' => trans('bot-owner.category_reordered')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.category_update_failed')];
        }
    }
}
