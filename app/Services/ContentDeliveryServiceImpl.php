<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\FileUploadHelper;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Models\Bot;
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

        // ساختن کپشن با فوتر
        $caption = $this->buildCaptionWithFooter($title, $botId, $origin);
        BotHelper::sendMessageByChatId($bot, $chatId, '🎧 ' . $title);

        $sent = $this->sendAudio($bot, $asset, $item, $botId, $origin, $chatId, $caption);
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

    /**
     * ساختن کپشن با فوتر تنظیم شده در bot.caption_footer
     */
    public function buildCaptionWithFooter(string $text, int $botId, string $origin): string
    {
        $botModel = Bot::find($botId);
        $footer = $botModel?->caption_footer;

        if (!$footer) {
            return $text;
        }

        // جایگزینی متغیرها
        $footer = str_replace(
            ['{bot_name}', '{bot_link_bale}', '{bot_link_telegram}'],
            [
                $botModel->bale_bot_name ?? $botModel->telegram_bot_name ?? '',
                $botModel->bale_bot_name ? 'https://ble.ir/' . $botModel->bale_bot_name : '',
                $botModel->telegram_bot_name ? 'https://t.me/' . $botModel->telegram_bot_name : '',
            ],
            $footer
        );

        return $text . "\n\n" . $footer;
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
