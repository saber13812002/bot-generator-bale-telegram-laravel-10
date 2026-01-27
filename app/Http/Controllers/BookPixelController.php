<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\BookGamificationService;
use App\Interfaces\Services\BookPixelService;
use App\Interfaces\Services\BookPublishingService;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookPixelController extends Controller
{
    public function __construct(
        private BookPixelService $bookPixelService,
        private BookGamificationService $gamificationService,
        private BookPublishingService $publishingService
    ) {}

    public function webhook(Request $request)
    {
        $startTime = microtime(true);
        
        Log::info('🤖 [BookPixel] Webhook received', [
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $botMotherId = $request->input('bot_mother_id');
            $botId = $request->input('bot_id');
            
            $bot = $this->createBotInstance($request, $type, $botId);
            
            if (!$bot) {
                Log::error('❌ [BookPixel] Could not create bot instance');
                return response()->json(['status' => 'error'], 200);
            }

            Log::info('📥 [BookPixel] Request details', [
                'type' => $type,
                'bot_id' => $botId,
                'bot_mother_id' => $botMotherId
            ]);

            $update = $request->json()->all() ?? $request->all();
            
            // Check for callback query first
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botId, $botMotherId);
                return response()->json(['status' => 'ok'], 200);
            }

            // Get chat_id and text safely
            $chatId = $bot->ChatID();
            $text = $bot->Text() ?? '';
            
            Log::info('📨 [BookPixel] Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'has_message' => isset($update['message']),
                'message_type' => $update['message']['photo'] ?? ($update['message']['voice'] ?? ($update['message']['text'] ?? 'unknown'))
            ]);

            // Check for photo (scan or cover)
            if (isset($update['message']['photo'])) {
                $this->handlePhoto($bot, $update['message'], $type, $botId, $botMotherId);
                return response()->json(['status' => 'ok'], 200);
            }

            // Check for voice
            if (isset($update['message']['voice'])) {
                $this->handleVoice($bot, $update['message'], $type, $botId, $botMotherId);
                return response()->json(['status' => 'ok'], 200);
            }

            // Handle text messages (only if text is not empty)
            if (!empty($text)) {
                $this->handleTextMessage($bot, $text, $chatId, $type, $botId, $botMotherId);
            } else {
                Log::warning('⚠️ [BookPixel] Empty text message received', [
                    'chat_id' => $chatId,
                    'update' => $update
                ]);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ [BookPixel] Request processed', [
                'processing_time_ms' => $processingTime
            ]);

            return response()->json(['status' => 'ok'], 200);
            
        } catch (Exception $e) {
            Log::error('❌ [BookPixel] Exception occurred', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['status' => 'error'], 200);
        }
    }

    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
            Log::info('🔑 [BookPixel] Using token from query string');
        } elseif ($botId) {
            $bot = Bot::find($botId);
            if ($bot) {
                $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
                Log::info('🔑 [BookPixel] Using token from database', ['bot_id' => $botId]);
            }
        }

        if (!$token) {
            Log::error('❌ [BookPixel] No token found');
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function handleTextMessage(Telegram $bot, string $text, int $chatId, string $type, int $botId, int $botMotherId): void
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        // Get current state
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        $currentState = $state ? $state->state : null;

        // Handle /start command
        if ($text == '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($bot, $botUser, $botMotherId);
            return;
        }

        // Handle /score command
        if ($text == '/score') {
            $this->handleScore($bot, $botUser, $botId);
            return;
        }

        // Handle state-based messages
        switch ($currentState) {
            case 'waiting_book_name':
                $this->handleBookName($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_isbn':
                $this->handleIsbn($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_shabak':
                // شابک همان ISBN است، پس این state دیگر استفاده نمی‌شود
                // اما برای سازگاری با داده‌های قدیمی، آن را به handleIsbn هدایت می‌کنیم
                $this->handleIsbn($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_cover':
                BotHelper::sendMessage($bot, trans('bot.book_pixel_please_send_cover'));
                break;
            case 'waiting_page_number':
                $this->handlePageNumber($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_scan':
                BotHelper::sendMessage($bot, trans('bot.book_pixel_please_send_scan'));
                break;
            default:
                BotHelper::sendMessage($bot, trans('bot.book_pixel_welcome'));
                $this->setState($botUser, $botMotherId, 'waiting_book_name');
        }
    }

    private function handleStart(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        BotHelper::sendMessage($bot, trans('bot.book_pixel_welcome'));
        $this->setState($botUser, $botMotherId, 'waiting_book_name');
        BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
    }

    private function handleBookName(Telegram $bot, BotUsers $botUser, int $botMotherId, string $bookName, int $botId): void
    {
        // اگر ورودی فقط عدد است، آن را به عنوان ISBN/شابک در نظر بگیریم
        if (is_numeric(trim($bookName))) {
            $isbn = trim($bookName);
            $this->setStateData($botUser, $botMotherId, 'isbn', $isbn);
            $this->setStateData($botUser, $botMotherId, 'shabak', $isbn); // شابک همان ISBN است
            
            // اگر کتاب با این ISBN پیدا شد، از نام آن استفاده می‌کنیم
            $book = $this->bookPixelService->findOrCreateBook("", $isbn, $isbn, $botId, $botUser->id);
            
            if ($book->name && $book->name !== "") {
                $this->setStateData($botUser, $botMotherId, 'book_name', $book->name);
            } else {
                // اگر کتاب پیدا نشد، از ISBN به عنوان نام موقت استفاده می‌کنیم
                $this->setStateData($botUser, $botMotherId, 'book_name', "کتاب با ISBN: " . $isbn);
            }
            
            if ($book->cover_image_file_id) {
                // Book exists, ask for page number
                BotHelper::sendMessage($bot, trans('bot.book_pixel_book_found', ['name' => $book->name]));
                $this->setState($botUser, $botMotherId, 'waiting_page_number');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
            } else {
                // New book, ask for cover
                $this->setState($botUser, $botMotherId, 'waiting_cover');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_cover'));
            }
            return;
        }
        
        // اگر ورودی متن است، به عنوان نام کتاب در نظر می‌گیریم
        $this->setStateData($botUser, $botMotherId, 'book_name', $bookName);
        
        // Try to find book
        $book = $this->bookPixelService->findOrCreateBook($bookName, null, null, $botId, $botUser->id);
        
        if ($book->cover_image_file_id) {
            // Book exists, ask for page number
            BotHelper::sendMessage($bot, trans('bot.book_pixel_book_found', ['name' => $book->name]));
            $this->setState($botUser, $botMotherId, 'waiting_page_number');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
        } else {
            // New book, ask for ISBN (اختیاری)
            $this->setState($botUser, $botMotherId, 'waiting_isbn');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_isbn'));
        }
    }

    private function handleIsbn(Telegram $bot, BotUsers $botUser, int $botMotherId, string $isbn, int $botId): void
    {
        $bookName = $this->getStateData($botUser, $botMotherId, 'book_name');
        $this->setStateData($botUser, $botMotherId, 'isbn', $isbn);
        $this->setStateData($botUser, $botMotherId, 'shabak', $isbn); // شابک همان ISBN است
        
        // مستقیماً به درخواست عکس جلد می‌رویم
        $this->setState($botUser, $botMotherId, 'waiting_cover');
        BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_cover'));
    }

    private function handleShabak(Telegram $bot, BotUsers $botUser, int $botMotherId, string $shabak, int $botId): void
    {
        $bookName = $this->getStateData($botUser, $botMotherId, 'book_name');
        $isbn = $this->getStateData($botUser, $botMotherId, 'isbn');
        $this->setStateData($botUser, $botMotherId, 'shabak', $shabak);
        
        $this->setState($botUser, $botMotherId, 'waiting_cover');
        BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_cover'));
    }

    private function handlePageNumber(Telegram $bot, BotUsers $botUser, int $botMotherId, string $pageNumber, int $botId): void
    {
        $pageNum = (int) $pageNumber;
        if ($pageNum <= 0) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_invalid_page_number'));
            return;
        }

        $bookName = $this->getStateData($botUser, $botMotherId, 'book_name') ?? $this->getStateData($botUser, $botMotherId, 'book_id');
        
        // Find book
        $book = null;
        if (is_numeric($bookName)) {
            $book = \App\Models\Book::find($bookName);
        } else {
            $book = $this->bookPixelService->findOrCreateBook($bookName, null, null, $botId, $botUser->id);
        }

        if (!$book) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_book_not_found'));
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            return;
        }

        $this->setStateData($botUser, $botMotherId, 'book_id', $book->id);
        $this->setStateData($botUser, $botMotherId, 'page_number', $pageNum);
        $this->setState($botUser, $botMotherId, 'waiting_scan');
        BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_scan'));
    }

    private function handlePhoto(Telegram $bot, array $message, string $type, int $botId, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        $currentState = $state ? $state->state : null;

        Log::info('📷 [BookPixel] Photo received', [
            'chat_id' => $chatId,
            'current_state' => $currentState,
            'bot_mother_id' => $botMotherId
        ]);

        // Get photo file_id (largest size)
        $photos = $message['photo'];
        $photo = end($photos);
        $fileId = $photo['file_id'];
        $fileUniqueId = $photo['file_unique_id'] ?? null;

        if ($currentState == 'waiting_cover') {
            $this->handleCoverPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
        } elseif ($currentState == 'waiting_scan') {
            $this->handleScanPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
        } else {
            // اگر state درست نیست، اما نام کتاب یا ISBN وجود دارد، می‌توانیم عکس را بپذیریم
            $bookName = $this->getStateData($botUser, $botMotherId, 'book_name');
            $isbn = $this->getStateData($botUser, $botMotherId, 'isbn');
            
            if ($bookName || $isbn) {
                // اگر اطلاعات کتاب وجود دارد، عکس را به عنوان جلد در نظر می‌گیریم
                $this->handleCoverPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
            } else {
                // اگر هیچ اطلاعاتی وجود ندارد، از کاربر می‌خواهیم ابتدا /start را بزند
                BotHelper::sendMessage($bot, trans('bot.book_pixel_unexpected_photo'));
                $this->setState($botUser, $botMotherId, 'waiting_book_name');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
            }
        }
    }

    private function handleCoverPhoto(Telegram $bot, BotUsers $botUser, int $botMotherId, string $fileId, ?string $fileUniqueId, int $botId): void
    {
        $bookName = $this->getStateData($botUser, $botMotherId, 'book_name');
        $isbn = $this->getStateData($botUser, $botMotherId, 'isbn');
        $shabak = $this->getStateData($botUser, $botMotherId, 'shabak');

        Log::info('📸 [BookPixel] Processing cover photo', [
            'chat_id' => $bot->ChatID(),
            'book_name' => $bookName,
            'isbn' => $isbn,
            'shabak' => $shabak
        ]);

        // اگر نام کتاب وجود ندارد اما ISBN وجود دارد، از ISBN استفاده می‌کنیم
        if ((!$bookName || trim($bookName) === '') && ($isbn || $shabak)) {
            $bookName = "کتاب با ISBN: " . ($isbn ?? $shabak);
            $this->setStateData($botUser, $botMotherId, 'book_name', $bookName);
        }

        // اگر هنوز نام کتاب وجود ندارد، از کاربر بخواهیم ابتدا نام کتاب را وارد کند
        if (!$bookName || trim($bookName) === '') {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            return;
        }

        try {
            $book = $this->bookPixelService->findOrCreateBook($bookName, $isbn, $shabak, $botId, $botUser->id);
            
            $book->cover_image_file_id = $fileId;
            $book->cover_image_file_unique_id = $fileUniqueId;
            $book->save();

            Log::info('✅ [BookPixel] Cover photo saved', [
                'book_id' => $book->id,
                'book_name' => $book->name
            ]);

            BotHelper::sendMessage($bot, trans('bot.book_pixel_book_created', ['name' => $book->name]));
            $this->setState($botUser, $botMotherId, 'waiting_page_number');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
        } catch (Exception $e) {
            Log::error('❌ [BookPixel] Error saving cover photo', [
                'error' => $e->getMessage(),
                'chat_id' => $bot->ChatID()
            ]);
            BotHelper::sendMessage($bot, trans('bot.book_pixel_error_saving_cover'));
        }
    }

    private function handleScanPhoto(Telegram $bot, BotUsers $botUser, int $botMotherId, string $fileId, ?string $fileUniqueId, int $botId): void
    {
        $bookId = $this->getStateData($botUser, $botMotherId, 'book_id');
        $pageNumber = $this->getStateData($botUser, $botMotherId, 'page_number');

        if (!$bookId || !$pageNumber) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_missing_data'));
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            return;
        }

        $book = \App\Models\Book::find($bookId);
        if (!$book) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_book_not_found'));
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            return;
        }

        // Create book page
        $bookPage = $this->bookPixelService->createBookPage($bookId, $pageNumber);

        // Create scan
        $scan = $this->bookPixelService->createScan($bookPage->id, $botUser->id, $botId, $pageNumber, $fileId, $fileUniqueId);

        // Send to moderation group
        $this->bookPixelService->sendToModerationGroup($scan, $botId, $bot->BotType());

        BotHelper::sendMessage($bot, trans('bot.book_pixel_scan_sent_for_approval'));
        
        // Clear state
        $this->clearState($botUser, $botMotherId);
    }

    private function handleVoice(Telegram $bot, array $message, string $type, int $botId, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if (!$botUser) {
            return;
        }

        $scanId = $this->getStateData($botUser, $botMotherId, 'scan_id');
        if (!$scanId) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_no_scan_for_voice'));
            return;
        }

        $voice = $message['voice'];
        $fileId = $voice['file_id'];
        $fileUniqueId = $voice['file_unique_id'] ?? null;

        $voiceRecord = $this->bookPixelService->createVoice($scanId, $botUser->id, $botId, $fileId, $fileUniqueId);

        // Award voice points
        $this->gamificationService->awardVoicePoints($botId, $botUser->id);

        // Update scan's voice in publishing queue if exists
        $queueItems = \App\Models\BookPublishingQueue::where('book_page_scan_id', $scanId)
            ->where('status', 'pending')
            ->get();
        
        foreach ($queueItems as $queueItem) {
            $queueItem->book_page_voice_id = $voiceRecord->id;
            $queueItem->save();
        }

        BotHelper::sendMessage($bot, trans('bot.book_pixel_voice_sent'));
        $this->clearState($botUser, $botMotherId);
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botId, int $botMotherId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        // Handle "send_voice" button
        if (str_starts_with($callbackData, 'send_voice_')) {
            $scanId = (int) str_replace('send_voice_', '', $callbackData);
            $chatId = $bot->ChatID();
            $botUser = BotUsers::where('chat_id', $chatId)
                ->where('origin', $type)
                ->where('bot_id', $botId)
                ->first();

            if ($botUser) {
                $this->setStateData($botUser, $botMotherId, 'scan_id', $scanId);
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_voice'));
            }
        }
    }

    private function handleScore(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $score = $this->gamificationService->getUserScore($botId, $botUser->id);
        
        if (!$score || $score->total_points == 0) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_no_score_yet'));
            return;
        }

        $message = trans('bot.book_pixel_score_message', [
            'points' => $score->total_points,
            'scans' => $score->scans_count,
            'voices' => $score->voices_count
        ]);

        BotHelper::sendMessage($bot, $message);
    }

    private function setState(BotUsers $botUser, int $botMotherId, string $state, array $data = []): void
    {
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->delete();

        BotUserState::create([
            'bot_user_id' => $botUser->id,
            'bot_mother_id' => $botMotherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addHours(2),
        ]);
    }

    private function setStateData(BotUsers $botUser, int $botMotherId, string $key, $value): void
    {
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        if ($state) {
            $data = $state->data ?? [];
            $data[$key] = $value;
            $state->data = $data;
            $state->save();
        }
    }

    private function getStateData(BotUsers $botUser, int $botMotherId, string $key, $default = null)
    {
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        if ($state && isset($state->data[$key])) {
            return $state->data[$key];
        }

        return $default;
    }

    private function clearState(BotUsers $botUser, int $botMotherId): void
    {
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->delete();
    }
}
