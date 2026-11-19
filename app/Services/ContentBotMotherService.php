<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\BotMotherStateHelper;
use App\Interfaces\Services\ContentQueueService;
use App\Jobs\ContentBroadcastJob;
use App\Jobs\NotifyNewCategoryJob;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentBroadcastJob as ContentBroadcastJobModel;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentPendingUpload;
use App\Models\ContentUserProgress;
use App\Models\LibraryUserSubscription;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentBotMotherService
{
    public function __construct(
        private ContentQueueService $queueService,
        private ContentAdminService $adminService
    ) {}

    public function handleContentCommand(Telegram $bot, string $chatId, string $type): void
    {
        if (!AdminHelper::isAdmin($chatId)) {
            BotHelper::sendMessage($bot, '⛔ فقط ادمین پلتفرم.');
            return;
        }

        $endpointIds = config('content_bots.content_endpoint_ids', ['book-library']);
        $bots = Bot::whereIn('endpoint_id', $endpointIds)->orderByDesc('id')->limit(20)->get();

        if ($bots->isEmpty()) {
            BotHelper::sendMessage($bot, 'هیچ ربات محتوایی یافت نشد. ابتدا book-library بسازید.');
            return;
        }

        $keyboard = [];
        foreach ($bots as $b) {
            $name = $b->bale_bot_name ?? $b->telegram_bot_name ?? ('Bot #' . $b->id);
            $keyboard[] = [$bot->buildInlineKeyBoardButton("📚 {$name} (ID:{$b->id})", callback_data: "bm:cnt:bot:{$b->id}")];
        }

        BotHelper::sendKeyboardMessage($bot, '📂 مدیریت محتوا — ربات را انتخاب کنید:', $bot->buildInlineKeyBoard($keyboard));
    }

    public function handleCallback(Telegram $bot, string $callbackData, string $chatId, string $type): bool
    {
        if (!AdminHelper::isAdmin($chatId)) {
            return false;
        }

        if (str_starts_with($callbackData, 'bm:cnt:bot:')) {
            $botId = (int) str_replace('bm:cnt:bot:', '', $callbackData);
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_MENU, ['content_bot_id' => $botId]);
            $this->showContentMenu($bot, $botId);
            return true;
        }

        if (str_starts_with($callbackData, 'bm:cnt:menu:')) {
            $parts = explode(':', $callbackData);
            $action = $parts[3] ?? '';
            $botId = (int) ($parts[4] ?? 0);
            return $this->handleMenuAction($bot, $action, $botId, $chatId, $type);
        }

        if (str_starts_with($callbackData, 'bm:cnt:acf:')) {
            $parts = explode(':', $callbackData);
            $pendingId = (int) ($parts[3] ?? 0);
            $categoryId = (int) ($parts[4] ?? 0);
            $botId = (int) ($parts[5] ?? 0);
            $item = $this->adminService->assignPendingToCategory($pendingId, $categoryId, $botId);
            if ($item) {
                BotHelper::sendMessage($bot, "✅ فایل به «{$item->category->title}» اضافه شد (ترتیب: {$item->queue_order})");
            } else {
                BotHelper::sendMessage($bot, '❌ فایل یا دسته یافت نشد.');
            }
            $this->showContentMenu($bot, $botId);
            return true;
        }

        if (str_starts_with($callbackData, 'bm:cnt:bcf:')) {
            $parts = explode(':', $callbackData);
            $filter = $parts[3] ?? 'all';
            $botId = (int) ($parts[4] ?? 0);
            $stateData = BotMotherStateHelper::getData($chatId);
            $job = ContentBroadcastJobModel::create([
                'bot_id' => $botId,
                'target_filter' => $filter,
                'message_text' => $stateData['broadcast_text'] ?? null,
                'file_id' => $stateData['broadcast_file_id'] ?? null,
                'file_type' => $stateData['broadcast_file_type'] ?? null,
                'status' => 'pending',
                'created_by_chat_id' => $chatId,
            ]);
            ContentBroadcastJob::dispatch($job->id);
            BotMotherStateHelper::clearState($chatId);
            BotHelper::sendMessage($bot, "✅ پیام همگانی در صف قرار گرفت (Job #{$job->id})");
            return true;
        }

        if (str_starts_with($callbackData, 'bm:cnt:catbc:')) {
            $doBroadcast = str_ends_with($callbackData, ':yes');
            $stateData = BotMotherStateHelper::getData($chatId);
            $botId = (int) ($stateData['content_bot_id'] ?? 0);
            $name = $stateData['category_draft'] ?? '';
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_MENU, ['content_bot_id' => $botId]);
            $category = $this->adminService->createCategory($botId, $name);
            BotHelper::sendMessage($bot, "✅ دسته «{$category->title}» ساخته شد.");
            if ($doBroadcast) {
                $botModel = Bot::find($botId);
                if ($botModel) {
                    NotifyNewCategoryJob::dispatch($category->id, $botId, $type);
                    BotHelper::sendMessage($bot, '📢 اعلان دسته جدید ارسال شد.');
                }
            }
            $this->showContentMenu($bot, $botId);
            return true;
        }

        return false;
    }

    public function handleTextInState(Telegram $bot, string $text, array $stateData, string $chatId, string $type): bool
    {
        $currentState = BotMotherStateHelper::getCurrentState($chatId);
        $botId = (int) ($stateData['content_bot_id'] ?? 0);

        if ($currentState === BotMotherStateHelper::STATE_CONTENT_ADD_CATEGORY) {
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_ADD_CATEGORY_BROADCAST, [
                'content_bot_id' => $botId,
                'category_draft' => $text,
            ]);
            $keyboard = [
                [$bot->buildInlineKeyBoardButton('بله', callback_data: 'bm:cnt:catbc:yes')],
                [$bot->buildInlineKeyBoardButton('خیر', callback_data: 'bm:cnt:catbc:no')],
            ];
            BotHelper::sendKeyboardMessage($bot, 'آیا اعلان دسته جدید به کاربران ارسال شود؟', $bot->buildInlineKeyBoard($keyboard));
            return true;
        }

        if ($currentState === BotMotherStateHelper::STATE_CONTENT_BROADCAST_MESSAGE) {
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_BROADCAST_FILTER, array_merge($stateData, [
                'broadcast_text' => $text,
            ]));
            $this->showBroadcastFilters($bot, $botId);
            return true;
        }

        return false;
    }

    public function handleMediaInState(Telegram $bot, array $update, string $chatId): bool
    {
        $currentState = BotMotherStateHelper::getCurrentState($chatId);
        if ($currentState !== BotMotherStateHelper::STATE_CONTENT_BROADCAST_MESSAGE) {
            return false;
        }

        $stateData = BotMotherStateHelper::getData($chatId);
        $botId = (int) ($stateData['content_bot_id'] ?? 0);
        $message = $update['message'] ?? [];
        $fileId = $message['voice']['file_id'] ?? $message['audio']['file_id'] ?? null;

        if (!$fileId) {
            return false;
        }

        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_BROADCAST_FILTER, array_merge($stateData, [
            'broadcast_file_id' => $fileId,
            'broadcast_file_type' => 'audio',
        ]));
        $this->showBroadcastFilters($bot, $botId);
        return true;
    }

    private function handleMenuAction(Telegram $bot, string $action, int $botId, string $chatId, string $type): bool
    {
        return match ($action) {
            'cats' => $this->listCategories($bot, $botId) || true,
            'pending' => $this->listPending($bot, $botId) || true,
            'stats' => $this->showStats($bot, $botId) || true,
            'addcat' => $this->startAddCategory($bot, $botId, $chatId) || true,
            'broadcast' => $this->startBroadcast($bot, $botId, $chatId) || true,
            'back' => $this->handleContentCommand($bot, $chatId, $type) || true,
            default => false,
        };
    }

    private function showContentMenu(Telegram $bot, int $botId): void
    {
        $keyboard = [
            [$bot->buildInlineKeyBoardButton('📂 دسته‌بندی‌ها', callback_data: "bm:cnt:menu:cats:{$botId}")],
            [$bot->buildInlineKeyBoardButton('📥 فایل‌های در انتظار', callback_data: "bm:cnt:menu:pending:{$botId}")],
            [$bot->buildInlineKeyBoardButton('➕ افزودن دسته', callback_data: "bm:cnt:menu:addcat:{$botId}")],
            [$bot->buildInlineKeyBoardButton('📢 پیام همگانی', callback_data: "bm:cnt:menu:broadcast:{$botId}")],
            [$bot->buildInlineKeyBoardButton('📊 آمار', callback_data: "bm:cnt:menu:stats:{$botId}")],
            [$bot->buildInlineKeyBoardButton('◀️ بازگشت', callback_data: 'bm:cnt:menu:back:0')],
        ];
        BotHelper::sendKeyboardMessage($bot, "⚙️ مدیریت محتوا — Bot ID: {$botId}", $bot->buildInlineKeyBoard($keyboard));
    }

    private function listCategories(Telegram $bot, int $botId): void
    {
        $categories = ContentCategory::where('bot_id', $botId)->where('is_active', true)->orderBy('page')->orderBy('sort_order')->get();
        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, 'هنوز دسته‌ای تعریف نشده. CONTENT_SEED_BOT_ID را اجرا کنید یا دسته اضافه کنید.');
            return;
        }

        $message = "📂 دسته‌بندی‌ها:\n\n";
        foreach ($categories as $cat) {
            $count = ContentItem::where('category_id', $cat->id)->where('is_active', true)->count();
            $message .= "• {$cat->title} (صف: {$count} آیتم)\n";
        }
        BotHelper::sendMessage($bot, $message);
        $this->showContentMenu($bot, $botId);
    }

    private function listPending(Telegram $bot, int $botId): void
    {
        $pending = ContentPendingUpload::where('bot_id', $botId)->orderByDesc('id')->limit(10)->get();
        if ($pending->isEmpty()) {
            BotHelper::sendMessage($bot, 'فایل در انتظاری نیست.');
            $this->showContentMenu($bot, $botId);
            return;
        }

        foreach ($pending as $p) {
            $categories = ContentCategory::where('bot_id', $botId)->where('is_active', true)->orderBy('page')->orderBy('sort_order')->get();
            $keyboard = [];
            foreach ($categories as $cat) {
                $keyboard[] = [$bot->buildInlineKeyBoardButton($cat->title, callback_data: "bm:cnt:acf:{$p->id}:{$cat->id}:{$botId}")];
            }
            BotHelper::sendKeyboardMessage($bot, "📥 Pending #{$p->id} — دسته را انتخاب کنید:", $bot->buildInlineKeyBoard($keyboard));
        }
    }

    private function showStats(Telegram $bot, int $botId): void
    {
        $catCount = ContentCategory::where('bot_id', $botId)->count();
        $itemCount = ContentItem::where('bot_id', $botId)->count();
        $userCount = LibraryUserSubscription::where('bot_id', $botId)->count();
        $progressCount = ContentUserProgress::where('bot_id', $botId)->where('last_position', '>', 0)->count();

        $message = "📊 آمار Bot ID {$botId}:\n";
        $message .= "• دسته‌ها: {$catCount}\n";
        $message .= "• آیتم صف: {$itemCount}\n";
        $message .= "• کاربران با پلن: {$userCount}\n";
        $message .= "• کاربران فعال (پیشرفت): {$progressCount}\n";

        BotHelper::sendMessage($bot, $message);
        $this->showContentMenu($bot, $botId);
    }

    private function startAddCategory(Telegram $bot, int $botId, string $chatId): void
    {
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_ADD_CATEGORY, ['content_bot_id' => $botId]);
        BotHelper::sendMessage($bot, 'نام دسته جدید را بفرستید:');
    }

    private function startBroadcast(Telegram $bot, int $botId, string $chatId): void
    {
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_CONTENT_BROADCAST_MESSAGE, ['content_bot_id' => $botId]);
        BotHelper::sendMessage($bot, 'متن یا فایل صوتی پیام همگانی را بفرستید:');
    }

    private function showBroadcastFilters(Telegram $bot, int $botId): void
    {
        $filters = ['all', 'free', 'paid', 'active'];
        $keyboard = [];
        foreach ($filters as $filter) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($filter, callback_data: "bm:cnt:bcf:{$filter}:{$botId}")];
        }
        BotHelper::sendKeyboardMessage($bot, 'فیلتر گیرندگان:', $bot->buildInlineKeyBoard($keyboard));
    }
}
