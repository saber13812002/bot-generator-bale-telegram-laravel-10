<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Personnel;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class TaskApprovalController extends Controller
{
    /**
     * Handle task approval/rejection in group
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Task Approval Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            
            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('MISSION_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('MISSION_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Task Approval Bot - Webhook status check', [
                'type' => $type,
                'webhook_ok' => $webhookInfo['ok'] ?? false,
                'webhook_url' => $webhookInfo['result']['url'] ?? null,
                'pending_updates' => $webhookInfo['result']['pending_update_count'] ?? 0
            ]);

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();
            $messageId = $bot->MessageID();
            
            // Log message received
            Log::info('📨 Task Approval Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'message_id' => $messageId,
                'type' => $type,
                'is_group' => $chatId < 0
            ]);
            
            // Get reply_to_message_id from request data
            $replyToMessageId = null;
            $update = $request->json()->all() ?? $request->all();
            if (isset($update['message']['reply_to_message']['message_id'])) {
                $replyToMessageId = $update['message']['reply_to_message']['message_id'];
            }

            // Check if this is the approval group
            $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
            if ($chatId != $approvalGroupChatId) {
                Log::info('ℹ️ Task Approval Bot - Not approval group, ignoring', [
                    'chat_id' => $chatId,
                    'approval_group_chat_id' => $approvalGroupChatId
                ]);
                return; // Not the approval group, ignore
            }

            Log::info('✅ Task Approval Bot - Message from approval group', ['chat_id' => $chatId]);
            
            // سلام اولیه برای اطمینان از کارکرد ربات (فقط برای پیام‌های متنی)
            if ($text && !empty(trim($text))) {
                // فقط برای پیام‌های متنی (نه برای update های دیگر)
                Log::info('👋 Task Approval Bot - سلام: ربات تایید آماده است', ['chat_id' => $chatId, 'text' => $text]);
            }

            // If it's a reply to a message, find the task by message_id
            if ($replyToMessageId) {
                Log::info('💬 Task Approval Bot - Reply detected', [
                    'reply_to_message_id' => $replyToMessageId,
                    'text' => $text
                ]);
                
                $task = Task::where('approval_message_id', $replyToMessageId)
                    ->where('task_status', 'pending_approval')
                    ->first();

                if ($task) {
                    $approvalText = mb_strtolower(trim($text));
                    
                    if ($approvalText == 'تایید' || $approvalText == 'تاييد') {
                        Log::info('✅ Task Approval Bot - Approving task', ['task_id' => $task->id]);
                        $this->approveTask($bot, $task, $chatId, $type);
                    } else {
                        Log::info('❌ Task Approval Bot - Rejecting task', ['task_id' => $task->id, 'reason' => $text]);
                        $this->rejectTask($bot, $task, $text, $chatId, $type);
                    }
                } else {
                    Log::warning('⚠️ Task Approval Bot - Task not found for reply', ['reply_to_message_id' => $replyToMessageId]);
                }
            } else {
                Log::info('ℹ️ Task Approval Bot - Not a reply message', ['chat_id' => $chatId]);
            }
            
            Log::info('✅ Task Approval Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Task Approval Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId ?? null,
                'text' => $text ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
        }
    }

    /**
     * Approve task
     */
    private function approveTask($bot, $task, $groupChatId, $type)
    {
        $personnel = $task->personnel;
        
        // Update task status
        $task->update([
            'task_status' => 'approved',
            'approved_at' => now(),
            'approved_by_chat_id' => $bot->ChatID(),
        ]);

        // Send confirmation to group
        $message = "✅ تسک با شناسه " . $task->id . " تایید شد.\n";
        $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "امتیاز اضافه شده: " . $task->points;
        
        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);

        // Send notification to user via mission bot
        $this->notifyUser($task, $personnel, 'approved', $type);

        // Update personnel rank if needed
        $this->updatePersonnelRank($personnel);

        Log::info("Task approved: " . $task->id . " - Personnel: " . $personnel->id);
    }

    /**
     * Reject task
     */
    private function rejectTask($bot, $task, $rejectionReason, $groupChatId, $type)
    {
        $personnel = $task->personnel;
        
        // Update task status
        $task->update([
            'task_status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $rejectionReason,
            'approved_by_chat_id' => $bot->ChatID(),
        ]);

        // Send confirmation to group
        $message = "❌ تسک با شناسه " . $task->id . " رد شد.\n";
        $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "دلیل: " . $rejectionReason;
        
        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);

        // Send notification to user via mission bot
        $this->notifyUser($task, $personnel, 'rejected', $type, $rejectionReason);

        Log::info("Task rejected: " . $task->id . " - Personnel: " . $personnel->id . " - Reason: " . $rejectionReason);
    }

    /**
     * Notify user about task status
     */
    private function notifyUser($task, $personnel, $status, $type, $rejectionReason = null)
    {
        // Find bot user for this personnel
        $botUser = \App\Models\BotUsers::where('settings->personnel_id', $personnel->id)
            ->where('origin', $type)
            ->first();

        if (!$botUser) {
            Log::warning("Bot user not found for personnel: " . $personnel->id);
            return;
        }

        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $bot = new Telegram($token, $type);

        if ($status == 'approved') {
            $message = "✅ تسک شما تایید شد!\n\n";
            $message .= "نام تسک: " . $task->task_name . "\n";
            $message .= "امتیاز اضافه شده: " . $task->points . "\n";
            $message .= "امتیاز کل شما: " . $personnel->total_points . "\n";
            $message .= "درجه فعلی: " . $personnel->rank;
        } else {
            $message = "❌ متاسفانه تسک شما رد شد.\n\n";
            $message .= "نام تسک: " . $task->task_name . "\n";
            if ($rejectionReason) {
                $message .= "دلیل رد: " . $rejectionReason . "\n\n";
            }
            $message .= "لطفا تسک را اصلاح کرده و دوباره ارسال کنید، یا از رزرو تسک جدید استفاده کنید.";
        }

        BotHelper::sendMessageByChatId($bot, $botUser->chat_id, $message);
    }

    /**
     * Update personnel rank based on total points
     */
    private function updatePersonnelRank($personnel)
    {
        $totalPoints = $personnel->total_points;
        
        // Define rank thresholds (you can adjust these values)
        $newRank = 'سرباز صفر';
        if ($totalPoints >= 1000) {
            $newRank = 'سرباز سه';
        } elseif ($totalPoints >= 500) {
            $newRank = 'سرباز دو';
        } elseif ($totalPoints >= 100) {
            $newRank = 'سرباز یک';
        }

        if ($personnel->rank != $newRank) {
            $personnel->update(['rank' => $newRank]);
            Log::info("Personnel rank updated: " . $personnel->id . " - New rank: " . $newRank);
        }
    }
}
