<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\ContentNote;
use Illuminate\Support\Facades\Log;
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

            $botUser = $this->resolveBotUser((string) $chatId, $botMotherId, $type, $botId);
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

    /**
     * پیدا کردن BotUsers مناسب با اولویت رکوردی که library_bot_instance_id دارد
     * (wizard state در این رکورد ذخیره می‌شود)
     */
    private function resolveBotUser(string $chatId, int $botMotherId, string $origin, ?int $botId): BotUsers
    {
        Log::info('🔍 [BookLibrary] resolveBotUser - looking for user', [
            'chat_id' => $chatId,
            'origin' => $origin,
            'botId_param' => $botId,
        ]);

        // اول: رکوردی که library_bot_instance_id دارد (wizard state اینجا ذخیره می‌شود)
        if ($botId) {
            $userWithInstance = BotUsers::where('chat_id', $chatId)
                ->where('origin', $origin)
                ->where('settings', 'like', '%"library_bot_instance_id":' . $botId . '%')
                ->first();

            Log::info('🔍 [BookLibrary] resolveBotUser - search with instance_id', [
                'found_with_instance' => $userWithInstance ? $userWithInstance->id : null,
            ]);

            if ($userWithInstance) {
                return $userWithInstance;
            }
        }

        // دوم: رکورد با chat_id و origin (رفتار پیش‌فرض)
        $fallback = BotUsers::firstOrNew($chatId, $botMotherId, $origin);
        Log::info('🔍 [BookLibrary] resolveBotUser - fallback firstOrNew', [
            'fallback_id' => $fallback->id,
            'fallback_settings' => $fallback->settings,
        ]);
        return $fallback;
    }

    // ======================== TEXT HANDLING ========================

    private function handleTextMessage(Telegram $bot, string $text, BotUsers $botUser, int $chatId, string $type, int $botMotherId, int $instanceBotId, array $update, Request $request): void
    {
        if (str_starts_with($text, '/start')) {
            $this->showWelcome($bot, $botUser, $instanceBotId);
            return;
        }

        if (in_array(mb_strtolower(trim($text)), ['/dailyreport', 'dailyreport', '/myreport'])) {
            \Illuminate\Support\Facades\Artisan::call('user:progress-report', [
                '--user-id' => $botUser->id,
                '--bot-id' => $instanceBotId
            ]);
            return;
        }

        // ===== User Command: /whatsnew =====
        if (in_array(mb_strtolower(trim($text)), ['/whatsnew', 'whatsnew', 'تازه ها'])) {
            $config = \App\Models\LibraryBotConfig::where('reader_bot_id', $instanceBotId)->first();
            $actualBotId = $config ? $config->bot_id : $instanceBotId;

            $categories = \App\Models\ContentCategory::with('items')
                ->where('bot_id', $actualBotId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $progressRecords = \App\Models\ContentUserProgress::where('bot_user_id', $botUser->id)
                ->where('bot_id', $actualBotId)
                ->get()
                ->keyBy('category_id');

            $whatsNewMsg = "🆕 **پادکست‌های جدید برای شما:**\n\n";
            $hasNew = false;

            foreach ($categories as $category) {
                $totalItems = $category->items->count();
                $lastPosition = isset($progressRecords[$category->id]) ? $progressRecords[$category->id]->last_position : 0;
                $newItemsCount = max(0, $totalItems - $lastPosition);

                if ($newItemsCount > 0) {
                    $hasNew = true;
                    $whatsNewMsg .= "🔹 **{$category->title}**: {$newItemsCount} فایل جدید\n";
                    $whatsNewMsg .= "   👉 دریافت: /category{$category->id}\n\n";
                }
            }

            if (!$hasNew) {
                $whatsNewMsg = "در حال حاضر هیچ فایل صوتی جدیدی برای شما اضافه نشده است. شما تمام محتواها را دریافت کرده‌اید! 🎉";
            }

            $whatsNewMsg .= \App\Helpers\BotHelper::getRandomHelpCta($botUser);

            \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, $whatsNewMsg);
            return;
        }

        // ===== Admin Commands: /topusers and /sendmsg =====
        if (str_starts_with(mb_strtolower(trim($text)), '/topusers')) {
            if (\App\Helpers\AdminHelper::isAdmin((string)$chatId)) {
                $topUsers = \App\Models\ContentUserProgress::selectRaw('bot_user_id, sum(last_position) as total_received')
                    ->where('bot_id', $instanceBotId)
                    ->groupBy('bot_user_id')
                    ->orderBy('total_received', 'desc')
                    ->limit(10)
                    ->get();

                if ($topUsers->isEmpty()) {
                    \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, "موردی یافت نشد.");
                    return;
                }

                $msg = "🏆 لیست ۱۰ کاربر برتر (بر اساس تعداد فایل‌های دریافتی):\n\n";
                foreach ($topUsers as $index => $u) {
                    $rank = $index + 1;
                    $msg .= "{$rank}. شناسه کاربر: {$u->bot_user_id} - تعداد فایل: {$u->total_received}\n";
                }

                \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, $msg);
            }
            return;
        }

        if (str_starts_with(mb_strtolower(trim($text)), '/sendmsg ')) {
            if (\App\Helpers\AdminHelper::isAdmin((string)$chatId)) {
                $parts = explode(' ', trim($text), 3);
                if (count($parts) >= 3) {
                    $targetUserId = $parts[1];
                    $messageContent = $parts[2];

                    $targetUser = \App\Models\BotUsers::find($targetUserId);
                    if ($targetUser) {
                        $adminMsg = "پیام ادمین:\n\n" . $messageContent;
                        \App\Helpers\BotHelper::sendMessageByChatId($bot, $targetUser->chat_id, $adminMsg);
                        \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, "✅ پیام با موفقیت به کاربر {$targetUserId} ارسال شد.");
                    } else {
                        \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, "❌ کاربر با شناسه {$targetUserId} یافت نشد.");
                    }
                } else {
                    \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, "❌ فرمت دستور اشتباه است. مثال:\n/sendmsg 123 پیام شما");
                }
            }
            return;
        }

        // ===== User Command: /category {id} =====
        if (str_starts_with(mb_strtolower(trim($text)), '/category')) {
            $catIdStr = trim(str_replace(['/category', ' '], '', mb_strtolower(trim($text))));
            if (is_numeric($catIdStr)) {
                $categoryId = (int) $catIdStr;

                $config = \App\Models\LibraryBotConfig::where('reader_bot_id', $instanceBotId)->first();
                $actualBotId = $config ? $config->bot_id : $instanceBotId;

                $this->deliverNextInCategory($bot, $botUser, $actualBotId, $type, $categoryId);
            } else {
                \App\Helpers\BotHelper::sendMessageByChatId($bot, (string)$chatId, "❌ شناسه دسته نامعتبر است.");
            }
            return;
        }

        // ===== پردازش دستور /tome برای Claim ربات =====
        if (str_starts_with(mb_strtolower(trim($text)), '/tome ')) {
            \Illuminate\Support\Facades\Log::info('📋 [BookLibraryReader] /tome command received', [
                'text' => $text,
                'chat_id' => $chatId,
                'type' => $type,
                'instance_bot_id' => $instanceBotId,
            ]);
            if (\App\Helpers\BotHelper::handleTomeCommand($bot, $text, (string) $chatId, $type, $botModel ?? null)) {
                \Illuminate\Support\Facades\Log::info('✅ [BookLibraryReader] /tome command handled successfully', [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
                return;
            }
            \Illuminate\Support\Facades\Log::warning('⚠️ [BookLibraryReader] /tome command returned false', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
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

        // ===== /library_plan_confirm (پشتیبانی از هر دو فرمت با فاصله و آندرلاین) =====
        if (str_starts_with($text, '/library_plan_confirm')) {
            // تبدیل آندرلاین به فاصله برای یکسان سازی
            $normalized = str_replace('_', ' ', $text);
            $parts = preg_split('/\s+/', $normalized);
            $requestId = (int) ($parts[1] ?? 0);
            if ($requestId > 0) {
                try {
                    $planService = app(\App\Services\BookLibraryPlanServiceImpl::class);
                    // confirmPlanRequest() خودش پیام فعال‌سازی (با یادآوری
                    // هدیه‌ی مایلستون) را به کاربر ارسال می‌کند؛ اینجا فقط
                    // اعلان برای ادمین ارسال می‌شود تا پیام دوباره نشود.
                    $result = $planService->confirmPlanRequest($requestId, 'bot_mother_admin');
                    if ($result['success']) {
                        BotHelper::sendMessage($bot, "✅ پلن #{$requestId} تایید شد.");
                    } else {
                        BotHelper::sendMessage($bot, "❌ خطا: " . ($result['message'] ?? 'نامشخص'));
                    }
                } catch (\Throwable $e) {
                    BotHelper::sendMessage($bot, "❌ خطا: " . $e->getMessage());
                }
            } else {
                BotHelper::sendMessage($bot, "❌ فرمت: /library_plan_confirm REQUEST_ID");
            }
            return;
        }

        // ===== دستورات ادمین مادر =====
        if (AdminHelper::isAdmin((string) $chatId) && $this->handleSuperAdminCommands($bot, $text, $botModel, $instanceBotId, $type)) {
            return;
        }

        // ===== Help =====
        if (in_array(mb_strtolower($text), ['/help', 'help', 'راهنما', '/راهنما'], true)) {
            $message = "📖 راهنمای ربات کتابخانه\n\n";
            $message .= "دستورات عمومی:\n";
            $message .= "🔹 /start — شروع کار با ربات\n";
            $message .= "🔹 /whatsnew — بررسی پادکست‌های جدید اضافه‌شده\n";
            $message .= "🔹 /dailyreport — مشاهده کارنامه فعالیت و دریافت‌های شما\n";
            $message .= "🔹 /help — راهنمای استفاده از ربات\n";
            $message .= "🔹 /adminkie — درخواست ادمین شدن\n";
            if ($isOwner) {
                $message .= "\n🛠 دستورات مدیریت:\n";
                $message .= "🔹 /manage — پنل مدیریت\n";
                $message .= "🔹 /categories — لیست دسته‌بندی‌ها\n";
                $message .= "🔹 /addcategory — دسته جدید\n";
                $message .= "🔹 /broadcast — ارسال همگانی\n";
            }
            if (\App\Helpers\AdminHelper::isAdmin((string)$chatId)) {
                $message .= "\n⚙️ دستورات ادمین:\n";
                $message .= "🔹 /topusers — 🏆 لیست کاربران برتر\n";
                $message .= "🔹 /sendmsg — ✉️ ارسال پیام به کاربر خاص\n";
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

        // ویزارد ادمین (بدون $isOwner چون ویزارد قبلاً ست شده)
        if ($this->handleAdminWizardText($bot, $text, $botUser, $instanceBotId, $type)) {
            return;
        }

        if ($isOwner && str_starts_with($text, '/addFileToCategory')) {
            $normalized = str_replace('_', ' ', $text);
            $parts = preg_split('/\s+/', $normalized);
            $pendingId = (int) ($parts[1] ?? 0);
            if ($pendingId > 0) {
                $this->adminService->showCategoryPickerForPending($bot, $instanceBotId, $pendingId);
            } else {
                BotHelper::sendMessage($bot, "❌ فرمت: /addFileToCategory_PENDING_ID\nمثال: /addFileToCategory_9");
            }
            return;
        }

        // ===== حذف pending upload =====
        if ($isOwner && str_starts_with($text, '/deletePending_')) {
            $pendingId = (int) str_replace('/deletePending_', '', $text);
            if ($pendingId > 0) {
                $deleted = $this->adminService->deletePending($pendingId, $instanceBotId);
                BotHelper::sendMessage($bot, $deleted ? "🗑 فایل از صف انتظار حذف شد." : "❌ فایل یافت نشد.");
            }
            return;
        }

        // ===== نمایش ویرایشگر محتوا =====
        if ($isOwner && str_starts_with($text, '/editContent_')) {
            $itemId = (int) str_replace('/editContent_', '', $text);
            if ($itemId > 0) {
                $this->adminService->showContentEditor($bot, $instanceBotId, $itemId);
            }
            return;
        }

        // ===== ویرایش عنوان (با ویزارد) =====
        if ($isOwner && str_starts_with($text, '/editTitle_')) {
            $itemId = (int) str_replace('/editTitle_', '', $text);
            if ($itemId > 0) {
                $botUser->settings(['content_wizard' => 'edit_title', 'content_edit_item_id' => $itemId]);
                BotHelper::sendMessage($bot, "📝 عنوان جدید را وارد کنید:");
            }
            return;
        }

        // ===== ویرایش توضیحات (با ویزارد) =====
        if ($isOwner && str_starts_with($text, '/editDesc_')) {
            $itemId = (int) str_replace('/editDesc_', '', $text);
            if ($itemId > 0) {
                $botUser->settings(['content_wizard' => 'edit_desc', 'content_edit_item_id' => $itemId]);
                BotHelper::sendMessage($bot, "📝 توضیحات جدید را وارد کنید:");
            }
            return;
        }

        // ===== حذف نرم آیتم =====
        if ($isOwner && str_starts_with($text, '/deleteItem_')) {
            $itemId = (int) str_replace('/deleteItem_', '', $text);
            if ($itemId > 0) {
                $deleted = $this->adminService->deleteContentItem($itemId, $instanceBotId);
                BotHelper::sendMessage($bot, $deleted ? "🗑 آیتم حذف شد." : "❌ آیتم یافت نشد.");
            }
            return;
        }

        if ($isOwner && in_array(mb_strtolower($text), ['/addcategory', '/add_category'], true)) {
            Log::info('📖 [BookLibrary] Setting add_category wizard', [
                'bot_user_id' => $botUser->id,
                'chat_id' => $botUser->chat_id,
                'bot_user_origin' => $botUser->origin,
                'instance_bot_id' => $instanceBotId,
            ]);
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
            $this->showCategoryPage($bot, $botUser, $instanceBotId, 1);
            return;
        }

        BotHelper::sendMessage($bot, '❓ دستور نامشخص. /help را بزنید.');
    }

    // ======================== ADMIN COMMANDS ========================

    private function handleSuperAdminCommands(Telegram $bot, string $text, ?Bot $botModel, int $botId, string $type): bool
    {
        if (str_starts_with($text, '/messagetothischatid')) {
            $normalized = str_replace('_', ' ', $text);
            $parts = explode(' ', $normalized, 3);
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
        Log::info('📖 [BookLibrary] Wizard check', [
            'wizard_state' => $wizard,
            'text' => $text,
            'chat_id' => $botUser->chat_id,
            'bot_user_id' => $botUser->id,
            'bot_user_origin' => $botUser->origin,
        ]);
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

        // ===== ویزارد ویرایش عنوان =====
        if ($wizard === 'edit_title') {
            $itemId = (int) $botUser->setting('content_edit_item_id', 0);
            if ($itemId > 0) {
                $updated = $this->adminService->updateContentItem($itemId, $botId, ['title' => $text]);
                if ($updated) {
                    BotHelper::sendMessage($bot, "✅ عنوان با موفقیت به‌روزرسانی شد.\n📌 برای مشاهده گزینه‌های بیشتر:\n/editContent_{$itemId}");
                } else {
                    BotHelper::sendMessage($bot, '❌ خطا در به‌روزرسانی عنوان.');
                }
            }
            $botUser->settings(['content_wizard' => null, 'content_edit_item_id' => null]);
            return true;
        }

        // ===== ویزارد ویرایش توضیحات =====
        if ($wizard === 'edit_desc') {
            $itemId = (int) $botUser->setting('content_edit_item_id', 0);
            if ($itemId > 0) {
                $updated = $this->adminService->updateContentItem($itemId, $botId, ['description' => $text]);
                if ($updated) {
                    BotHelper::sendMessage($bot, "✅ توضیحات با موفقیت به‌روزرسانی شد.\n📌 برای مشاهده گزینه‌های بیشتر:\n/editContent_{$itemId}");
                } else {
                    BotHelper::sendMessage($bot, '❌ خطا در به‌روزرسانی توضیحات.');
                }
            }
            $botUser->settings(['content_wizard' => null, 'content_edit_item_id' => null]);
            return true;
        }

        return false;
    }

    // ======================== MEDIA UPLOAD ========================

    private function handleMediaUpload(Telegram $bot, array $update, BotUsers $botUser, int $instanceBotId, string $type, int $chatId): bool
    {
        if (!$instanceBotId) return false;

        $message = $update['message'] ?? [];
        $fileId = null;
        $fileUniqueId = null;
        $mimeType = null;
        $caption = $message['caption'] ?? null; // کپشنی که کاربر همراه فایل فرستاده

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

        if (!$fileId) return false;

        Log::info('📖 [BookLibrary] Media upload detected', [
            'has_caption' => $caption !== null,
            'caption_text' => $caption,
            'file_unique_id' => $fileUniqueId,
            'chat_id' => $chatId,
        ]);

        // بررسی تکراری نبودن file_unique_id در صف انتظار
        if ($fileUniqueId) {
            $existingPending = \App\Models\ContentPendingUpload::where('file_unique_id', $fileUniqueId)
                ->where('bot_id', $instanceBotId)
                ->where('origin', $type)
                ->first();
            if ($existingPending) {
                BotHelper::sendMessageByChatId($bot, $chatId, "✅ این فایل قبلاً ارسال شده است.\n📌 Pending ID: {$existingPending->id}\nبرای انتساب (کلیکی):\n/addFileToCategory_{$existingPending->id}");
                return true;
            }
        }

        $botModel = Bot::find($instanceBotId);
        $isOwner = $botModel && ContentBotAdminHelper::isBotOwner($botModel, (string) $chatId, $type);

        // اگر ادمین است و در حالت ویزارد broadcast است
        if ($isOwner) {
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

            // ادمین: مستقیم به صف Pending + نمایش دکمه‌های دسته‌بندی
            $pending = $this->adminService->storePendingUpload($instanceBotId, (string) $chatId, $type, $fileId, $fileUniqueId, $mimeType, $caption);
            $this->adminService->notifyFileReceived($bot, $pending);
            return true;
        }

        // کاربر عادی: فایل را به ادمین ارسال کن برای تایید
        $this->forwardFileToAdmin($bot, $botModel, $instanceBotId, $chatId, $type, $fileId, $fileUniqueId, $mimeType, $botUser, $caption);
        return true;
    }

    /**
     * ارسال فایل کاربر به ادمین برای تایید
     * فایل هم در صف pending ثبت می‌شود (برای انتساب بعدی با /addFileToCategory)
     * و هم برای ادمین ارسال می‌شود تا گوش دهد
     */
    private function forwardFileToAdmin(Telegram $bot, ?Bot $botModel, int $botId, string $chatId, string $type, string $fileId, ?string $fileUniqueId, ?string $mimeType, BotUsers $botUser, ?string $caption = null): void
    {
        //先在 صف pending ثبت کن (file_id ذخیره می‌شود برای ارسال مجدد)
        $pending = $this->adminService->storePendingUpload($botId, (string) $chatId, $type, $fileId, $fileUniqueId, $mimeType, $caption);

        // اطلاع به کاربر
        BotHelper::sendMessageByChatId($bot, $chatId, "✅ فایل شما دریافت شد (کد: {$pending->id}). پس از تایید ادمین به صف اضافه خواهد شد.");

        // پیدا کردن ادمین ربات (owner)
        $adminChatId = $type === 'bale' ? $botModel?->bale_owner_chat_id : $botModel?->telegram_owner_chat_id;

        if ($adminChatId) {
            $userName = $botUser->alias_name ?: "کاربر {$chatId}";
            $caption = "📤 فایل جدید از {$userName}\n🆔 Chat ID: {$chatId}\n📌 Pending ID: {$pending->id}\n\nبرای انتساب به دسته (کلیکی):\n/addFileToCategory_{$pending->id}_CATEGORY_ID\n\nیا در Nova:\n🔗 http://bots.pardisania.ir/nova/resources/content-items";

            // ارسال فایل به ادمین
            try {
                if ($mimeType === 'voice') {
                    $bot->sendVoice(['chat_id' => $adminChatId, 'voice' => $fileId, 'caption' => $caption]);
                } else {
                    $bot->sendAudio(['chat_id' => $adminChatId, 'audio' => $fileId, 'caption' => $caption]);
                }
            } catch (\Throwable $e) {
                Log::warning('[BookLibrary] Forward to admin failed', ['error' => $e->getMessage()]);
            }
        }

        // به ادمین مادر هم اطلاع بده
        $botName = $botModel ? ($botModel->bale_bot_name ?: $botModel->telegram_bot_name ?: 'ربات') : 'ربات';
        $adminMessage = "📤 کاربر {$chatId} یک فایل صوتی برای ربات «{$botName}» ارسال کرده است.\n";
        $adminMessage .= "🆔 Pending ID: {$pending->id}\n";
        $adminMessage .= "📌 برای انتساب: /addFileToCategory_{$pending->id}_CATEGORY_ID\n";
        $adminMessage .= "🔗 http://bots.pardisania.ir/nova/resources/content-items";
        $this->notifyMotherAdmins($adminMessage);
    }

    private function notifyMotherAdmins(string $message): void
    {
        $configs = [
            ['token' => env('BOT_MOTHER_TOKEN_BALE'), 'type' => 'bale'],
            ['token' => env('BOT_MOTHER_TOKEN_TELEGRAM'), 'type' => 'telegram'],
        ];
        foreach (\App\Helpers\AdminHelper::getAdmins() as $adminChatId) {
            if (empty($adminChatId)) continue;
            foreach ($configs as $config) {
                if (empty($config['token'])) continue;
                try {
                    $adminBot = $config['type'] === 'bale' ? new Telegram($config['token'], 'bale') : new Telegram($config['token']);
                    BotHelper::sendMessageByChatId($adminBot, $adminChatId, $message);
                } catch (\Throwable $e) {
                    Log::warning('[BookLibrary] Admin notify failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    // ======================== CALLBACK QUERY ========================

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, int $chatId, string $type, int $botMotherId, ?int $botId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        // استفاده از resolveBotUser به جای firstOrNew مستقیم
        // تا رکوردی که wizard state در آن ذخیره شده پیدا شود
        $botUser = $this->resolveBotUser((string) $chatId, $botMotherId, $type, $botId);
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
            $botUser->settings(['content_wizard' => 'add_category_name']);
            BotHelper::sendMessage($bot, '🏷 نام دسته جدید را وارد کنید:');
            return;
        }
        if ($callbackData === 'bl:admin:broadcast') {
            $botUser->settings(['content_wizard' => 'broadcast_message']);
            BotHelper::sendMessage($bot, '📢 پیام همگانی را وارد کنید:');
            return;
        }

        // صفحه‌بندی دسته‌ها
        if (str_starts_with($callbackData, 'bl:cat_page:')) {
            $page = (int) str_replace('bl:cat_page:', '', $callbackData);
            $this->showCategoryPage($bot, $botUser, $instanceBotId, $page);
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
            try {
                $parts = explode(':', $callbackData);
                $pendingId = (int) ($parts[2] ?? 0);
                $categoryId = (int) ($parts[3] ?? 0);
                $item = $this->adminService->assignPendingToCategory($pendingId, $categoryId, $instanceBotId);
                if ($item) {
                    BotHelper::sendMessage($bot, "✅ فایل به دسته «{$item->category->title}» با ترتیب {$item->queue_order} اضافه شد.\n📝 برای ویرایش عنوان و توضیحات:\n/editContent_{$item->id}\n🗑 برای حذف:\n/deleteItem_{$item->id}");
                } else {
                    BotHelper::sendMessage($bot, '❌ فایل یافت نشد.');
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
            $this->showCategoryPage($bot, $botUser, $botId, 1);
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

    private function showCategoryPage(Telegram $bot, BotUsers $botUser, int $botId, int $page): void
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

        // --- Recent Categories (Continue Reading) ---
        if ($page === 1) {
            $progressRecords = \App\Models\ContentUserProgress::with('category')
                ->where('bot_user_id', $botUser->id)
                ->where('bot_id', $botId)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            $recentCategories = [];
            foreach ($progressRecords as $progress) {
                if (!$progress->category || !$progress->category->is_active) continue;

                $totalItems = $progress->category->items()->where('is_active', true)->count();
                if ($totalItems > $progress->last_position) {
                    $recentCategories[] = $progress->category;
                }

                if (count($recentCategories) >= 2) break;
            }

            if (count($recentCategories) > 0) {
                $keyboard[] = [$bot->buildInlineKeyBoardButton('👇 ⏳ ادامه فایل‌های قبلی 👇', callback_data: "ignore")];
                $recentRow = [];
                foreach ($recentCategories as $cat) {
                    $recentRow[] = $bot->buildInlineKeyBoardButton('🔥 ' . $cat->title, callback_data: "bl:cat:{$cat->id}");
                }
                $keyboard[] = $recentRow;
                $keyboard[] = [$bot->buildInlineKeyBoardButton('👇 🗂 همه دسته‌بندی‌ها 👇', callback_data: "ignore")];
            }
        }
        // --------------------------------------------

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
        $currency = trans('book_library.currency');
        $message = "⭐ پلن فعلی: {$currentPlan}\n";
        $message .= $this->bookLibraryService->buildProgressBar($subscription) . "\n\n";
        $message .= "پلن‌های قابل ارتقا:\n\n";
        $keyboard = [];
        foreach (config('book_library.paid_plans', []) as $planKey) {
            $plan = config('book_library.plans.' . $planKey);
            if ($plan) {
                $label = trans($plan['label_key']);
                $price = number_format($plan['price']);

                // Demo pricing: show struck-through list price when present
                $message .= $label . ' ';
                if (!empty($plan['list_price'])) {
                    $message .= trans('book_library.plan_offer_line', [
                        'list' => number_format($plan['list_price']),
                        'price' => $price,
                        'currency' => $currency,
                    ]);
                } else {
                    $message .= trans('book_library.plan_price_line', ['price' => $price, 'currency' => $currency]);
                }
                $message .= "\n";

                // Button labels can't carry HTML markup — plain offer price only
                $buttonLabel = $label . ' - ' . $price . ' ' . $currency;
                $keyboard[] = [$bot->buildInlineKeyBoardButton($buttonLabel, callback_data: "bl:plan:{$planKey}")];
            }
        }
        $message .= "\n" . trans('book_library.reward_menu_hint');
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
        $keyboard = [];
        foreach ($categories as $cat) {
            $itemCount = $cat->items()->where('is_active', true)->count();
            $status = $cat->is_active ? '✅' : '⛔';
            $message .= "{$status} {$cat->title} ({$itemCount} آیتم)\n";
            $keyboard[] = [$bot->buildInlineKeyBoardButton("{$cat->title} ({$itemCount})", callback_data: "bl:cat:{$cat->id}")];
        }
        $message .= "\n📌 روی دکمه هر دسته کلیک کنید تا محتوا دریافت کنید.";
        BotHelper::sendKeyboardMessage($bot, $message, $bot->buildInlineKeyBoard($keyboard));
    }
}
