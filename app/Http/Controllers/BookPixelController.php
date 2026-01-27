<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\BookGamificationService;
use App\Interfaces\Services\BookPixelService;
use App\Interfaces\Services\BookPublishingService;
use App\Interfaces\Services\BookDraftService;
use App\Interfaces\Services\BookStatisticsService;
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
        private BookPublishingService $publishingService,
        private BookDraftService $draftService,
        private BookStatisticsService $statisticsService
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

            // Auto-save to draft for any message
            if (isset($update['message'])) {
                $this->autoSaveToDraft($bot, $update['message'], $type, $botId);
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

        // Handle /help command
        if ($text == '/help' || $text == 'help' || $text == 'راهنما') {
            $this->handleHelp($bot);
            return;
        }

        // Handle /score command
        if ($text == '/score') {
            $this->handleScore($bot, $botUser, $botId);
            return;
        }

        // Handle /cover command
        if ($text == '/cover' || $text == 'cover' || $text == 'جلد') {
            $this->handleCoverCommand($bot, $botUser, $botMotherId);
            return;
        }

        // Handle /search command
        if (str_starts_with($text, '/search ') || str_starts_with($text, 'search ')) {
            $query = trim(str_replace(['/search', 'search'], '', $text));
            if (!empty($query)) {
                $this->handleSearch($bot, $botUser, $botId, $query);
                return;
            }
        }

        // Handle /drafts command
        if ($text == '/drafts' || $text == 'drafts' || $text == 'پیش‌نویس') {
            $this->handleDrafts($bot, $botUser, $botId);
            return;
        }

        // Handle /cancel command
        if ($text == '/cancel' || $text == 'cancel' || $text == 'لغو') {
            $this->handleCancel($bot, $botUser, $botMotherId);
            return;
        }

        // Handle /skip command
        if ($text == '/skip' || $text == 'skip' || $text == 'رد کردن') {
            $this->handleSkip($bot, $botUser, $botMotherId, $botId);
            return;
        }

        // Handle /stats command (only in groups)
        if ($text == '/stats' || $text == 'stats' || $text == 'آمار') {
            $this->handleStats($bot, $botId);
            return;
        }

        // Handle state-based messages
        switch ($currentState) {
            case 'waiting_isbn':
                $this->handleIsbnFirst($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_book_name':
                $this->handleBookName($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_shabak':
                // شابک همان ISBN است، پس این state دیگر استفاده نمی‌شود
                // اما برای سازگاری با داده‌های قدیمی، آن را به handleIsbn هدایت می‌کنیم
                $this->handleIsbnFirst($bot, $botUser, $botMotherId, $text, $botId);
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
                $this->setState($botUser, $botMotherId, 'waiting_isbn');
                $message = trans('bot.book_pixel_ask_isbn_first');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => trans('bot.skip'), 'callback_data' => 'skip_isbn']
                        ]
                    ]
                ];
                BotHelper::sendKeyboardMessage($bot, $message, json_encode($keyboard));
        }
    }

    private function handleStart(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        BotHelper::sendMessage($bot, trans('bot.book_pixel_welcome'));
        $this->setState($botUser, $botMotherId, 'waiting_isbn');
        
        $message = trans('bot.book_pixel_ask_isbn_first');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => trans('bot.skip'), 'callback_data' => 'skip_isbn']
                ]
            ]
        ];
        BotHelper::sendKeyboardMessage($bot, $message, json_encode($keyboard));
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

    private function handleIsbnFirst(Telegram $bot, BotUsers $botUser, int $botMotherId, string $isbn, int $botId): void
    {
        $isbn = trim($isbn);
        $this->setStateData($botUser, $botMotherId, 'isbn', $isbn);
        $this->setStateData($botUser, $botMotherId, 'shabak', $isbn); // شابک همان ISBN است
        
        // جستجوی کتاب با ISBN
        $book = $this->bookPixelService->findOrCreateBook("", $isbn, $isbn, $botId, $botUser->id);
        
        if ($book->name && $book->name !== "" && !str_starts_with($book->name, "کتاب با ISBN:")) {
            // کتاب پیدا شد
            $this->setStateData($botUser, $botMotherId, 'book_name', $book->name);
            $this->setStateData($botUser, $botMotherId, 'book_id', $book->id);
            
            BotHelper::sendMessage($bot, trans('bot.book_pixel_book_found', ['name' => $book->name]));
            
            // اگر عکس جلد دارد، مستقیماً به مرحله صفحه می‌رویم
            if ($book->cover_image_file_id) {
                $this->setState($botUser, $botMotherId, 'waiting_page_number');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
            } else {
                // عکس جلد ندارد، اما اختیاری است
                $message = trans('bot.book_pixel_book_ready_no_cover');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => trans('bot.send_cover'), 'callback_data' => 'send_cover'],
                            ['text' => trans('bot.skip'), 'callback_data' => 'skip_cover']
                        ]
                    ]
                ];
                BotHelper::sendKeyboardMessage($bot, $message, json_encode($keyboard));
            }
        } else {
            // کتاب پیدا نشد، نام کتاب را می‌گیریم
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
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

        // Save to draft automatically
        $isbn = $this->getStateData($botUser, $botMotherId, 'isbn');
        $bookName = $this->getStateData($botUser, $botMotherId, 'book_name');
        $this->draftService->saveDraft($botId, $chatId, 'cover', $fileId, $fileUniqueId, $isbn, $bookName);
        
        // Send confirmation
        BotHelper::sendMessage($bot, trans('bot.book_pixel_photo_received'));
        
        if ($currentState == 'waiting_cover') {
            $this->handleCoverPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
        } elseif ($currentState == 'waiting_scan') {
            $this->handleScanPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
        } else {
            // اگر state درست نیست، اما نام کتاب یا ISBN وجود دارد، می‌توانیم عکس را بپذیریم
            if ($bookName || $isbn) {
                // اگر اطلاعات کتاب وجود دارد، عکس را به عنوان جلد در نظر می‌گیریم
                $this->handleCoverPhoto($bot, $botUser, $botMotherId, $fileId, $fileUniqueId, $botId);
            } else {
                // اگر هیچ اطلاعاتی وجود ندارد، از کاربر می‌خواهیم ابتدا /start را بزند
                BotHelper::sendMessage($bot, trans('bot.book_pixel_unexpected_photo'));
                $this->setState($botUser, $botMotherId, 'waiting_isbn');
                $message = trans('bot.book_pixel_ask_isbn_first');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => trans('bot.skip'), 'callback_data' => 'skip_isbn']
                        ]
                    ]
                ];
                BotHelper::sendKeyboardMessage($bot, $message, json_encode($keyboard));
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

        // Save to draft first
        $this->draftService->saveDraft($botId, $bot->ChatID(), 'scan', $fileId, $fileUniqueId, null, null, $pageNumber);
        
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

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        // Handle "send_voice" button
        if (str_starts_with($callbackData, 'send_voice_')) {
            $scanId = (int) str_replace('send_voice_', '', $callbackData);
            if ($botUser) {
                $this->setStateData($botUser, $botMotherId, 'scan_id', $scanId);
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_voice'));
            }
        }
        // Handle skip ISBN
        elseif ($callbackData == 'skip_isbn') {
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
        }
        // Handle skip cover
        elseif ($callbackData == 'skip_cover') {
            $bookId = $this->getStateData($botUser, $botMotherId, 'book_id');
            if ($bookId) {
                $this->setState($botUser, $botMotherId, 'waiting_page_number');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
            }
        }
        // Handle send cover
        elseif ($callbackData == 'send_cover') {
            $this->setState($botUser, $botMotherId, 'waiting_cover');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_cover'));
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

    private function autoSaveToDraft(Telegram $bot, array $message, string $type, int $botId): void
    {
        $chatId = $bot->ChatID();
        
        // Save photo to draft
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $photo = end($photos);
            $fileId = $photo['file_id'];
            $fileUniqueId = $photo['file_unique_id'] ?? null;
            
            // Determine type based on context
            $draftType = 'cover'; // Default to cover, will be updated if scan
            
            $this->draftService->saveDraft($botId, $chatId, $draftType, $fileId, $fileUniqueId);
        }
        
        // Save voice to draft
        if (isset($message['voice'])) {
            $voice = $message['voice'];
            $fileId = $voice['file_id'];
            $fileUniqueId = $voice['file_unique_id'] ?? null;
            
            $this->draftService->saveDraft($botId, $chatId, 'voice', $fileId, $fileUniqueId);
        }
    }

    private function handleHelp(Telegram $bot): void
    {
        $message = trans('bot.book_pixel_help');
        BotHelper::sendMessage($bot, $message);
    }

    private function handleCoverCommand(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $this->setState($botUser, $botMotherId, 'waiting_cover');
        BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_cover'));
    }

    private function handleSearch(Telegram $bot, BotUsers $botUser, int $botId, string $query): void
    {
        $books = $this->draftService->searchBooks($botId, $query);
        
        if ($books->isEmpty()) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_no_books_found'));
            return;
        }
        
        $message = trans('bot.book_pixel_search_results') . "\n\n";
        foreach ($books->take(10) as $book) {
            $message .= "📖 {$book->name}";
            if ($book->isbn) {
                $message .= " (ISBN: {$book->isbn})";
            }
            $message .= "\n";
        }
        
        BotHelper::sendMessage($bot, $message);
    }

    private function handleDrafts(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $drafts = $this->draftService->getDrafts($botId, $botUser->chat_id, null, 'draft');
        
        if ($drafts->isEmpty()) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_no_drafts'));
            return;
        }
        
        $message = trans('bot.book_pixel_drafts_list') . "\n\n";
        foreach ($drafts->take(10) as $draft) {
            $typeText = match($draft->type) {
                'cover' => trans('bot.cover'),
                'scan' => trans('bot.scan'),
                'voice' => trans('bot.voice'),
                default => $draft->type
            };
            $message .= "📎 {$typeText}";
            if ($draft->book_name) {
                $message .= " - {$draft->book_name}";
            }
            if ($draft->page_number) {
                $message .= " (صفحه {$draft->page_number})";
            }
            $message .= "\n";
        }
        
        BotHelper::sendMessage($bot, $message);
    }

    private function handleCancel(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $this->clearState($botUser, $botMotherId);
        BotHelper::sendMessage($bot, trans('bot.book_pixel_cancelled'));
    }

    private function handleSkip(Telegram $bot, BotUsers $botUser, int $botMotherId, int $botId): void
    {
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        $currentState = $state ? $state->state : null;

        if ($currentState == 'waiting_isbn') {
            $this->setState($botUser, $botMotherId, 'waiting_book_name');
            BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_book_name'));
        } elseif ($currentState == 'waiting_cover') {
            $bookId = $this->getStateData($botUser, $botMotherId, 'book_id');
            if ($bookId) {
                $this->setState($botUser, $botMotherId, 'waiting_page_number');
                BotHelper::sendMessage($bot, trans('bot.book_pixel_ask_page_number'));
            } else {
                BotHelper::sendMessage($bot, trans('bot.book_pixel_cannot_skip'));
            }
        } else {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_nothing_to_skip'));
        }
    }

    private function handleStats(Telegram $bot, int $botId): void
    {
        // Check if this is a group
        $chatId = $bot->ChatID();
        $chatInfo = $bot->getChat(['chat_id' => $chatId]);
        
        if (!isset($chatInfo['type']) || ($chatInfo['type'] !== 'group' && $chatInfo['type'] !== 'supergroup')) {
            BotHelper::sendMessage($bot, trans('bot.book_pixel_stats_only_in_group'));
            return;
        }
        
        $stats = $this->statisticsService->getFullStats($botId);
        
        $message = "📊 " . trans('bot.book_pixel_statistics') . "\n\n";
        $message .= "📋 " . trans('bot.queue') . ":\n";
        $message .= "  • " . trans('bot.pending_approval') . ": {$stats['queue']['pending_approval']}\n";
        $message .= "  • " . trans('bot.pending_publishing') . ": {$stats['queue']['pending_publishing']}\n";
        $message .= "  • " . trans('bot.in_publishing_queue') . ": {$stats['queue']['in_publishing_queue']}\n\n";
        
        $message .= "✅ " . trans('bot.completed') . ":\n";
        $message .= "  • " . trans('bot.today') . ": {$stats['completed']['today']}\n";
        $message .= "  • " . trans('bot.last_week') . ": {$stats['completed']['last_week']}\n";
        $message .= "  • " . trans('bot.last_month') . ": {$stats['completed']['last_month']}\n";
        $message .= "  • " . trans('bot.last_year') . ": {$stats['completed']['last_year']}\n\n";
        
        $message .= "📢 " . trans('bot.publishing_channels') . ": {$stats['channels']}\n\n";
        
        $message .= "🆕 " . trans('bot.new') . ":\n";
        $message .= "  • " . trans('bot.new_users_today') . ": {$stats['new']['users_today']}\n";
        $message .= "  • " . trans('bot.new_books_today') . ": {$stats['new']['books_today']}\n\n";
        
        $message .= "📝 " . trans('bot.drafts') . ": {$stats['drafts']['incomplete']}\n";
        $message .= "🎯 " . trans('bot.missions') . ": {$stats['missions']['pending']} " . trans('bot.pending') . ", {$stats['missions']['assigned']} " . trans('bot.assigned') . "\n";
        
        BotHelper::sendMessage($bot, $message);
    }
}
