<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\ContentBotAdminHelper;
use App\Interfaces\Services\BookLibraryDeliveryService;
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
use App\Models\LibraryUserBook;
use App\Models\LibraryBotConfig;
use App\Services\BotAdminKieService;
use App\Services\ContentAdminService;
use App\Services\ContentDeliveryServiceImpl;
use App\Services\ContentQueueServiceImpl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

/**
 * ربات کتابخانه مستقل — همه چیز با یک ربات
 * این ربات به تنهایی هم دسته‌بندی را نشان می‌دهد، هم محتوا را ارسال می‌کند،
 * هم مدیریت ادمین را انجام می‌دهد (بدون نیاز به ربات دوم)
 */
class BookLibraryReaderController extends Controller
{
    public function __construct(
        private BookLibraryService $bookLibraryService,
        private BookLibraryDeliveryService $deliveryService,
        private readonly BotAdminKieService $adminKieService,
        private ContentQueueService $queueService,
        private ContentDeliveryService $contentDeliveryService,
        private BookLibraryPlanService $planService,
        private ContentAdminService $adminService,
    ) {}

    public function webhook(Request $request): int
    {
        Log::info('📖 [BookLibrary] Webhook received', [
            'has_origin' => $request->has('origin'),
            'has_bot_id' => $request->has('bot_id'),
            'has_token' => $request->has('token'),
            'bot_id_param' => $request->input('bot_id'),
            'origin_param' => $request->input('origin'),
        ]);

        try {
            if (!$request->has('origin')) {
                return 200;
            }

            $type = $request->input('origin');
            $botMotherId = (int) $request->input('bot_mother_id', 1);
            $botId = $request->input('bot_id') ? (int) $request->input('bot_id') : null;

            $bot = $this->createBotInstance($request, $type, $botId);
            if (!$bot) {
                return 200;
            }

            $update = $request->json()->all() ?? $request->all();
            $chatId = $bot->ChatID();

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $chatId, $type, $botMotherId, $botId);
                return 200;
            }

            $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
            $instanceBotId = $this->resolveBotId($botUser, $botId);

            // آپلود فایل توسط ادمین
            if ($this->handleMediaUpload($bot, $update, $botUser, $instanceBotId, $type, $chatId)) {
                return 200;
            }

            $text = $bot->Text() ?? '';
            if ($text !== '') {
                $this->handleTextMessage($bot, trim($text), $botUser, $chatId, $type, $botMotherId, $instanceBotId, $update, $request);
            }

            return 200;
        } catch (Exception $e) {
            Log::error('❌ [BookLibrary] Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
            $token = $type === 'bale' ? $botModel?->bale_bot_token : $botModel?->telegram_bot_token;
        }
        if (!$token) return null;
        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function resolveBotId(BotUsers $botUser, ?int $botId): int
    {
        if ($botId) {
            $botUser->settings(['library_bot_instance_id' => $botId]);
            return $botId;
        }
        return (int) $botUser->setting('library_bot_instance_id', 0);
    }

    // ======================== TEXT HANDLING ========================

    private function handleTextMessage(Telegram $bot, string $text, BotUsers $botUser, int $chatId, string $type, int $botMotherId, int $instanceBotId, array $update, Request $request): void
    {
        if (str_starts_with($text, '/start')) {
            $this->showWelcome($bot, $botUser, $instanceBotId);
            return;
        }

        if (!$instanceBotId) {
            BotHelper::sendMessage($bot, '❌ خطا در شناسایی ربات');
            return;
        }

        $botModel = Bot::find($instanceBotId);
        $isOwner = $botModel && ContentBotAdminHelper::isBotOwner($botModel, (string) $chatId, $type);

        // ===== /adminkie =====
        if ($this->adminKieService->isAdminkieCommand($text)) {
            Log::info('📖 [BookLibrary] Handling /adminkie');
            $result = $this->adminKieService->tryHandleFromRequest($request);
            if ($result !== null) return;
            BotHelper::sendMessage($bot, '❌ /adminkie قابل پردازش نیست.');
            return;
        }

        // ===== دستورات ادمین مادر =====
        if (AdminHelper::isAdmin((string) $chatId) && $this->handleSuperAdminCommands($bot, $text, $botModel, $instanceBotId, $type)) {
            return;
        }

        // ===== Help =====
        if (in_array(mb_strtolower($text), ['/help', 'help', 'راهنما', '/راهنما'], true)) {
            $message = "📖 راهنمای ربات کتابخانه\n\n";
            $message .= "دستورات:\n";
            $message .= "/start — شروع\n";
            $message .= "/help — راهنما\n";
            $message .= "/adminkie — درخواست ادمین شدن\n";
            if ($isOwner) {
                $message .= "\n🛠 دستورات مدیریت:\n";
                $message .= "/manage — پنل مدیریت\n";
                $message .= "/categories — لیست دسته‌بندی‌ها\n";
                $message .= "/addcategory — دسته جدید\n";
                $message .= "/broadcast — ارسال همگانی\n";
            }
            BotHelper::sendMessage($bot, $message);
            $this->sendMainMenu($bot);
            return;
        }

        // ===== دستورات مدیریت =====
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
                BotHelper::sendMessage($bot, "❌ فرمت: /addFileToCategory PENDING_ID");
            }
            return;
        }

        if ($isOwner && in_array(mb_strtolower($text), ['/addcategory', '/add_category'], true)) {
            $botUser->settings(['content_wizard' => 'add_category_name']);
            BotHelper::sendMessage($bot, '🏷 نام دسته جدید را وارد کنید:');
            return;
        }

        if ($isOwner && mb_strtolower($text) === '/broadcast') {
            $botUser->settings(['content_wizard' => 'broadcast_message']);
            BotHelper::sendMessage($bot, '📢 پیام همگانی را وارد کنید:');
            return;
        }

        if ($isOwner && in_array(mb_strtolower($text), ['/categories', '/tags', '/دسته‌ها'], true)) {
            $this->showCategoryList($bot, $instanceBotId);
            return;
        }

        // ===== منوهای کاربر =====
        $menuMyBooks = trans('book_library.menu_my_books');
        $menuUpgrade = trans('book_library.menu_upgrade');
        $menuCategories = trans('book_library.menu_categories');

        if ($text === $menuMyBooks) {
            $this->showMyProgress($bot, $botUser, $instanceBotId);
            return;
        }
        if ($text === $menuUpgrade) {
            $this->showPlanMenu($bot, $botUser, $instanceBotId);
            return;
        }
        if ($text === $menuCategories) {
            $this->showCategoryPage($bot, $instanceBotId, 1);
            return;
        }

        BotHelper::sendMessage($bot, '❓ دستور نامشخص. /help را بزنید.');
    }

    // ======================== ADMIN COMMANDS ========================

    private function handleSuperAdminCommands(Telegram $bot, string $text, ?Bot $botModel, int $botId, string $type): bool
    {
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

    private function handleAdminWizardText(Telegram $bot, string $text, BotUsers $botUser, int $botId, string $type): bool
    {
        $wizard = $botUser->setting('content_wizard');
        if (!$wizard) return false;

        if ($wizard === 'add_category_name') {
            $botUser->settings(['content_wizard' => 'add_category_broadcast', 'content_category_draft' => $text]);
            $keyboard = [
                [$bot->buildInlineKeyBoardButton('✅ بله، اطلاع‌رسانی شود', callback_data: 'bl:catbc:yes')],
                [$bot->buildInlineKeyBoardButton('❌ خیر', callback_data: 'bl:catbc:no')],
            ];
            BotHelper::sendKeyboardMessage($bot, "🏷 دسته «{$text}» ساخته شود؟\nآیا به کاربران اطلاع‌رسانی شود؟", $bot->buildInlineKeyBoard($keyboard));
            return true;
        }

        if ($wizard === 'broadcast_message') {
            $botUser->settings(['content_wizard' => 'broadcast_filter', 'content_broadcast_text' => $text]);
            $this->showBroadcastFilterPicker($bot);
            return true;
        }

        return false;
    }

    // ======================== MEDIA UPLOAD ========================

    private function handleMediaUpload(Telegram $bot, array $update, BotUsers $botUser, int $instanceBotId, string $type, int $chatId): bool
    {
        if (!$instanceBotId) return false;

        $botModel = Bot::find($instanceBotId);
        if (!$botModel || !ContentBotAdminHelper::isBotOwner($botModel, (string) $chatId, $type)) {
            return false;
        }

        $message = $update['message'] ?? [];
        $fileId = null;
        $mimeType = null;

        if (isset($message['voice'])) {
            $fileId = $message['voice']['file_id'];
            $mimeType = 'voice';
        } elseif (isset($message['audio'])) {
            $fileId = $message['audio']['file_id'];
            $mimeType = $message['audio']['mime_type'] ?? 'audio';
        } elseif (isset($message['document'])) {
            $mime = $message['document']['mime_type'] ?? '';
            if (str_contains($mime, 'audio') || str_ends_with($message['document']['file_name'] ?? '', '.mp3')) {
                $fileId = $message['document']['file_id'];
                $mimeType = $mime;
            }
        }

        if (!$fileId) return false;

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

        $pending = $this->adminService->storePendingUpload($instanceBotId, (string) $chatId, $type, $fileId, null, $mimeType);
        BotHelper::sendMessage($bot, "✅ فایل دریافت شد.\n📌 برای انتساب به دسته: /addFileToCategory {$pending->id}");
        return true;
    }

    // ======================== CALLBACK QUERY ========================

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, int $chatId, string $type, int $botMotherId, ?int $botId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
        $instanceBotId = $this->resolveBotId($botUser, $botId);

        $callbackQueryId = $callbackQuery['id'] ?? null;
        if ($callbackQueryId) {
            $bot->answerCallbackQuery(['callback_query_id' => $callbackQueryId]);
        }

        // دکمه‌های پنل ادمین
        if ($callbackData === 'bl:admin:categories') {
            $this->showCategoryList($bot, $instanceBotId);
            return;
        }
        if ($callbackData === 'bl:admin:addcat') {
            BotHelper::sendMessage($bot, '🏷 نام دسته جدید را وارد کنید:');
            return;
        }
        if ($callbackData === 'bl:admin:broadcast') {
            BotHelper::sendMessage($bot, '📢 پیام همگانی را وارد کنید:');
            return;
        }

        // صفحه‌بندی دسته‌ها
        if (str_starts_with($callbackData, 'bl:cat_page:')) {
            $page = (int) str_replace('bl:cat_page:', '', $callbackData);
            $this->showCategoryPage($bot, $instanceBotId, $page);
            return;
        }

        // انتخاب دسته
        if (str_starts_with($callbackData, 'bl:cat:')) {
            $categoryId = (int) str_replace('bl:cat:', '', $callbackData);
            $this->deliverNextInCategory($bot, $botUser, $instanceBotId, $type, $categoryId);
            return;
        }

        // انتساب فایل به دسته
        if (str_starts_with($callbackData, 'bl:acf:')) {
            $parts = explode(':', $callbackData);
            $pendingId = (int) ($parts[2] ?? 0);
            $categoryId = (int) ($parts[3] ?? 0);
            $item = $this->adminService->assignPendingToCategory($pendingId, $categoryId, $instanceBotId);
            if ($item) {
                BotHelper::sendMessage($bot, "✅ فایل به دسته «{$item->category->title}» با ترتیب {$item->queue_order} اضافه شد.");
            } else {
                BotHelper::sendMessage($bot, '❌ فایل یافت نشد.');
            }
            return;
        }

        // ساخت دسته
        if (str_starts_with($callbackData, 'bl:catbc:')) {
            $doBroadcast = str_ends_with($callbackData, ':yes');
            $name = $botUser->setting('content_category_draft', '');
            $botUser->settings(['content_wizard' => null, 'content_category_draft' => null]);
            $category = $this->adminService->createCategory($instanceBotId, $name);
            BotHelper::sendMessage($bot, "✅ دسته «{$category->title}» ساخته شد.");
            if ($doBroadcast) {
                $botModel = Bot::find($instanceBotId);
                if ($botModel) {
                    $this->adminService->dispatchNewCategoryNotify($category, $botModel, $type);
                    BotHelper::sendMessage($bot, '📢 اطلاع‌رسانی به کاربران ارسال شد.');
                }
            }
            return;
        }

        // Broadcast فیلتر
        if (str_starts_with($callbackData, 'bl:bcf:')) {
            $filter = str_replace('bl:bcf:', '', $callbackData);
            $botUser->settings(['content_broadcast_filter' => $filter, 'content_wizard' => 'broadcast_confirm']);
            $text = $botUser->setting('content_broadcast_text', '');
            $confirm = "📢 ارسال همگانی\nفیلتر: {$filter}\n\n";
            $confirm .= $text ? "متن:\n{$text}\n\n" : '';
            $keyboard = [
                [$bot->buildInlineKeyBoardButton('✅ ارسال شود', callback_data: 'bl:bcyes')],
                [$bot->buildInlineKeyBoardButton('❌ لغو', callback_data: 'bl:bcno')],
            ];
            BotHelper::sendKeyboardMessage($bot, $confirm, $bot->buildInlineKeyBoard($keyboard));
            return;
        }

        if ($callbackData === 'bl:bcyes') {
            $this->dispatchBroadcast($bot, $botUser, $instanceBotId, (string) $chatId);
            return;
        }
        if ($callbackData === 'bl:bcno') {
            $botUser->settings(['content_wizard' => null, 'content_broadcast_text' => null, 'content_broadcast_file_id' => null, 'content_broadcast_filter' => null]);
            BotHelper::sendMessage($bot, '❌ ارسال همگانی لغو شد.');
            return;
        }

        // طرح/پلن
        if (str_starts_with($callbackData, 'bl:plan:')) {
            $plan = str_replace('bl:plan:', '', $callbackData);
            $result = $this->planService->requestPlanUpgrade($botUser, $instanceBotId, $plan, $botUser->chat_id);
            BotHelper::sendMessage($bot, $result['message']);
            return;
        }

        // Quick Actions (برای کتاب‌های دریافتی قبلی)
        if (str_starts_with($callbackData, 'bl:qa:')) {
            $parts = explode(':', $callbackData);
            $action = $parts[2] ?? '';
            $userBookId = (int) ($parts[3] ?? 0);
            $userBook = LibraryUserBook::where('id', $userBookId)->where('bot_user_id', $botUser->id)->first();
            if ($userBook) {
                $this->deliveryService->sendQuickAction($bot, $botUser, $userBook, $instanceBotId, $type, $action);
            }
            return;
        }
    }

    // ======================== UI HELPERS ========================

    private function showWelcome(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        BotHelper::sendMessage($bot, "📖 به ربات کتابخانه خوش آمدید!\nبرای شروع یک دسته را انتخاب کنید.");
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
        BotHelper::sendKeyboardMessage($bot, '📋 منوی اصلی:', $bot->buildKeyBoard($keyboard, true, true));
    }

    private function showCategoryPage(Telegram $bot, int $botId, int $page): void
    {
        if ($botId <= 0) {
            BotHelper::sendMessage($bot, '❌ خطا');
            return;
        }
        $categories = $this->queueService->getCategoriesForPage($botId, $page);
        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, "🏷 هیچ دسته‌بندی‌ای موجود نیست.");
            return;
        }
        $keyboard = [];
        foreach ($categories as $category) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($category->title, callback_data: "bl:cat:{$category->id}")];
        }
        $maxPage = $this->queueService->getMaxCategoryPage($botId);
        if ($page < $maxPage) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton('▶️ بعدی', callback_data: 'bl:cat_page:' . ($page + 1))];
        }
        BotHelper::sendKeyboardMessage($bot, '📚 دسته‌بندی‌ها:', $bot->buildInlineKeyBoard($keyboard));
    }

    private function deliverNextInCategory(Telegram $bot, BotUsers $botUser, int $botId, string $type, int $categoryId): void
    {
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
            BotHelper::sendMessage($bot, "📭 محتوای جدیدی در دسته «{$category->title}» موجود نیست.\nبه زودی محتوای جدید اضافه خواهد شد.");
            return;
        }
        $delivered = $this->contentDeliveryService->deliverNextInCategory($bot, $botUser, $item, $botId, $type);
        if ($delivered) {
            $this->queueService->advanceProgress($botUser, $categoryId, $botId, $item);
        }
    }

    private function showMyProgress(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $progressList = $this->queueService->getUserProgressList($botUser, $botId, 10);
        if ($progressList->isEmpty()) {
            BotHelper::sendMessage($bot, '📖 شما هنوز کتابی دریافت نکرده‌اید. از منوی دسته‌ها شروع کنید.');
            return;
        }
        $message = "📖 کتاب‌های من:\n\n";
        foreach ($progressList as $index => $progress) {
            $catTitle = $progress->category->title ?? 'نامشخص';
            $itemTitle = $progress->lastItem->title ?? ('#' . $progress->last_position);
            $message .= ($index + 1) . ". {$catTitle} — {$itemTitle}\n";
        }
        BotHelper::sendMessage($bot, $message);
    }

    private function showPlanMenu(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $botId);
        $currentPlan = trans(config('book_library.plans.' . $subscription->plan . '.label_key', 'book_library.plan.free'));
        $message = "⭐ پلن فعلی: {$currentPlan}\n";
        $message .= $this->bookLibraryService->buildProgressBar($subscription) . "\n\n";
        $message .= "پلن‌های قابل ارتقا:\n";
        $keyboard = [];
        foreach (config('book_library.paid_plans', []) as $planKey) {
            $plan = config('book_library.plans.' . $planKey);
            if ($plan) {
                $label = trans($plan['label_key']) . ' - ' . number_format($plan['price']) . ' تومان';
                $keyboard[] = [$bot->buildInlineKeyBoardButton($label, callback_data: "bl:plan:{$planKey}")];
            }
        }
        if (!empty($keyboard)) {
            BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
        } else {
            BotHelper::sendMessage($bot, $message);
        }
    }

    private function showBroadcastFilterPicker(Telegram $bot): void
    {
        $filters = ['all' => 'همه کاربران', 'free' => 'کاربران رایگان', 'paid' => 'کاربران ویژه', 'active' => 'کاربران فعال'];
        $keyboard = [];
        foreach ($filters as $key => $label) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($label, callback_data: "bl:bcf:{$key}")];
        }
        BotHelper::sendKeyboardMessage($bot, '📢 فیلتر ارسال همگانی:', $bot->buildInlineKeyBoard($keyboard));
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
        BotHelper::sendMessage($bot, '✅ ارسال همگانی در صف قرار گرفت.');
    }

    // ======================== ADMIN PANEL ========================

    private function showAdminPanel(Telegram $bot, int $botId): void
    {
        $botModel = Bot::find($botId);
        $botName = $botModel?->bale_bot_name ?? $botModel?->telegram_bot_name ?? "Bot #{$botId}";
        $categories = ContentCategory::where('bot_id', $botId)->where('is_active', true)->count();
        $items = ContentItem::where('bot_id', $botId)->where('is_active', true)->count();

        $message = "🛠 پنل مدیریت «{$botName}»\n\n";
        $message .= "📊 آمار:\n";
        $message .= "🏷 دسته‌بندی‌ها: {$categories}\n";
        $message .= "📦 آیتم‌ها: {$items}\n\n";
        $message .= "📋 دستورات:\n";
        $message .= "➖ /categories — مدیریت دسته‌ها\n";
        $message .= "➖ /addcategory — دسته جدید\n";
        $message .= "➖ /broadcast — ارسال همگانی\n";
        $message .= "➖ /adminkie — درخواست ادمین\n\n";
        $message .= "💡 همچنین می‌توانید فایل صوتی بفرستید تا در صف قرار گیرد.";

        $keyboard = [
            [$bot->buildInlineKeyBoardButton('🏷 دسته‌بندی‌ها', callback_data: "bl:admin:categories")],
            [$bot->buildInlineKeyBoardButton('➕ دسته جدید', callback_data: "bl:admin:addcat")],
            [$bot->buildInlineKeyBoardButton('📢 ارسال همگانی', callback_data: "bl:admin:broadcast")],
        ];
        BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
    }

    private function showCategoryList(Telegram $bot, int $botId): void
    {
        $categories = ContentCategory::where('bot_id', $botId)->orderBy('sort_order')->get();
        if ($categories->isEmpty()) {
            BotHelper::sendMessage($bot, "🏷 هیچ دسته‌بندی‌ای تعریف نشده.\nبرای افزودن: /addcategory\nیا در Nova:\n🔗 http://bots.pardisania.ir/nova/resources/content-categories");
            return;
        }
        $message = "🏷 لیست دسته‌بندی‌ها:\n\n";
        foreach ($categories as $cat) {
            $itemCount = $cat->items()->where('is_active', true)->count();
            $status = $cat->is_active ? '✅' : '⛔';
            $message .= "{$status} #{$cat->id} {$cat->title} ({$itemCount} آیتم)\n";
        }
        BotHelper::sendMessage($bot, $message);
    }
}
