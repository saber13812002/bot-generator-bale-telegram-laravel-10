<?php

namespace App\Services;

use App\Interfaces\Services\ContentQueueService;
use App\Models\BotUsers;
use App\Models\ContentAsset;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentPendingUpload;
use App\Models\ContentUserProgress;
use Illuminate\Support\Collection;

class ContentQueueServiceImpl implements ContentQueueService
{
    public function getCategoriesForPage(int $botId, int $page): Collection
    {
        return ContentCategory::where('bot_id', $botId)
            ->where('page', $page)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getMaxCategoryPage(int $botId): int
    {
        return (int) ContentCategory::where('bot_id', $botId)
            ->where('is_active', true)
            ->max('page') ?: 1;
    }

    public function findCategory(int $categoryId, int $botId): ?ContentCategory
    {
        return ContentCategory::where('id', $categoryId)
            ->where('bot_id', $botId)
            ->where('is_active', true)
            ->first();
    }

    public function getProgress(BotUsers $botUser, int $categoryId): int
    {
        $progress = ContentUserProgress::where('bot_user_id', $botUser->id)
            ->where('category_id', $categoryId)
            ->first();

        return $progress?->last_position ?? 0;
    }

    public function getNextItemForUser(BotUsers $botUser, int $categoryId, int $botId): ?ContentItem
    {
        $progress = ContentUserProgress::where('bot_user_id', $botUser->id)
            ->where('category_id', $categoryId)
            ->first();

        $lastPosition = $progress?->last_position ?? 0;
        $lastItemId = $progress?->last_content_item_id;

        // آیتم بعدی بر اساس آخرین position
        $nextOrder = $lastPosition + 1;

        $item = ContentItem::where('category_id', $categoryId)
            ->where('bot_id', $botId)
            ->where('is_active', true)
            ->where('queue_order', $nextOrder)
            ->with('assets')
            ->first();

        // اگر آیتمی پیدا نشد و آخرین آیتم تکراری بود، position را رد کن
        if (!$item && $lastItemId) {
            // شاید position جاب شده، آخرین آیتم تحویل داده شده را چک کن
            $nextItem = ContentItem::where('category_id', $categoryId)
                ->where('bot_id', $botId)
                ->where('is_active', true)
                ->where('id', '>', $lastItemId)
                ->orderBy('queue_order')
                ->with('assets')
                ->first();

            if ($nextItem) {
                // پیشرفت را به روزرسانی کن
                $progress?->update(['last_position' => $nextItem->queue_order - 1]);
                return $nextItem;
            }
        }

        return $item;
    }

    public function advanceProgress(BotUsers $botUser, int $categoryId, int $botId, ContentItem $item): ContentUserProgress
    {
        return ContentUserProgress::updateOrCreate(
            [
                'bot_user_id' => $botUser->id,
                'category_id' => $categoryId,
            ],
            [
                'bot_id' => $botId,
                'last_position' => $item->queue_order,
                'last_content_item_id' => $item->id,
            ]
        );
    }

    public function maxQueueOrder(int $categoryId): int
    {
        return (int) ContentItem::where('category_id', $categoryId)->max('queue_order');
    }

    public function appendPendingToCategory(ContentPendingUpload $pending, int $categoryId, ?string $title = null): ContentItem
    {
        $nextOrder = $this->maxQueueOrder($categoryId) + 1;

        // اولویت عنوان: 1. پارامتر صریح 2. title ذخیره شده در pending (از کپشن) 3. نام پیش‌فرض
        $itemTitle = $title ?: ($pending->title ?: ('فایل ' . $nextOrder));

        $item = ContentItem::create([
            'bot_id' => $pending->bot_id,
            'category_id' => $categoryId,
            'title' => $itemTitle,
            'queue_order' => $nextOrder,
            'is_active' => true,
        ]);

        $asset = new ContentAsset([
            'type' => 'audio',
        ]);
        if ($pending->origin === 'bale') {
            $asset->bale_file_id = $pending->file_id;
        } else {
            $asset->telegram_file_id = $pending->file_id;
        }
        $item->assets()->save($asset);

        $pending->delete();

        return $item->load('category');
    }

    public function getUserProgressList(BotUsers $botUser, int $botId, int $limit = 10): Collection
    {
        return ContentUserProgress::where('bot_user_id', $botUser->id)
            ->where('bot_id', $botId)
            ->where('last_position', '>', 0)
            ->with(['category', 'lastItem'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }
}
