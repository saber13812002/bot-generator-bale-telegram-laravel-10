<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\FileUploadHelper;
use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryBook;
use App\Models\LibraryBookMedia;
use App\Models\LibraryUserBook;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookLibraryDeliveryServiceImpl implements BookLibraryDeliveryService
{
    public function __construct(
        private BookLibraryService $bookLibraryService
    ) {}

    public function deliverBook(
        Telegram $mainBot,
        BotUsers $botUser,
        LibraryBook $book,
        int $mainBotId,
        string $origin,
        bool $isRandom,
        bool $revealTitleFirst
    ): ?LibraryUserBook {
        $config = $this->bookLibraryService->getBotConfig($mainBotId);
        $useReader = $config && $config->reader_bot_id;

        if ($useReader && !$this->bookLibraryService->hasReaderStarted($botUser, $mainBotId)) {
            $username = $this->bookLibraryService->getReaderBotUsername($mainBotId);
            $message = trans('book_library.reader_required');
            if ($username) {
                $message .= "\n\n@" . $username;
            }
            BotHelper::sendMessage($mainBot, $message);
            return null;
        }

        $deliveryBot = $useReader ? $this->createReaderBot($config->reader_bot_id, $origin) : $mainBot;
        $deliveredVia = $useReader ? 'reader' : 'main';
        $chatId = $botUser->chat_id;

        if (!$deliveryBot) {
            BotHelper::sendMessage($mainBot, trans('book_library.delivery_error'));
            return null;
        }

        BotHelper::sendMessageByChatId($deliveryBot, $chatId, trans('book_library.preparing'));

        if ($revealTitleFirst) {
            BotHelper::sendMessageByChatId($deliveryBot, $chatId, '📘 ' . $book->title);
        }

        $audioSent = $this->sendMedia($deliveryBot, $book, 'audio', $mainBotId, $origin, $chatId, $revealTitleFirst ? $book->title : null);

        if (!$audioSent) {
            BotHelper::sendMessageByChatId($mainBot, $chatId, trans('book_library.no_audio'));
            return null;
        }

        if (!$revealTitleFirst) {
            BotHelper::sendMessageByChatId($deliveryBot, $chatId, '📘 ' . $book->title);
        }

        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $mainBotId);
        $subscription->increment('books_used');

        $userBook = LibraryUserBook::create([
            'bot_user_id' => $botUser->id,
            'book_id' => $book->id,
            'bot_id' => $mainBotId,
            'delivered_via' => $deliveredVia,
            'status' => 'received',
            'revealed_title' => $revealTitleFirst,
            'is_random' => $isRandom,
        ]);

        $progress = $this->bookLibraryService->buildProgressBar($subscription->fresh());
        BotHelper::sendMessageByChatId($deliveryBot, $chatId, $progress);

        $this->sendQuickActionKeyboard($deliveryBot, $chatId, $userBook, $book);

        if ($useReader) {
            BotHelper::sendMessageByChatId($mainBot, $chatId, trans('book_library.sent_to_reader'));
        }

        return $userBook;
    }

    public function sendQuickAction(
        Telegram $mainBot,
        BotUsers $botUser,
        LibraryUserBook $userBook,
        int $mainBotId,
        string $origin,
        string $action
    ): bool {
        $book = $userBook->book()->with('media')->first();
        if (!$book) {
            return false;
        }

        $config = $this->bookLibraryService->getBotConfig($mainBotId);
        $useReader = $config && $config->reader_bot_id;
        $deliveryBot = $useReader ? $this->createReaderBot($config->reader_bot_id, $origin) : $mainBot;
        $chatId = $botUser->chat_id;

        if (!$deliveryBot) {
            return false;
        }

        return match ($action) {
            'pdf' => $this->sendMedia($deliveryBot, $book, 'pdf', $mainBotId, $origin, $chatId, $book->title),
            'infographic' => $this->sendMedia($deliveryBot, $book, 'infographic', $mainBotId, $origin, $chatId, $book->title),
            'replay' => $this->sendMedia($deliveryBot, $book, 'audio', $mainBotId, $origin, $chatId, $book->title),
            default => false,
        };
    }

    private function sendMedia(
        Telegram $bot,
        LibraryBook $book,
        string $type,
        int $botId,
        string $origin,
        string $chatId,
        ?string $caption = null
    ): bool {
        $media = $book->getMediaByType($type);
        if (!$media || (!$media->content_url && !$media->getCachedFileId($origin))) {
            if ($type !== 'audio') {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('book_library.media_not_available'));
            }
            return false;
        }

        $fileId = $media->getCachedFileId($origin);
        if ($fileId) {
            return $this->sendByFileId($bot, $chatId, $type, $fileId, $caption);
        }

        if (!$media->content_url) {
            return false;
        }

        $fileType = $type === 'audio' ? 'audio' : ($type === 'pdf' ? 'document' : 'photo');
        $uniqueKey = "library_{$book->id}_{$type}";

        $result = FileUploadHelper::getOrUploadFile(
            $bot,
            $uniqueKey,
            $media->content_url,
            $fileType,
            ['book_id' => $book->id, 'type' => $type],
            $botId,
            $origin
        );

        if ($result && isset($result['file_id'])) {
            $media->setCachedFileId($origin, $result['file_id']);
            return $this->sendByFileId($bot, $chatId, $type, $result['file_id'], $caption);
        }

        if ($type === 'audio') {
            BotHelper::sendAudio($chatId, $media->content_url, $book->title, $bot, $caption ?? '');
            return true;
        }

        if ($type === 'pdf') {
            $bot->sendDocument(['chat_id' => $chatId, 'document' => $media->content_url, 'caption' => $caption]);
            return true;
        }

        BotHelper::sendPhoto($chatId, $media->content_url, $book->title, $bot, $caption ?? '');
        return true;
    }

    private function sendByFileId(Telegram $bot, string $chatId, string $type, string $fileId, ?string $caption): bool
    {
        try {
            if ($type === 'audio') {
                $bot->sendAudio(['chat_id' => $chatId, 'audio' => $fileId, 'caption' => $caption]);
            } elseif ($type === 'pdf') {
                $bot->sendDocument(['chat_id' => $chatId, 'document' => $fileId, 'caption' => $caption]);
            } else {
                $bot->sendPhoto(['chat_id' => $chatId, 'photo' => $fileId, 'caption' => $caption]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('❌ [BookLibrary] sendByFileId failed', ['error' => $e->getMessage(), 'type' => $type]);
            return false;
        }
    }

    private function sendQuickActionKeyboard(Telegram $bot, string $chatId, LibraryUserBook $userBook, LibraryBook $book): void
    {
        $keyboard = [];
        $id = $userBook->id;

        if ($book->getMediaByType('pdf')) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.qa_pdf'), callback_data: "bl:qa:pdf:{$id}")];
        }
        if ($book->getMediaByType('infographic')) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.qa_infographic'), callback_data: "bl:qa:info:{$id}")];
        }
        $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.qa_replay'), callback_data: "bl:qa:replay:{$id}")];

        if (!empty($keyboard)) {
            $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
            BotHelper::sendKeyboardMessageToChatId($bot, trans('book_library.quick_actions'), $inlineKeyboard, $chatId);
        }
    }

    private function createReaderBot(int $readerBotId, string $origin): ?Telegram
    {
        $readerBot = Bot::find($readerBotId);
        if (!$readerBot) {
            return null;
        }

        $token = $origin === 'bale' ? $readerBot->bale_bot_token : $readerBot->telegram_bot_token;
        if (!$token) {
            return null;
        }

        return $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }
}
