<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\ContentBotAdminHelper;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Interfaces\Services\ContentQueueService;
use App\Jobs\ContentBroadcastJob;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentBroadcastJob as ContentBroadcastJobModel;
use App\Models\ContentPendingUpload;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Services\ContentAdminService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookLibraryController extends Controller
{
    public function __construct(
        private ContentQueueService $queueService,
        private ContentDeliveryService $deliveryService,
        private BookLibraryService $bookLibraryService,
        private BookLibraryPlanService $planService,
        private ContentAdminService $adminService
    ) {}

    public function webhook(Request $request): int
    {
        Log::info('📚 [BookLibrary] Webhook received');

        try {
            if (!$request->has('origin')) {
                return 200;
            }

            $type = $request->input('origin');
            $botMotherId = (int) $request->input('bot_mother_id', 1);
            $botId = $request->input('bot_id') ? (int) $request->input('bot_id') : null;

            $bot = $this->createBotInstance($request, $type, $botId);
            if (!$bot) {
                Log::error('❌ [BookLibrary] Could not create bot instance');
                return 200;
            }

            $update = $request->json()->all() ?? $request->all();
            $chatId = $bot->ChatID();

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $chatId, $type, $botMotherId, $botId);
                return 200;
            }

            $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
            $instanceBotId = $this->resolveInstanceBotId($botUser, $botId);

            if ($this->handleMediaUpload($bot, $update, $botUser, $instanceBotId, $type, $chatId)) {
                return 200;
            }

            $text = $bot->Text() ?? '';
            if ($text !== '') {
                $this->handleTextMessage($bot, trim($text), $botUser, $chatId, $type, $botMotherId, $instanceBotId, $update);
            }

            return 200;
        } catch (Exception $e) {
            Log::error('❌ [BookLibrary] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 500;
        }
    }

    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
        } elseif ($botId) {
            $botModel = Bot::find($botId);
            if ($botModel) {
                $token = $type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
            }
        }

        if (!$token) {
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function resolveInstanceBotId(BotUsers $botUser, ?int $botId): int
    {
        if ($botId) {
            $botUser->settings(['library_bot_instance_id' => $botId]);
            return $botId;
        }

        return (int) $botUser->setting('library_bot_instance_id', 0);
    }

    private function handleTextMessage(
        Telegram $bot,
        string $text,
        BotUsers $botUser,
        int $chatId,
        string $type,
        int $botMotherId,
        int $instanceBotId,
        array $update
    ): void {
        if (str_starts_with($text, '/start')) {
            $this->showWelcome($bot, $botUser, $instanceBotId);
            return;
        }

        // ===== پردازش دستور /tome برای Claim ربات =====
        if (\App\Helpers\BotHelper::handleTomeCommand($bot, $text, (string) $chatId, $type, $botModel ?? null)) {
            return;
        }

        if (!$instanceBotId) {
            BotHelper::sendMessage($bot, trans('book_library.delivery_error'));
            return;
        }

        $botModel = Bot::find($instanceBotId);
        $isOwner = $botModel && ContentBotAdminHelper::isBotOwner($botModel, (string) $chatId, $type);
        $isSuperAdmin = AdminHelper::isAdmin($chatId);

        // ===== دستورات ادمین مادر =====
        if ($isSuperAdmin && $this->handleSuperAdminCommands($bot, $text, $botModel, $instanceBotId, $type)) {
            return;
        }

        if (in_array(mb_strtolower($text), ['/help', 'help', 'راهنما', '/راهنما'], true)) {
            $message = trans('book_library.main_help');
            if ($isOwner) {
                $message .= "\n\n" . trans('book_library.admin_manage_help_hint');
            }
            BotHelper::sendMessage($bot, $message);
            $this->sendMainMenu($bot);
            return;
        }

        // ===== دستورات مدیریتی ادمین ربات =====
        if ($isOwner && in_array(mb_strtolower($text), ['/manage', '/مدیریت'], true)) {
            $this->showAdminPanel($bot, $instanceBotId);
            return;
        }

        if ($isOwner && $this->handleAdminWizardText($bot, $text, $botUser, $instanceBotId, $type)) {
            return;
        }

        if ($isOwner && str_starts_with($text, '/addFileToCategory')) {
            $parts = preg_split('/\s+/', $text);
            $pendingId = (int) ($parts[1] ?? 0);
            if ($pendingId > 0) {
                $this->adminService->showCategoryPickerForPending($bot, $instanceBotId, $pendingId);
            } else {
                BotHelper::sendMessage($bot, trans('book_library.admin_add_to_category_usage'));
            }
            return;
        }

        if ($isOwner && in_array(mb_strtolower($text), ['/addcategory', '/add_category'], true)) {
            $botUser->settings(['content_wizard' => 'add_category_name']);
            BotHelper::sendMessage($bot, trans('book_library.admin_category_name_prompt'));
            return;
        }

        if ($isOwner && mb_strtolower($text) === '/broadcast') {
            $botUser->settings(['content_wizard' => 'broadcast_message']);
            BotHelper::sendMessage($bot, trans('book_library.broadcast_message_prompt'));
            return;
        }

        if ($isOwner && in_array(mb_strtolower($text), ['/categories', '/tags', '/دسته‌ها'], true)) {
            $this->showAdminCategoryList($bot, $instanceBotId, $botModel, $type);
            return;
        }

        // ===== منوهای کاربر =====
        if ($text === trans('book_library.menu_my_books')) {
            $this->showMyProgress($bot, $botUser, $instanceBotId);
            return;
        }

        if ($text === trans('book_library.menu_upgrade')) {
            $this->showPlanMenu($bot, $botUser, $instanceBotId);
            return;
        }

        if ($text === trans('book_library.menu_categories')) {
            $this->showCategoryPage($bot, $instanceBotId, 1);
            return;
        }

        BotHelper::sendMessage($bot, trans('book_library.use_menu'));
    }

    /**
     * دستورات ادمین مادر
     */
    private function handleSuperAdminCommands(Telegram $bot, string $text, ?Bot $botModel, int $botId, string $type): bool
    {
        // /messagetothischatid CHAT_ID MESSAGE
        if (str_starts_with($text, '/messagetothischatid')) {
            $parts = explode(' ', $text, 3);
            $targetChatId = $parts[1] ?? '';
            $messageText = $parts[2] ?? '';

            if (empty($targetChatId) || empty($messageText)) {
                BotHelper::sendMessage($bot, "❌ فرمت: /messagetothischatid CHAT_ID متن پیام");
                return true;
            }

            $token = $botModel?->bale_bot_token ?? $botModel?->telegram_bot_token;
            if ($token) {
                $origin = $botModel?->bale_bot_token ? 'bale' : 'telegram';
                $targetBot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
                BotHelper::sendMessageByChatId($targetBot, $targetChatId, "📨 پیام از ادمین:\n\n" . $messageText);
                BotHelper::sendMessage($bot, "✅ پیام به {$targetChatId} ارسال شد.");
            } else {
                BotHelper::sendMessage($bot, "❌ توکن ربات یافت نشد.");
            }
            return true;
        }

        return false;
    }

    /**
     * پنل مدیریت ادمین ربات
     */
    private function showAdminPanel(Telegram $bot, int $botId): void
    {
        $botModel = Bot::find($botId);
        $botName = $botModel?->bale_bot_name ?? $botModel?->telegram_bot_name ?? "Bot #{$botId}";

        $categories = ContentCategory::where('bot_id', $botId)->where('is_active', true)->count();
        $items = ContentItem::where('bot_id', $botId)->where('is_active', true)->count();

        $message = "🛠 پنل مدیریت ربات «{$botName}»\n\n";
        $message .= "📊 آمار:\n";
        $message .= "🏷 دسته‌بندی‌ها: {$categories}\n";
        $message .= "📦 آیتم‌ها: {$items}\n\n";
        $message .= "📋 دستورات مدیریت:\n";
        $message .= "➖ /categories — مدیریت دسته‌بندی‌ها\n";
        $message .= "➖ /addcategory — افزودن دسته جدید\n";
        $message .= "➖ /broadcast — ارسال همگانی\n";
        $message .= "➖ /manage — نمایش دوباره این پنل\n\n";
        $message .= "برای افزودن فایل، فقط یک فایل صوتی ارسال کنید.";

        $keyboard = [
            [$bot->buildInlineKeyBoardButton('🏷 دسته‌بندی‌ها', callback_data: "bl:admin:categories")],
            [$bot->buildInlineKeyBoardButton('➕ دسته جدید', callback_data: "bl:admin:addcat")],
            [$bot->buildInlineKeyBoardButton('📢 ارسال همگانی', callback_data: "bl:admin:broadcast")],
        ];
        BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
    }

    /**
     * نمایش لیست دسته‌بندی‌ها برای ادمین
     */
    private function showAdminCategoryList(Telegram $bot, int $botId, ?Bot $botModel, string $type): void
    {
        $categories = ContentCategory::where('bot_id', $botId)->orderBy('sort_order')->get();

        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, "🏷 هیچ دسته‌بندی‌ای تعریف نشده است.\nبرای افزودن: /addcategory");
            return;
        }

        $message = "🏷 لیست دسته‌بندی‌ها:\n\n";
        foreach ($categories as $cat) {
            $itemCount = $cat->items()->where('is_active', true)->count();
            $status = $cat->is_active ? '✅' : '⛔';
            $message .= "{$status} #{$cat->id} {$cat->title} ({$itemCount} آیتم)\n";
        }
        $message .= "\nبرای مدیریت از Nova استفاده کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    private function handleAdminWizardText(
        Telegram $bot,
        string $text,
        BotUsers $botUser,
        int $botId,
        string $type
    ): bool {
        $wizard = $botUser->setting('content_wizard');
        if (!$wizard) {
            return false;
        }

        if ($wizard === 'add_category_name') {
            $botUser->settings(['content_wizard' => 'add_category_broadcast', 'content_category_draft' => $text]);
            $keyboard = [
                [$bot->buildInlineKeyBoardButton(trans('book_library.yes'), callback_data: 'bl:catbc:yes')],
                [$bot->buildInlineKeyBoardButton(trans('book_library.no'), callback_data: 'bl:catbc:no')],
            ];
            BotHelper::sendKeyboardMessage($bot, trans('book_library.admin_category_broadcast_prompt'), $bot->buildInlineKeyBoard($keyboard));
            return true;
        }

        if ($wizard === 'broadcast_message') {
            $botUser->settings(['content_wizard' => 'broadcast_filter', 'content_broadcast_text' => $text]);
            $this->showBroadcastFilterPicker($bot);
            return true;
        }

        return false;
    }

    private function handleMediaUpload(
        Telegram $bot,
        array $update,
        BotUsers $botUser,
        int $instanceBotId,
        string $type,
        int $chatId
    ): bool {
        if (!$instanceBotId) {
            return false;
        }

        $botModel = Bot::find($instanceBotId);
        if (!$botModel || !ContentBotAdminHelper::isBotOwner($botModel, (string) $chatId, $type)) {
            return false;
        }

        $message = $update['message'] ?? [];
        $fileId = null;
        $fileUniqueId = null;
        $mimeType = null;

        if (isset($message['voice'])) {
            $fileId = $message['voice']['file_id'];
            $fileUniqueId = $message['voice']['file_unique_id'] ?? null;
            $mimeType = 'voice';
        } elseif (isset($message['audio'])) {
            $fileId = $message['audio']['file_id'];
            $fileUniqueId = $message['audio']['file_unique_id'] ?? null;
            $mimeType = $message['audio']['mime_type'] ?? 'audio';
        } elseif (isset($message['document'])) {
            $mime = $message['document']['mime_type'] ?? '';
            if (str_contains($mime, 'audio') || str_ends_with($message['document']['file_name'] ?? '', '.mp3')) {
                $fileId = $message['document']['file_id'];
                $fileUniqueId = $message['document']['file_unique_id'] ?? null;
                $mimeType = $mime;
            }
        }

        if (!$fileId) {
            return false;
        }

        $wizard = $botUser->setting('content_wizard');
        if ($wizard === 'broadcast_message') {
            $botUser->settings([
                'content_wizard' => 'broadcast_filter',
                'content_broadcast_file_id' => $fileId,
                'content_broadcast_file_type' => 'audio',
            ]);
            $this->showBroadcastFilterPicker($bot);
            return true;
        }

        $pending = $this->adminService->storePendingUpload($instanceBotId, (string) $chatId, $type, $fileId, $fileUniqueId, $mimeType);
        $this->adminService->notifyFileReceived($bot, $pending);
        return true;
    }

    private function handleCallbackQuery(
        Telegram $bot,
        array $callbackQuery,
        int $chatId,
        string $type,
        int $botMotherId,
        ?int $botId
    ): void {
        $callbackData = $callbackQuery['data'] ?? '';
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
        $instanceBotId = $this->resolveInstanceBotId($botUser, $botId);

        $callbackQueryId = $callbackQuery['id'] ?? null;
        if ($callbackQueryId) {
            $bot->answerCallbackQuery(['callback_query_id' => $callbackQueryId]);
        }

        // ===== دکمه‌های پنل ادمین =====
        if ($callbackData === 'bl:admin:categories') {
            $this->showAdminCategoryList($bot, $instanceBotId, Bot::find($instanceBotId), $type);
            return;
        }

        if ($callbackData === 'bl:admin:addcat') {
            $botUser->settings(['content_wizard' => 'add_category_name']);
            BotHelper::sendMessage($bot, trans('book_library.admin_category_name_prompt'));
            return;
        }

        if ($callbackData === 'bl:admin:broadcast') {
            $botUser->settings(['content_wizard' => 'broadcast_message']);
            BotHelper::sendMessage($bot, trans('book_library.broadcast_message_prompt'));
            return;
        }

        if (str_starts_with($callbackData, 'bl:cat_page:')) {
            $page = (int) str_replace('bl:cat_page:', '', $callbackData);
            $this->showCategoryPage($bot, $instanceBotId, $page);
            return;
        }

        if (str_starts_with($callbackData, 'bl:cat:')) {
            $categoryId = (int) str_replace('bl:cat:', '', $callbackData);
            $this->deliverNextInCategory($bot, $botUser, $instanceBotId, $type, $categoryId);
            return;
        }

        if (str_starts_with($callbackData, 'bl:acf:')) {
            try {
                $parts = explode(':', $callbackData);
                $pendingId = (int) ($parts[2] ?? 0);
                $categoryId = (int) ($parts[3] ?? 0);
                $item = $this->adminService->assignPendingToCategory($pendingId, $categoryId, $instanceBotId);
                if ($item) {
                    BotHelper::sendMessage($bot, trans('book_library.admin_file_added', [
                        'category' => $item->category->title ?? '',
                        'order' => $item->queue_order,
                    ]) . "\n📝 برای ویرایش عنوان و توضیحات:\n/editContent_{$item->id}\n🗑 برای حذف:\n/deleteItem_{$item->id}");
                } else {
                    BotHelper::sendMessage($bot, trans('book_library.admin_pending_not_found'));
                }
            } catch (\Throwable $e) {
                Log::error('❌ [BookLibrary] Error assigning file to category', [
                    'pending_id' => $pendingId,
                    'category_id' => $categoryId,
                    'error' => $e->getMessage(),
                ]);
                BotHelper::sendMessage($bot, '❌ خطا در انتساب فایل به دسته. لطفاً کپشن فایل را کوتاه‌تر کنید و دوباره امتحان کنید.');
            }
            return;
        }

        if (str_starts_with($callbackData, 'bl:catbc:')) {
            $doBroadcast = str_ends_with($callbackData, ':yes');
            $name = $botUser->setting('content_category_draft', '');
            $botUser->settings(['content_wizard' => null, 'content_category_draft' => null]);
            $category = $this->adminService->createCategory($instanceBotId, $name);
            BotHelper::sendMessage($bot, trans('book_library.admin_category_created', ['title' => $category->title]));
            if ($doBroadcast) {
                $botModel = Bot::find($instanceBotId);
                if ($botModel) {
                    $this->adminService->dispatchNewCategoryNotify($category, $botModel, $type);
                    BotHelper::sendMessage($bot, trans('book_library.admin_category_broadcast_sent'));
                }
            }
            return;
        }

        if (str_starts_with($callbackData, 'bl:bcf:')) {
            $filter = str_replace('bl:bcf:', '', $callbackData);
            $botUser->settings(['content_broadcast_filter' => $filter, 'content_wizard' => 'broadcast_confirm']);
            $text = $botUser->setting('content_broadcast_text', '');
            $confirm = trans('book_library.broadcast_confirm', ['filter' => trans('book_library.broadcast_filter_' . $filter)]);
            $confirm .= $text ? "\n\n" . $text : '';
            $keyboard = [
                [$bot->buildInlineKeyBoardButton(trans('book_library.broadcast_send'), callback_data: 'bl:bcyes')],
                [$bot->buildInlineKeyBoardButton(trans('book_library.broadcast_cancel'), callback_data: 'bl:bcno')],
            ];
            BotHelper::sendKeyboardMessage($bot, $confirm, $bot->buildInlineKeyBoard($keyboard));
            return;
        }

        if ($callbackData === 'bl:bcyes') {
            $this->dispatchBroadcast($bot, $botUser, $instanceBotId, (string) $chatId);
            return;
        }

        if ($callbackData === 'bl:bcno') {
            $botUser->settings([
                'content_wizard' => null,
                'content_broadcast_text' => null,
                'content_broadcast_file_id' => null,
                'content_broadcast_filter' => null,
            ]);
            BotHelper::sendMessage($bot, trans('book_library.broadcast_cancelled'));
            return;
        }

        if (str_starts_with($callbackData, 'bl:plan:')) {
            $plan = str_replace('bl:plan:', '', $callbackData);
            $identifier = $botUser->chat_id . ' (' . $type . ')';
            $result = $this->planService->requestPlanUpgrade($botUser, $instanceBotId, $plan, $identifier);
            BotHelper::sendMessage($bot, $result['message']);
        }
    }

    private function showWelcome(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        BotHelper::sendMessage($bot, trans('book_library.welcome'));
        $this->sendMainMenu($bot);
        if ($botId > 0) {
            $this->showCategoryPage($bot, $botId, 1);
        }
    }

    private function sendMainMenu(Telegram $bot): void
    {
        $keyboard = [
            [trans('book_library.menu_categories')],
            [trans('book_library.menu_my_books')],
            [trans('book_library.menu_upgrade')],
        ];
        BotHelper::sendKeyboardMessage($bot, trans('book_library.menu_prompt'), $bot->buildKeyBoard($keyboard, true, true));
    }

    private function showCategoryPage(Telegram $bot, int $botId, int $page): void
    {
        if ($botId <= 0) {
            BotHelper::sendMessage($bot, trans('book_library.delivery_error'));
            return;
        }

        $categories = $this->queueService->getCategoriesForPage($botId, $page);
        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.no_genres') . "\n\n" . trans('book_library.no_categories_admin_hint'));
            return;
        }

        $keyboard = [];
        foreach ($categories as $category) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($category->title, callback_data: "bl:cat:{$category->id}")];
        }

        $maxPage = $this->queueService->getMaxCategoryPage($botId);
        if ($page < $maxPage) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.genre_next'), callback_data: 'bl:cat_page:' . ($page + 1))];
        }

        BotHelper::sendKeyboardMessage($bot, trans('book_library.genre_prompt'), $bot->buildInlineKeyBoard($keyboard));
    }

    private function deliverNextInCategory(
        Telegram $bot,
        BotUsers $botUser,
        int $botId,
        string $type,
        int $categoryId
    ): void {
        if (!$this->planService->canDeliver($botUser, $botId)) {
            BotHelper::sendMessage($bot, trans('book_library.quota_exceeded'));
            $this->showPlanMenu($bot, $botUser, $botId);
            return;
        }

        $category = $this->queueService->findCategory($categoryId, $botId);
        if (!$category) {
            BotHelper::sendMessage($bot, trans('book_library.genre_not_found'));
            return;
        }

        $item = $this->queueService->getNextItemForUser($botUser, $categoryId, $botId);
        if (!$item) {
            BotHelper::sendMessage($bot, trans('book_library.queue_end', ['category' => $category->title]));
            return;
        }

        $delivered = $this->deliveryService->deliverNextInCategory($bot, $botUser, $item, $botId, $type);
        if ($delivered) {
            $this->queueService->advanceProgress($botUser, $categoryId, $botId, $item);
        }
    }

    private function showMyProgress(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $progressList = $this->queueService->getUserProgressList($botUser, $botId, 10);
        if ($progressList->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.my_books_empty'));
            return;
        }

        $message = trans('book_library.my_books_title') . "\n\n";
        foreach ($progressList as $index => $progress) {
            $catTitle = $progress->category->title ?? trans('book_library.unknown_book');
            $itemTitle = $progress->lastItem->title ?? ('#' . $progress->last_position);
            $message .= ($index + 1) . '. ' . $catTitle . ' — ' . $itemTitle . "\n";
        }

        BotHelper::sendMessage($bot, $message);
    }

    private function showPlanMenu(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $botId);
        $currentPlan = trans(config('book_library.plans.' . $subscription->plan . '.label_key', 'book_library.plan.free'));

        $message = trans('book_library.plan_current', ['plan' => $currentPlan]) . "\n";
        $message .= $this->bookLibraryService->buildProgressBar($subscription) . "\n\n";
        $message .= trans('book_library.plan_choose');

        $keyboard = [];
        foreach (config('book_library.paid_plans', []) as $planKey) {
            $plan = config('book_library.plans.' . $planKey);
            if (!$plan) {
                continue;
            }
            $label = trans($plan['label_key']) . ' - ' . number_format($plan['price']) . ' ' . trans('book_library.currency');
            $keyboard[] = [$bot->buildInlineKeyBoardButton($label, callback_data: "bl:plan:{$planKey}")];
        }

        BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
    }

    private function showBroadcastFilterPicker(Telegram $bot): void
    {
        $filters = ['all', 'free', 'paid', 'active'];
        $keyboard = [];
        foreach ($filters as $filter) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(
                trans('book_library.broadcast_filter_' . $filter),
                callback_data: "bl:bcf:{$filter}"
            )];
        }
        BotHelper::sendKeyboardMessage($bot, trans('book_library.broadcast_filter_prompt'), $bot->buildInlineKeyBoard($keyboard));
    }

    private function dispatchBroadcast(Telegram $bot, BotUsers $botUser, int $botId, string $chatId): void
    {
        $filter = $botUser->setting('content_broadcast_filter', 'all');
        $job = ContentBroadcastJobModel::create([
            'bot_id' => $botId,
            'target_filter' => $filter,
            'message_text' => $botUser->setting('content_broadcast_text'),
            'file_id' => $botUser->setting('content_broadcast_file_id'),
            'file_type' => $botUser->setting('content_broadcast_file_type'),
            'status' => 'pending',
            'created_by_chat_id' => $chatId,
        ]);

        ContentBroadcastJob::dispatch($job->id);

        $botUser->settings([
            'content_wizard' => null,
            'content_broadcast_text' => null,
            'content_broadcast_file_id' => null,
            'content_broadcast_filter' => null,
            'content_broadcast_file_type' => null,
        ]);

        BotHelper::sendMessage($bot, trans('book_library.broadcast_queued'));
    }
}
