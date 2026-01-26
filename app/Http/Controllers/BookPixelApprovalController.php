<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\BookGamificationService;
use App\Interfaces\Services\BookPublishingService;
use App\Interfaces\Repositories\BookPageScanRepository;
use App\Models\BookModerationGroup;
use App\Models\BookPageScan;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookPixelApprovalController extends Controller
{
    public function __construct(
        private BookPageScanRepository $scanRepository,
        private BookGamificationService $gamificationService,
        private BookPublishingService $publishingService
    ) {}

    public function index(BotRequest $request)
    {
        Log::info('🔔 Book Pixel Approval - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            $botId = $request->input('bot_id');
            
            // Get bot token
            $token = null;
            if ($request->has('token')) {
                $token = $request->input('token');
            } elseif ($botId) {
                $botModel = \App\Models\Bot::find($botId);
                if ($botModel) {
                    $token = $type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
                }
            }
            
            if (!$token) {
                Log::error('Book Pixel Approval - No token found');
                return;
            }
            
            $bot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            $update = $request->json()->all() ?? $request->all();
            $chatId = $bot->ChatID();

            // Check if this is a moderation group
            $moderationGroup = BookModerationGroup::where('group_chat_id', $chatId)
                ->where('origin', $type)
                ->where('is_active', true)
                ->first();

            if (!$moderationGroup) {
                Log::info('ℹ️ Book Pixel Approval - Not moderation group, ignoring', [
                    'chat_id' => $chatId
                ]);
                return;
            }

            // Handle callback query (approve/reject buttons)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $moderationGroup->bot_id);
                return;
            }

            Log::info('✅ Book Pixel Approval - Message from moderation group', ['chat_id' => $chatId]);
            
        } catch (Exception $e) {
            Log::error('❌ Book Pixel Approval - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $userId = $callbackQuery['from']['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        Log::info('🔘 Book Pixel Approval - Callback query received', [
            'callback_data' => $callbackData,
            'user_id' => $userId,
            'message_id' => $messageId
        ]);

        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        // Handle approve
        if (str_starts_with($callbackData, 'approve_scan_')) {
            $scanId = (int) str_replace('approve_scan_', '', $callbackData);
            $this->approveScan($bot, $scanId, $userId, $botId, $type, $callbackQueryId);
        }
        // Handle reject
        elseif (str_starts_with($callbackData, 'reject_scan_')) {
            $scanId = (int) str_replace('reject_scan_', '', $callbackData);
            $this->rejectScan($bot, $scanId, $userId, $botId, $type, $callbackQueryId);
        }
    }

    private function approveScan(Telegram $bot, int $scanId, ?int $userId, int $botId, string $type, string $callbackQueryId): void
    {
        Log::info('✅ Book Pixel Approval - Approving scan', [
            'scan_id' => $scanId,
            'user_id' => $userId,
            'bot_id' => $botId
        ]);

        $scan = $this->scanRepository->find($scanId);
        if (!$scan || $scan->status !== 'pending_approval') {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => 'اسکن یافت نشد یا قبلاً تایید/رد شده است',
            ]);
            return;
        }

        // Update scan status
        $this->scanRepository->updateStatus($scanId, 'approved', [
            'approved_by_chat_id' => $userId,
            'approved_at' => now(),
            'points_awarded' => 100,
        ]);

        // Award points
        $this->gamificationService->awardScanPoints($botId, $scan->user_id);

        // Add to publishing queue
        $this->publishingService->addToQueue($scan);

        // Notify user
        $this->notifyUser($scan, $botId, $type, 'approved');

        // Update message in moderation group
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید شده', 'callback_data' => 'approved']
                ]
            ]
        ];

        try {
            $bot->editMessageReplyMarkup([
                'chat_id' => $bot->ChatID(),
                'message_id' => $scan->approval_message_id,
                'reply_markup' => json_encode($keyboard)
            ]);
        } catch (Exception $e) {
            Log::error('Book Pixel Approval - Error updating message', [
                'error' => $e->getMessage()
            ]);
        }

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '✅ تایید شد',
        ]);

        Log::info('✅ Book Pixel Approval - Scan approved successfully', [
            'scan_id' => $scanId
        ]);
    }

    private function rejectScan(Telegram $bot, int $scanId, ?int $userId, int $botId, string $type, string $callbackQueryId): void
    {
        Log::info('❌ Book Pixel Approval - Rejecting scan', [
            'scan_id' => $scanId,
            'user_id' => $userId,
            'bot_id' => $botId
        ]);

        $scan = $this->scanRepository->find($scanId);
        if (!$scan || $scan->status !== 'pending_approval') {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => 'اسکن یافت نشد یا قبلاً تایید/رد شده است',
            ]);
            return;
        }

        // Update scan status
        $this->scanRepository->updateStatus($scanId, 'rejected', [
            'rejected_at' => now(),
            'rejection_reason' => 'رد شده توسط ادمین',
        ]);

        // Reload scan to get updated data
        $scan = $this->scanRepository->find($scanId);
        if ($scan) {
            // Notify user
            $this->notifyUser($scan, $botId, $type, 'rejected');
        }

        // Update message in moderation group
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '❌ رد شده', 'callback_data' => 'rejected']
                ]
            ]
        ];

        try {
            $bot->editMessageReplyMarkup([
                'chat_id' => $bot->ChatID(),
                'message_id' => $scan->approval_message_id,
                'reply_markup' => json_encode($keyboard)
            ]);
        } catch (Exception $e) {
            Log::error('Book Pixel Approval - Error updating message', [
                'error' => $e->getMessage()
            ]);
        }

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '❌ رد شد',
        ]);

        Log::info('❌ Book Pixel Approval - Scan rejected', [
            'scan_id' => $scanId
        ]);
    }

    private function notifyUser(BookPageScan $scan, int $botId, string $type, string $status): void
    {
        try {
            $botUser = $scan->user;
            if (!$botUser) {
                Log::warning('Book Pixel Approval - User not found for scan', [
                    'scan_id' => $scan->id,
                    'user_id' => $scan->user_id
                ]);
                return;
            }

            // Get bot token
            $botModel = \App\Models\Bot::find($botId);
            if (!$botModel) {
                Log::error('Book Pixel Approval - Bot not found', ['bot_id' => $botId]);
                return;
            }

            $token = $type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
            if (!$token) {
                Log::error('Book Pixel Approval - Bot token not found', ['bot_id' => $botId]);
                return;
            }

            $userBot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
            $book = $scan->bookPage->book;
            $userScore = $this->gamificationService->getUserScore($botId, $botUser->id);

            if ($status === 'approved') {
                $message = "✅ اسکن صفحه شما تایید شد!\n\n";
                $message .= "📖 کتاب: {$book->name}\n";
                $message .= "📄 صفحه: {$scan->page_number}\n";
                $message .= "🎯 امتیاز اضافه شده: 100\n";
                if ($userScore) {
                    $message .= "📊 امتیاز کل شما: {$userScore->total_points}\n";
                }
                $message .= "\n💡 می‌توانید وویس این صفحه را ارسال کنید (200 امتیاز اضافی)";

                // Add inline keyboard with "Send Voice" button
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎤 ارسال وویس این صفحه', 'callback_data' => "send_voice_{$scan->id}"]
                        ]
                    ]
                ];

                BotHelper::sendKeyboardMessageToChatId($userBot, $message, json_encode($keyboard), $botUser->chat_id);
            } else {
                $message = "❌ متاسفانه اسکن صفحه شما رد شد.\n\n";
                $message .= "📖 کتاب: {$book->name}\n";
                $message .= "📄 صفحه: {$scan->page_number}\n";
                if ($scan->rejection_reason) {
                    $message .= "دلیل: {$scan->rejection_reason}\n";
                }

                BotHelper::sendMessageByChatId($userBot, $botUser->chat_id, $message);
            }

            Log::info('Book Pixel Approval - User notified', [
                'scan_id' => $scan->id,
                'user_id' => $botUser->id,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Book Pixel Approval - Error notifying user', [
                'error' => $e->getMessage(),
                'scan_id' => $scan->id
            ]);
        }
    }
}
