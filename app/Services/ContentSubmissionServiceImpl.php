<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Interfaces\Services\ContentSubmissionService;
use App\Models\Bot;
use App\Models\ContentSubmissionBotConfig;
use App\Models\ContentSubmissionItem;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentSubmissionServiceImpl implements ContentSubmissionService
{
    public function submitContent(
        int $botId,
        int $submitterChatId,
        string $contentType,
        ?string $contentText = null,
        ?string $fileId = null,
        ?string $fileUniqueId = null
    ): ContentSubmissionItem {
        Log::info('[ContentSubmission] submitContent start', [
            'bot_id' => $botId,
            'submitter_chat_id' => $submitterChatId,
            'content_type' => $contentType,
            'action' => 'submit_content',
        ]);

        $item = ContentSubmissionItem::create([
            'bot_id' => $botId,
            'submitter_chat_id' => $submitterChatId,
            'content_type' => $contentType,
            'content_text' => $contentText,
            'file_id' => $fileId,
            'file_unique_id' => $fileUniqueId,
            'status' => 'pending_approval',
        ]);

        Log::info('[ContentSubmission] submitContent created item', [
            'item_id' => $item->id,
            'bot_id' => $botId,
            'action' => 'submit_content',
        ]);

        return $item;
    }

    public function sendToApprovalGroup(ContentSubmissionItem $item, string $type): bool
    {
        Log::info('[ContentSubmission] sendToApprovalGroup start', [
            'item_id' => $item->id,
            'bot_id' => $item->bot_id,
            'action' => 'send_to_approval_group',
        ]);

        try {
            $config = ContentSubmissionBotConfig::where('bot_id', $item->bot_id)
                ->where('origin', $type)
                ->first();

            if (!$config || !$config->group_chat_id) {
                Log::warning('[ContentSubmission] No approval group config', [
                    'item_id' => $item->id,
                    'bot_id' => $item->bot_id,
                    'action' => 'send_to_approval_group',
                ]);
                return false;
            }

            $bot = Bot::find($item->bot_id);
            if (!$bot) {
                Log::error('[ContentSubmission] Bot not found', ['bot_id' => $item->bot_id, 'action' => 'send_to_approval_group']);
                return false;
            }

            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::error('[ContentSubmission] Bot token not found', ['bot_id' => $item->bot_id, 'action' => 'send_to_approval_group']);
                return false;
            }

            $telegramBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            $caption = "📝 درخواست تایید محتوا\n\n";
            $caption .= "👤 فرستنده: {$item->submitter_chat_id}\n";
            $caption .= "🆔 شناسه: {$item->id}\n";
            $caption .= "📌 نوع: {$item->content_type}\n";
            if ($item->content_text) {
                $caption .= "\n" . mb_substr($item->content_text, 0, 200) . (mb_strlen($item->content_text) > 200 ? '...' : '');
            }
            $caption .= "\n\nبرای تایید: ریپلای به این پیام با عدد ۱";

            $result = null;
            if ($item->content_type === 'image' && $item->file_id) {
                $result = $telegramBot->sendPhoto([
                    'chat_id' => $config->group_chat_id,
                    'photo' => $item->file_id,
                    'caption' => $caption,
                ]);
            } elseif ($item->content_type === 'video' && $item->file_id) {
                $result = $telegramBot->sendVideo([
                    'chat_id' => $config->group_chat_id,
                    'video' => $item->file_id,
                    'caption' => $caption,
                ]);
            } else {
                $text = $item->content_text ?? '(بدون متن)';
                $message = "📝 درخواست تایید محتوا\n\n👤 فرستنده: {$item->submitter_chat_id}\n🆔 شناسه: {$item->id}\n\n{$text}\n\nبرای تایید: ریپلای به این پیام با عدد ۱";
                $result = $telegramBot->sendMessage([
                    'chat_id' => $config->group_chat_id,
                    'text' => $message,
                ]);
            }

            if ($result && isset($result['ok']) && $result['ok']) {
                $item->approval_message_id = $result['result']['message_id'] ?? null;
                $item->save();
                Log::info('[ContentSubmission] sendToApprovalGroup success', [
                    'item_id' => $item->id,
                    'approval_message_id' => $item->approval_message_id,
                    'action' => 'send_to_approval_group',
                ]);
                return true;
            }

            Log::error('[ContentSubmission] sendToApprovalGroup send failed', [
                'item_id' => $item->id,
                'result' => $result,
                'action' => 'send_to_approval_group',
            ]);
            return false;
        } catch (Exception $e) {
            Log::error('[ContentSubmission] sendToApprovalGroup error', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => 'send_to_approval_group',
            ]);
            return false;
        }
    }

    public function processApprovalReply(
        int $botId,
        int $groupChatId,
        int $replyToMessageId,
        int $approverChatId,
        string $type
    ): ?ContentSubmissionItem {
        Log::info('[ContentSubmission] processApprovalReply start', [
            'bot_id' => $botId,
            'group_chat_id' => $groupChatId,
            'reply_to_message_id' => $replyToMessageId,
            'approver_chat_id' => $approverChatId,
            'action' => 'process_approval_reply',
        ]);

        $item = ContentSubmissionItem::where('bot_id', $botId)
            ->where('approval_message_id', $replyToMessageId)
            ->where('status', 'pending_approval')
            ->first();

        if (!$item) {
            Log::warning('[ContentSubmission] Item not found for reply', [
                'bot_id' => $botId,
                'reply_to_message_id' => $replyToMessageId,
                'action' => 'process_approval_reply',
            ]);
            return null;
        }

        $config = ContentSubmissionBotConfig::where('bot_id', $botId)->where('origin', $type)->first();
        if (!$config) {
            Log::error('[ContentSubmission] Config not found', ['bot_id' => $botId, 'action' => 'process_approval_reply']);
            return null;
        }

        $required = (int) $config->required_approvals;
        if ($required < 1) {
            $required = 1;
        }

        if (!$item->first_approver_chat_id) {
            $item->first_approver_chat_id = $approverChatId;
            $item->approved_at = now();
            if ($required === 1) {
                $item->status = 'approved';
            }
            $item->save();
            Log::info('[ContentSubmission] First approval recorded', [
                'item_id' => $item->id,
                'approver_chat_id' => $approverChatId,
                'action' => 'process_approval_reply',
            ]);
            if ($required === 1) {
                return $item;
            }
            return null;
        }

        if ($item->second_approver_chat_id) {
            Log::warning('[ContentSubmission] Item already fully approved', ['item_id' => $item->id, 'action' => 'process_approval_reply']);
            return null;
        }

        $item->second_approver_chat_id = $approverChatId;
        $item->status = 'approved';
        $item->save();

        Log::info('[ContentSubmission] Second approval recorded, item ready to publish', [
            'item_id' => $item->id,
            'approver_chat_id' => $approverChatId,
            'action' => 'process_approval_reply',
        ]);

        return $item;
    }

    public function publishToChannel(ContentSubmissionItem $item, string $type): bool
    {
        Log::info('[ContentSubmission] publishToChannel start', [
            'item_id' => $item->id,
            'bot_id' => $item->bot_id,
            'action' => 'publish_to_channel',
        ]);

        try {
            $config = ContentSubmissionBotConfig::where('bot_id', $item->bot_id)->where('origin', $type)->first();
            if (!$config || !$config->channel_chat_id) {
                Log::error('[ContentSubmission] No channel config', ['item_id' => $item->id, 'bot_id' => $item->bot_id, 'action' => 'publish_to_channel']);
                return false;
            }

            $bot = Bot::find($item->bot_id);
            if (!$bot) {
                Log::error('[ContentSubmission] Bot not found', ['bot_id' => $item->bot_id, 'action' => 'publish_to_channel']);
                return false;
            }

            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::error('[ContentSubmission] Bot token not found', ['bot_id' => $item->bot_id, 'action' => 'publish_to_channel']);
                return false;
            }

            $telegramBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
            $channelChatId = $config->channel_chat_id;

            $result = null;
            if ($item->content_type === 'image' && $item->file_id) {
                $result = $telegramBot->sendPhoto([
                    'chat_id' => $channelChatId,
                    'photo' => $item->file_id,
                    'caption' => $item->content_text ?? '',
                ]);
            } elseif ($item->content_type === 'video' && $item->file_id) {
                $result = $telegramBot->sendVideo([
                    'chat_id' => $channelChatId,
                    'video' => $item->file_id,
                    'caption' => $item->content_text ?? '',
                ]);
            } else {
                $result = $telegramBot->sendMessage([
                    'chat_id' => $channelChatId,
                    'text' => $item->content_text ?? '(متن خالی)',
                ]);
            }

            if ($result && isset($result['ok']) && $result['ok']) {
                $item->status = 'published';
                $item->published_at = now();
                $item->channel_message_id = $result['result']['message_id'] ?? null;
                $item->save();
                Log::info('[ContentSubmission] publishToChannel success', [
                    'item_id' => $item->id,
                    'channel_message_id' => $item->channel_message_id,
                    'action' => 'publish_to_channel',
                ]);
                return true;
            }

            Log::error('[ContentSubmission] publishToChannel send failed', [
                'item_id' => $item->id,
                'result' => $result,
                'action' => 'publish_to_channel',
            ]);
            return false;
        } catch (Exception $e) {
            Log::error('[ContentSubmission] publishToChannel error', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => 'publish_to_channel',
            ]);
            return false;
        }
    }

    public function notifySubmitter(ContentSubmissionItem $item, string $status, string $type): void
    {
        Log::info('[ContentSubmission] notifySubmitter start', [
            'item_id' => $item->id,
            'submitter_chat_id' => $item->submitter_chat_id,
            'status' => $status,
            'action' => 'notify_submitter',
        ]);

        try {
            $bot = Bot::find($item->bot_id);
            if (!$bot) {
                Log::error('[ContentSubmission] Bot not found for notify', ['bot_id' => $item->bot_id, 'action' => 'notify_submitter']);
                return;
            }

            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::error('[ContentSubmission] Bot token not found for notify', ['bot_id' => $item->bot_id, 'action' => 'notify_submitter']);
                return;
            }

            $userBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            if ($status === 'published' || $status === 'approved') {
                $message = "✅ محتوای شما تایید و در کانال منتشر شد.\n\n🆔 شناسه: {$item->id}";
            } elseif ($status === 'rejected') {
                $message = "❌ متاسفانه محتوای شما رد شد.\n\n🆔 شناسه: {$item->id}";
                if ($item->rejection_reason) {
                    $message .= "\nدلیل: {$item->rejection_reason}";
                }
            } else {
                $message = "📌 وضعیت محتوای شما: {$status}\n\n🆔 شناسه: {$item->id}";
            }

            BotHelper::sendMessageByChatId($userBot, $item->submitter_chat_id, $message);

            Log::info('[ContentSubmission] notifySubmitter sent', [
                'item_id' => $item->id,
                'status' => $status,
                'action' => 'notify_submitter',
            ]);
        } catch (Exception $e) {
            Log::error('[ContentSubmission] notifySubmitter error', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => 'notify_submitter',
            ]);
        }
    }
}
