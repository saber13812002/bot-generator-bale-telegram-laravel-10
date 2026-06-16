<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\FileUploadHelper;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Models\BotUsers;
use App\Models\ContentItem;
use App\Models\LibraryUserBook;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentDeliveryServiceImpl implements ContentDeliveryService
{
    public function __construct(
        private BookLibraryService $bookLibraryService,
        private BookLibraryPlanService $planService
    ) {}

    public function deliverNextInCategory(
        Telegram $bot,
        BotUsers $botUser,
        ContentItem $item,
        int $botId,
        string $origin
    ): bool {
        $chatId = $botUser->chat_id;
        $asset = $item->getAudioAsset();

        if (!$asset) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('book_library.no_audio'));
            return false;
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trans('book_library.preparing'));

        $title = $item->title ?: ('#' . $item->queue_order);
        BotHelper::sendMessageByChatId($bot, $chatId, '🎧 ' . $title);

        $sent = $this->sendAudio($bot, $asset, $item, $botId, $origin, $chatId, $title);
        if (!$sent) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('book_library.no_audio'));
            return false;
        }

        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $botId);
        $subscription->increment('books_used');

        LibraryUserBook::create([
            'bot_user_id' => $botUser->id,
            'book_id' => $item->id,
            'bot_id' => $botId,
            'delivered_via' => 'main',
            'status' => 'received',
            'revealed_title' => true,
            'is_random' => false,
        ]);

        $progress = $this->bookLibraryService->buildProgressBar($subscription->fresh());
        BotHelper::sendMessageByChatId($bot, $chatId, $progress);

        return true;
    }

    private function sendAudio(
        Telegram $bot,
        $asset,
        ContentItem $item,
        int $botId,
        string $origin,
        string $chatId,
        ?string $caption
    ): bool {
        $fileId = $asset->getFileIdForOrigin($origin);
        if ($fileId) {
            try {
                $bot->sendAudio(['chat_id' => $chatId, 'audio' => $fileId, 'caption' => $caption]);
                return true;
            } catch (\Exception $e) {
                Log::error('❌ [ContentDelivery] sendAudio file_id failed', ['error' => $e->getMessage()]);
            }
        }

        if ($asset->content_url) {
            $uniqueKey = "content_{$item->id}_audio";
            $result = FileUploadHelper::getOrUploadFile(
                $bot,
                $uniqueKey,
                $asset->content_url,
                'audio',
                ['content_item_id' => $item->id],
                $botId,
                $origin
            );
            if ($result && isset($result['file_id'])) {
                $asset->setFileIdForOrigin($origin, $result['file_id']);
                return true;
            }
            BotHelper::sendAudio($chatId, $asset->content_url, $item->title ?? '', $bot, $caption ?? '');
            return true;
        }

        return false;
    }
}
