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
        ?string $mimeType = null
    ): ContentPendingUpload {
        return ContentPendingUpload::create([
            'bot_id' => $botId,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'origin' => $origin,
            'uploaded_by_chat_id' => $chatId,
            'mime_type' => $mimeType,
        ]);
    }

    public function notifyFileReceived(Telegram $bot, ContentPendingUpload $pending): void
    {
        $message = trans('book_library.admin_file_received', ['id' => $pending->id]);
        $message .= "\n\n" . trans('book_library.admin_add_to_category_cmd', ['id' => $pending->id]);
        BotHelper::sendMessage($bot, $message);
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
}
