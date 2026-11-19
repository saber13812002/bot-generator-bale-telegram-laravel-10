<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Interfaces\Services\ContentSubmissionService;
use App\Models\Bot;
use App\Models\ContentSubmissionBotConfig;
use App\Models\ContentSubmissionItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentSubmissionController extends Controller
{
    public function __construct(
        private ContentSubmissionService $contentSubmissionService
    ) {}

    public function webhook(Request $request)
    {
        Log::info('[ContentSubmission] Webhook received', [
            'has_origin' => $request->has('origin'),
            'has_bot_id' => $request->has('bot_id'),
            'action' => 'webhook_entry',
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $botId = (int) $request->input('bot_id');
            $token = $request->input('token');
            if (!$token && $botId) {
                $botModel = Bot::find($botId);
                $token = $botModel ? ($type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token) : null;
            }
            if (!$token) {
                Log::error('[ContentSubmission] No token', ['bot_id' => $botId, 'action' => 'webhook_entry']);
                return response()->json(['ok' => true], 200);
            }

            $bot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
            $update = $request->json()->all() ?? $request->all();
            $chatId = $bot->ChatID();
            $message = $update['message'] ?? null;

            if (!$message) {
                Log::info('[ContentSubmission] No message in update', ['chat_id' => $chatId, 'action' => 'webhook_entry']);
                return response()->json(['ok' => true], 200);
            }

            $config = ContentSubmissionBotConfig::where('bot_id', $botId)->where('origin', $type)->first();
            if (!$config) {
                Log::warning('[ContentSubmission] No config for bot', ['bot_id' => $botId, 'chat_id' => $chatId, 'action' => 'webhook_entry']);
                return response()->json(['ok' => true], 200);
            }

            $isPrivate = ($message['chat']['type'] ?? '') === 'private' || (isset($message['chat']['id']) && $message['chat']['id'] > 0);
            $isGroup = !$isPrivate && isset($message['chat']['id']) && $message['chat']['id'] === (int) $config->group_chat_id;

            if ($isPrivate) {
                $this->handlePrivateMessage($bot, $message, $botId, $type, $config);
                return response()->json(['ok' => true], 200);
            }

            if ($isGroup) {
                $this->handleGroupMessage($bot, $message, $botId, $type, $config);
                return response()->json(['ok' => true], 200);
            }

            Log::info('[ContentSubmission] Chat not private and not approval group, ignore', [
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'action' => 'webhook_entry',
            ]);
            return response()->json(['ok' => true], 200);
        } catch (Exception $e) {
            Log::error('[ContentSubmission] Webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => 'webhook_entry',
            ]);
            return response()->json(['ok' => true], 200);
        }
    }

    private function handlePrivateMessage(Telegram $bot, array $message, int $botId, string $type, ContentSubmissionBotConfig $config): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        Log::info('[ContentSubmission] handlePrivateMessage', [
            'chat_id' => $chatId,
            'bot_id' => $botId,
            'action' => 'handle_private',
        ]);

        $contentType = null;
        $contentText = null;
        $fileId = null;
        $fileUniqueId = null;

        if (!empty($message['text'])) {
            $contentType = 'text';
            $contentText = $message['text'];
        } elseif (!empty($message['photo'])) {
            $contentType = 'image';
            $photos = $message['photo'];
            $fileId = $photos[count($photos) - 1]['file_id'] ?? null;
            $fileUniqueId = $photos[count($photos) - 1]['file_unique_id'] ?? null;
            $contentText = $message['caption'] ?? null;
        } elseif (!empty($message['video'])) {
            $contentType = 'video';
            $fileId = $message['video']['file_id'] ?? null;
            $fileUniqueId = $message['video']['file_unique_id'] ?? null;
            $contentText = $message['caption'] ?? null;
        }

        if (!$contentType) {
            Log::warning('[ContentSubmission] Unsupported message type in private', [
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'action' => 'handle_private',
            ]);
            BotHelper::sendMessage($bot, 'لطفاً یک متن، عکس یا ویدیو ارسال کنید.');
            return;
        }

        $item = $this->contentSubmissionService->submitContent($botId, $chatId, $contentType, $contentText, $fileId, $fileUniqueId);

        if ($config->hasApprovalGroup()) {
            $sent = $this->contentSubmissionService->sendToApprovalGroup($item, $type);
            if ($sent) {
                BotHelper::sendMessage($bot, '✅ محتوای شما برای تایید به گروه ارسال شد. پس از تایید در کانال منتشر می‌شود.');
            } else {
                BotHelper::sendMessage($bot, '❌ خطا در ارسال به گروه تایید. لطفاً بعداً تلاش کنید.');
            }
        } else {
            $published = $this->contentSubmissionService->publishToChannel($item, $type);
            if ($published) {
                $this->contentSubmissionService->notifySubmitter($item, 'published', $type);
                BotHelper::sendMessage($bot, '✅ محتوای شما در کانال منتشر شد.');
            } else {
                BotHelper::sendMessage($bot, '❌ خطا در انتشار. لطفاً بعداً تلاش کنید.');
            }
        }
    }

    private function handleGroupMessage(Telegram $bot, array $message, int $botId, string $type, ContentSubmissionBotConfig $config): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        $replyToMessage = $message['reply_to_message'] ?? null;
        $text = trim($message['text'] ?? '');

        Log::info('[ContentSubmission] handleGroupMessage', [
            'chat_id' => $chatId,
            'bot_id' => $botId,
            'has_reply' => (bool) $replyToMessage,
            'text' => $text,
            'action' => 'handle_group',
        ]);

        if (!$replyToMessage || $text !== '1') {
            return;
        }

        $replyToMessageId = $replyToMessage['message_id'] ?? null;
        $approverChatId = $message['from']['id'] ?? null;
        if (!$replyToMessageId || !$approverChatId) {
            return;
        }

        $item = $this->contentSubmissionService->processApprovalReply(
            $botId,
            $chatId,
            $replyToMessageId,
            $approverChatId,
            $type
        );

        if (!$item) {
            return;
        }

        Log::info('[ContentSubmission] Item fully approved, publishing', [
            'item_id' => $item->id,
            'bot_id' => $botId,
            'action' => 'handle_group_publish',
        ]);

        $published = $this->contentSubmissionService->publishToChannel($item, $type);
        if ($published) {
            try {
                $bot->deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $item->approval_message_id,
                ]);
            } catch (Exception $e) {
                Log::warning('[ContentSubmission] Could not delete approval message', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                    'action' => 'handle_group_delete',
                ]);
            }
            $this->contentSubmissionService->notifySubmitter($item, 'published', $type);
        }
    }
}
