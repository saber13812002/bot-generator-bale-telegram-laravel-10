<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryUserBook;
use App\Interfaces\Services\BookLibraryDeliveryService;
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

            $text = $bot->Text() ?? '';
            if ($text !== '' && str_starts_with(trim($text), '/start')) {
                $this->handleStart($bot, $chatId, $type, $botMotherId, $botId);
            }

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
}
