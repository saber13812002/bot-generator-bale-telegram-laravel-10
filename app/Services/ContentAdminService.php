<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Interfaces\Services\ContentQueueService;
use App\Jobs\NotifyNewCategoryJob;
use App\Models\Bot;
use App\Models\ContentCategory;
use App\Models\ContentPendingUpload;
use App\Models\ContentItem;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentAdminService
{
    public function __construct(
        private ContentQueueService $queueService
    ) {}

    public function storePendingUpload(
        int $botId,
        string $chatId,
        string $origin,
        string $fileId,
        ?string $fileUniqueId = null,
        ?string $mimeType = null,
        ?string $title = null
    ): ContentPendingUpload {
        $pending = ContentPendingUpload::create([
            'bot_id' => $botId,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'origin' => $origin,
            'uploaded_by_chat_id' => $chatId,
            'mime_type' => $mimeType,
            'title' => $title, // کل کپشن ذخیره می‌شود (ستون TEXT است) - خط اول بعنوان عنوان و بقیه بعنوان توضیحات استفاده خواهد شد
        ]);

        Log::info('📖 [ContentAdmin] Pending upload stored', [
            'pending_id' => $pending->id,
            'title_from_caption' => $title,
            'bot_id' => $botId,
            'origin' => $origin,
        ]);

        return $pending;
    }

    public function notifyFileReceived(Telegram $bot, ContentPendingUpload $pending): void
    {
        $categories = ContentCategory::where('bot_id', $pending->bot_id)
            ->where('is_active', true)
            ->orderBy('page')
            ->orderBy('sort_order')
            ->get();

        $message = trans('book_library.admin_file_received', ['id' => $pending->id]);

        if ($categories->isNotEmpty()) {
            $message .= "\n\n" . trans('book_library.admin_pick_category');
            $keyboard = [];
            foreach ($categories as $cat) {
                $keyboard[] = [$bot->buildInlineKeyBoardButton($cat->title, callback_data: "bl:acf:{$pending->id}:{$cat->id}")];
            }
            BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
        } else {
            $message .= "\n\n" . trans('book_library.admin_add_to_category_cmd', ['id' => $pending->id]);
            $message .= "\n\n🗑 برای حذف:\n/deletePending_{$pending->id}";
            BotHelper::sendMessage($bot, $message);
        }
    }

    public function showCategoryPickerForPending(Telegram $bot, int $botId, int $pendingId): void
    {
        $pending = ContentPendingUpload::where('id', $pendingId)->where('bot_id', $botId)->first();
        if (!$pending) {
            BotHelper::sendMessage($bot, trans('book_library.admin_pending_not_found'));
            return;
        }

        $categories = ContentCategory::where('bot_id', $botId)->where('is_active', true)->orderBy('page')->orderBy('sort_order')->get();
        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.no_genres'));
            return;
        }

        $keyboard = [];
        foreach ($categories as $cat) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($cat->title, callback_data: "bl:acf:{$pendingId}:{$cat->id}")];
        }

        BotHelper::sendKeyboardMessage($bot, trans('book_library.admin_pick_category'), $bot->buildInlineKeyBoard($keyboard));
    }

    public function assignPendingToCategory(int $pendingId, int $categoryId, int $botId): ?ContentItem
    {
        $pending = ContentPendingUpload::where('id', $pendingId)->where('bot_id', $botId)->first();
        if (!$pending) {
            return null;
        }

        $category = $this->queueService->findCategory($categoryId, $botId);
        if (!$category) {
            return null;
        }

        return $this->queueService->appendPendingToCategory($pending, $categoryId);
    }

    public function createCategory(int $botId, string $title): ContentCategory
    {
        $maxPage = $this->queueService->getMaxCategoryPage($botId);
        $page = $maxPage;
        $countOnPage = ContentCategory::where('bot_id', $botId)->where('page', $page)->count();
        $perPage = config('content_bots.categories_per_page', 5);
        if ($countOnPage >= $perPage) {
            $page = $maxPage + 1;
            $countOnPage = 0;
        }

        return ContentCategory::create([
            'bot_id' => $botId,
            'title' => $title,
            'page' => $page,
            'sort_order' => $countOnPage + 1,
            'is_active' => true,
        ]);
    }

    public function dispatchNewCategoryNotify(ContentCategory $category, Bot $bot, string $origin): void
    {
        NotifyNewCategoryJob::dispatch($category->id, $bot->id, $origin);
        Log::info('📢 [ContentAdmin] New category notify job dispatched', ['category_id' => $category->id]);
    }

    /**
     * حذف pending upload
     */
    public function deletePending(int $pendingId, int $botId): bool
    {
        $pending = ContentPendingUpload::where('id', $pendingId)->where('bot_id', $botId)->first();
        if (!$pending) {
            return false;
        }
        $pending->delete();
        Log::info('📖 [ContentAdmin] Pending upload deleted', ['pending_id' => $pendingId, 'bot_id' => $botId]);
        return true;
    }

    /**
     * حذف نرم آیتم محتوا
     */
    public function deleteContentItem(int $itemId, int $botId): bool
    {
        return $this->queueService->softDeleteItem($itemId, $botId);
    }

    /**
     * نمایش ویرایشگر عنوان و توضیحات برای یک آیتم
     */
    public function showContentEditor(Telegram $bot, int $botId, int $itemId): void
    {
        $item = ContentItem::where('id', $itemId)->where('bot_id', $botId)->first();
        if (!$item) {
            BotHelper::sendMessage($bot, '❌ آیتم یافت نشد.');
            return;
        }

        $message = "📝 ویرایش محتوا #{$item->id}\n\n";
        $message .= "📌 عنوان فعلی:\n{$item->title}\n\n";
        if ($item->description) {
            $message .= "📌 توضیحات فعلی:\n{$item->description}\n\n";
        }
        $message .= "برای ویرایش، از دستورات زیر استفاده کنید:\n";
        $message .= "/editTitle_{$item->id} — ویرایش عنوان\n";
        $message .= "/editDesc_{$item->id} — ویرایش توضیحات\n";
        $message .= "/deleteItem_{$item->id} — حذف آیتم";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * به‌روزرسانی عنوان یا توضیحات آیتم
     */
    public function updateContentItem(int $itemId, int $botId, array $data): ?ContentItem
    {
        return $this->queueService->updateItem($itemId, $botId, $data);
    }
}
