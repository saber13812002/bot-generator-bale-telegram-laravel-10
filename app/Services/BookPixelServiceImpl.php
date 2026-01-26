<?php

namespace App\Services;

use App\Interfaces\Repositories\BookRepository;
use App\Interfaces\Repositories\BookPageScanRepository;
use App\Interfaces\Services\BookPixelService;
use App\Models\Book;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\BookPageVoice;
use App\Models\BookModerationGroup;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookPixelServiceImpl implements BookPixelService
{
    public function __construct(
        private BookRepository $bookRepository,
        private BookPageScanRepository $scanRepository
    ) {}

    public function findOrCreateBook(string $name, ?string $isbn, ?string $shabak, int $botId, ?int $userId = null): Book
    {
        Log::info('BookPixelService - Finding or creating book', [
            'name' => $name,
            'isbn' => $isbn,
            'shabak' => $shabak,
            'bot_id' => $botId
        ]);

        // Try to find by ISBN
        if ($isbn) {
            $book = $this->bookRepository->findByIsbnAndBot($isbn, $botId);
            if ($book) {
                return $book;
            }
        }

        // Try to find by Shabak
        if ($shabak) {
            $book = $this->bookRepository->findByShabakAndBot($shabak, $botId);
            if ($book) {
                return $book;
            }
        }

        // Try to find by name
        $book = $this->bookRepository->findByNameAndBot($name, $botId);
        if ($book) {
            return $book;
        }

        // Create new book
        return $this->bookRepository->create([
            'name' => $name,
            'isbn' => $isbn,
            'shabak' => $shabak,
            'bot_id' => $botId,
            'created_by_user_id' => $userId,
        ]);
    }

    public function createBookPage(int $bookId, int $pageNumber): BookPage
    {
        Log::info('BookPixelService - Creating book page', [
            'book_id' => $bookId,
            'page_number' => $pageNumber
        ]);

        return BookPage::firstOrCreate(
            ['book_id' => $bookId, 'page_number' => $pageNumber],
            ['book_id' => $bookId, 'page_number' => $pageNumber]
        );
    }

    public function createScan(int $bookPageId, int $userId, int $botId, int $pageNumber, string $fileId, ?string $fileUniqueId = null): BookPageScan
    {
        Log::info('BookPixelService - Creating scan', [
            'book_page_id' => $bookPageId,
            'user_id' => $userId,
            'bot_id' => $botId,
            'page_number' => $pageNumber
        ]);

        return $this->scanRepository->create([
            'book_page_id' => $bookPageId,
            'user_id' => $userId,
            'bot_id' => $botId,
            'page_number' => $pageNumber,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'status' => 'pending_approval',
            'points_awarded' => 0,
        ]);
    }

    public function sendToModerationGroup(BookPageScan $scan, int $botId, string $type): bool
    {
        Log::info('BookPixelService - Sending to moderation group', [
            'scan_id' => $scan->id,
            'bot_id' => $botId,
            'type' => $type
        ]);

        try {
            $moderationGroup = BookModerationGroup::where('bot_id', $botId)
                ->where('origin', $type)
                ->where('is_active', true)
                ->first();

            if (!$moderationGroup) {
                Log::warning('BookPixelService - No active moderation group found', [
                    'bot_id' => $botId,
                    'type' => $type
                ]);
                return false;
            }

            // Get bot token
            $bot = \App\Models\Bot::find($botId);
            if (!$bot) {
                Log::error('BookPixelService - Bot not found', ['bot_id' => $botId]);
                return false;
            }

            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::error('BookPixelService - Bot token not found', ['bot_id' => $botId]);
                return false;
            }

            $telegramBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            // Build message
            $book = $scan->bookPage->book;
            $message = "📚 درخواست تایید اسکن صفحه\n\n";
            $message .= "📖 کتاب: {$book->name}\n";
            $message .= "📄 صفحه: {$scan->page_number}\n";
            $message .= "👤 کاربر: {$scan->user_id}\n";
            $message .= "🆔 شناسه: {$scan->id}";

            // Send photo with inline keyboard
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید', 'callback_data' => "approve_scan_{$scan->id}"],
                        ['text' => '❌ رد', 'callback_data' => "reject_scan_{$scan->id}"]
                    ]
                ]
            ];

            $result = $telegramBot->sendPhoto([
                'chat_id' => $moderationGroup->group_chat_id,
                'photo' => $scan->file_id,
                'caption' => $message,
                'reply_markup' => json_encode($keyboard)
            ]);

            if ($result && isset($result['ok']) && $result['ok']) {
                $scan->approval_message_id = $result['result']['message_id'];
                $scan->save();

                Log::info('BookPixelService - Sent to moderation group successfully', [
                    'scan_id' => $scan->id,
                    'message_id' => $scan->approval_message_id
                ]);
                return true;
            }

            Log::error('BookPixelService - Failed to send to moderation group', [
                'scan_id' => $scan->id,
                'result' => $result
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('BookPixelService - Error sending to moderation group', [
                'error' => $e->getMessage(),
                'scan_id' => $scan->id
            ]);
            return false;
        }
    }

    public function createVoice(int $bookPageScanId, int $userId, int $botId, string $fileId, ?string $fileUniqueId = null): BookPageVoice
    {
        Log::info('BookPixelService - Creating voice', [
            'book_page_scan_id' => $bookPageScanId,
            'user_id' => $userId,
            'bot_id' => $botId
        ]);

        return BookPageVoice::create([
            'book_page_scan_id' => $bookPageScanId,
            'user_id' => $userId,
            'bot_id' => $botId,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'points_awarded' => 0,
        ]);
    }
}
