<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\ContentItem;
use App\Modules\BotOwner\Contracts\BotItemsServiceInterface;

class BotItemsService implements BotItemsServiceInterface
{
    public function getItems(Bot $bot, ?int $categoryId = null): \Illuminate\Support\Collection
    {
        $query = ContentItem::where('bot_id', $bot->id)
            ->with('category', 'assets')
            ->orderBy('queue_order')
            ->orderBy('id');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get();
    }

    public function reorder(Bot $bot, array $order): array
    {
        try {
            foreach ($order as $item) {
                ContentItem::where('id', $item['id'])
                    ->where('bot_id', $bot->id)
                    ->update(['queue_order' => $item['queue_order']]);
            }

            return ['success' => true, 'message' => trans('bot-owner.items_reordered')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.category_update_failed')];
        }
    }
}
