<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\LibraryUserBook;
use App\Services\ContentDeliveryServiceImpl;
use App\Services\ContentQueueServiceImpl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookLibraryReaderController extends Controller
{
    public function __construct(
        private BookLibraryService $bookLibraryService,
        private BookLibraryDeliveryService $deliveryService
    ) {}

    public function webhook(Request $request): int
    {
        Log::info('📖 [BookLibraryReader] Webhook received');

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

            $text = trim($bot->Text() ?? '');
            if ($text === '') {
                return 200;
            }

            if (str_starts_with($text, '/start')) {
                $this->handleStart($bot, $chatId, $type, $botMotherId, $botId);
                return 200;
            }

            if ($this->isHelpCommand($text)) {
                $this->sendHelp($bot, $botId);
                return 200;
            }

            // ===== دستورات ادمین مادر =====
            if (AdminHelper::isAdmin((string) $chatId) && $this->handleAdminCommands($bot, $text, $botId, $type)) {
                return 200;
            }

            BotHelper::sendMessage($bot, trans('book_library.reader_use_help'));

            return 200;
        } catch (Exception $e) {
            Log::error('❌ [BookLibraryReader] Error', ['error' => $e->getMessage()]);
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

    private function handleStart(Telegram $bot, int $chatId, string $type, int $botMotherId, ?int $readerBotId): void
    {
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);

        $mainBotId = $this->resolveMainBotId($readerBotId);
        if ($mainBotId) {
            $this->bookLibraryService->markReaderStarted($botUser, $mainBotId);
        }

        BotHelper::sendMessage($bot, trans('book_library.reader_welcome'));
        $this->sendHelp($bot, $readerBotId);
    }

    private function sendHelp(Telegram $bot, ?int $readerBotId): void
    {
        $mainBotId = $this->resolveMainBotId($readerBotId);

        $message = trans('book_library.reader_help');
        if ($mainBotId) {
            $mainBot = Bot::find($mainBotId);
            $mainName = $mainBot?->bale_bot_name ?: $mainBot?->telegram_bot_name;
            if ($mainName) {
                $message .= "\n\n" . trans('book_library.reader_main_bot_hint', ['username' => $mainName]);
            }
        }

        BotHelper::sendMessage($bot, $message);
    }

    private function isHelpCommand(string $text): bool
    {
        $lower = mb_strtolower($text);
        return in_array($lower, ['/help', 'help', 'راهنما', '/راهنما'], true);
    }

    private function handleCallbackQuery(
        Telegram $bot,
        array $callbackQuery,
        int $chatId,
        string $type,
        int $botMotherId,
        ?int $readerBotId
    ): void {
        $callbackData = $callbackQuery['data'] ?? '';
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
        $mainBotId = $this->resolveMainBotId($readerBotId);

        $callbackQueryId = $callbackQuery['id'] ?? null;
        if ($callbackQueryId) {
            $bot->answerCallbackQuery(['callback_query_id' => $callbackQueryId]);
        }

        if (!$mainBotId || !str_starts_with($callbackData, 'bl:qa:')) {
            return;
        }

        $parts = explode(':', $callbackData);
        $action = $parts[2] ?? '';
        $userBookId = (int) ($parts[3] ?? 0);

        $userBook = LibraryUserBook::where('id', $userBookId)
            ->where('bot_user_id', $botUser->id)
            ->first();

        if ($userBook) {
            $this->deliveryService->sendQuickAction($bot, $botUser, $userBook, $mainBotId, $type, $action);
        }
    }

    private function resolveMainBotId(?int $readerBotId): ?int
    {
        if (!$readerBotId) {
            return null;
        }

        $config = \App\Models\LibraryBotConfig::where('reader_bot_id', $readerBotId)->first();

        return $config?->bot_id;
    }

    /**
     * دستورات ادمین مادر در ربات کتابخوان
     */
    private function handleAdminCommands(Telegram $bot, string $text, ?int $readerBotId, string $type): bool
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

            $token = null;
            if ($readerBotId) {
                $readerModel = Bot::find($readerBotId);
                $token = $type === 'bale' ? $readerModel?->bale_bot_token : $readerModel?->telegram_bot_token;
            }

            if ($token) {
                $targetBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
                BotHelper::sendMessageByChatId($targetBot, $targetChatId, "📨 پیام از ادمین:\n\n" . $messageText);
                BotHelper::sendMessage($bot, "✅ پیام به {$targetChatId} ارسال شد.");
            } else {
                BotHelper::sendMessage($bot, "❌ توکن ربات یافت نشد.");
            }
            return true;
        }

        return false;
    }
}
