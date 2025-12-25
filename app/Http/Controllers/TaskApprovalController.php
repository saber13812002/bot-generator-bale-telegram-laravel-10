<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\MissionPersonnel;
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

            // If it's a reply to a message, find the task or mission by message_id
            if ($replyToMessageId) {
                Log::info('💬 Task Approval Bot - Reply detected', [
                    'reply_to_message_id' => $replyToMessageId,
                    'text' => $text
                ]);
                
                // First check for Task
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
                    return;
                }

                // Then check for Mission
                $missionPersonnel = MissionPersonnel::where('approval_message_id', $replyToMessageId)
                    ->where('status', 'pending_approval')
                    ->first();

                if ($missionPersonnel) {
                    $approvalText = mb_strtolower(trim($text));
                    
                    if ($approvalText == 'تایید' || $approvalText == 'تاييد') {
                        Log::info('✅ Task Approval Bot - Approving mission', ['mission_personnel_id' => $missionPersonnel->id]);
                        $this->approveMission($bot, $missionPersonnel, $chatId, $type);
                    } else {
                        Log::info('❌ Task Approval Bot - Rejecting mission', ['mission_personnel_id' => $missionPersonnel->id, 'reason' => $text]);
                        $this->rejectMission($bot, $missionPersonnel, $text, $chatId, $type);
                    }
                    return;
                }

                Log::warning('⚠️ Task Approval Bot - Task or Mission not found for reply', ['reply_to_message_id' => $replyToMessageId]);
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
        
        try {
            // Update task status with transaction to ensure data integrity
            $updated = $task->update([
                'task_status' => 'approved',
                'approved_at' => now(),
                'approved_by_chat_id' => $bot->ChatID(),
            ]);

            if (!$updated) {
                Log::error("Failed to update task status", [
                    'task_id' => $task->id,
                    'personnel_id' => $personnel->id
                ]);
                throw new Exception("خطا در ثبت تغییرات تسک در دیتابیس");
            }

            // Log successful update
            Log::info("Task approved and saved to database", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'approved_by_chat_id' => $bot->ChatID(),
                'approved_at' => now()->toDateTimeString()
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

            Log::info("Task approval process completed", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id
            ]);
        } catch (Exception $e) {
            Log::error("Error approving task", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reject task
     */
    private function rejectTask($bot, $task, $rejectionReason, $groupChatId, $type)
    {
        $personnel = $task->personnel;
        
        try {
            // Update task status with transaction to ensure data integrity
            $updated = $task->update([
                'task_status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
                'approved_by_chat_id' => $bot->ChatID(),
            ]);

            if (!$updated) {
                Log::error("Failed to update task status", [
                    'task_id' => $task->id,
                    'personnel_id' => $personnel->id
                ]);
                throw new Exception("خطا در ثبت تغییرات تسک در دیتابیس");
            }

            // Log successful update
            Log::info("Task rejected and saved to database", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'approved_by_chat_id' => $bot->ChatID(),
                'rejected_at' => now()->toDateTimeString(),
                'rejection_reason' => $rejectionReason
            ]);

            // Send confirmation to group
            $message = "❌ تسک با شناسه " . $task->id . " رد شد.\n";
            $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "دلیل: " . $rejectionReason;
            
            BotHelper::sendMessageByChatId($bot, $groupChatId, $message);

            // Send notification to user via mission bot
            $this->notifyUser($task, $personnel, 'rejected', $type, $rejectionReason);

            Log::info("Task rejection process completed", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'reason' => $rejectionReason
            ]);
        } catch (Exception $e) {
            Log::error("Error rejecting task", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
     * Approve mission
     */
    private function approveMission($bot, $missionPersonnel, $groupChatId, $type)
    {
        $personnel = $missionPersonnel->personnel;
        $mission = $missionPersonnel->mission;
        
        try {
            // Update mission personnel status
            $updated = $missionPersonnel->approve($bot->ChatID());

            if (!$updated) {
                Log::error("Failed to update mission status", [
                    'mission_personnel_id' => $missionPersonnel->id,
                    'mission_id' => $mission->id,
                    'personnel_id' => $personnel->id
                ]);
                throw new Exception("خطا در ثبت تغییرات ماموریت در دیتابیس");
            }

            // Refresh to get latest data
            $missionPersonnel->refresh();

            // Log successful update
            Log::info("Mission approved and saved to database", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'approved_by_chat_id' => $bot->ChatID(),
                'approved_at' => $missionPersonnel->approved_at?->toDateTimeString(),
                'status' => $missionPersonnel->status
            ]);

            // Send confirmation to group
            $message = "✅ ماموریت با شناسه " . $mission->id . " تایید شد.\n";
            $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "امتیاز اضافه شده: " . $mission->points;
            
            BotHelper::sendMessageByChatId($bot, $groupChatId, $message);

            // Send notification to user via mission bot
            $this->notifyUserMission($missionPersonnel, $personnel, 'approved', $type);

            // Update personnel rank if needed
            $this->updatePersonnelRank($personnel);

            Log::info("Mission approval process completed", [
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id
            ]);
        } catch (Exception $e) {
            Log::error("Error approving mission", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reject mission
     */
    private function rejectMission($bot, $missionPersonnel, $rejectionReason, $groupChatId, $type)
    {
        $personnel = $missionPersonnel->personnel;
        $mission = $missionPersonnel->mission;
        
        try {
            // Update mission personnel status
            $updated = $missionPersonnel->reject($rejectionReason, $bot->ChatID());

            if (!$updated) {
                Log::error("Failed to update mission status", [
                    'mission_personnel_id' => $missionPersonnel->id,
                    'mission_id' => $mission->id,
                    'personnel_id' => $personnel->id
                ]);
                throw new Exception("خطا در ثبت تغییرات ماموریت در دیتابیس");
            }

            // Refresh to get latest data
            $missionPersonnel->refresh();

            // Log successful update
            Log::info("Mission rejected and saved to database", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'approved_by_chat_id' => $bot->ChatID(),
                'rejected_at' => $missionPersonnel->rejected_at?->toDateTimeString(),
                'rejection_reason' => $rejectionReason,
                'status' => $missionPersonnel->status
            ]);

            // Send confirmation to group
            $message = "❌ ماموریت با شناسه " . $mission->id . " رد شد.\n";
            $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "دلیل: " . $rejectionReason;
            
            BotHelper::sendMessageByChatId($bot, $groupChatId, $message);

            // Send notification to user via mission bot
            $this->notifyUserMission($missionPersonnel, $personnel, 'rejected', $type, $rejectionReason);

            Log::info("Mission rejection process completed", [
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'reason' => $rejectionReason
            ]);
        } catch (Exception $e) {
            Log::error("Error rejecting mission", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Notify user about mission status
     */
    private function notifyUserMission($missionPersonnel, $personnel, $status, $type, $rejectionReason = null)
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

        $mission = $missionPersonnel->mission;

        if ($status == 'approved') {
            $message = "✅ ماموریت شما تایید شد!\n\n";
            $message .= "عنوان ماموریت: " . $mission->title . "\n";
            $message .= "امتیاز اضافه شده: " . $mission->points . "\n";
            $message .= "امتیاز کل شما: " . $personnel->total_points . "\n";
            $message .= "درجه فعلی: " . $personnel->rank;
        } else {
            $message = "❌ متاسفانه ماموریت شما رد شد.\n\n";
            $message .= "عنوان ماموریت: " . $mission->title . "\n";
            if ($rejectionReason) {
                $message .= "دلیل رد: " . $rejectionReason . "\n\n";
            }
            $message .= "لطفا ماموریت را اصلاح کرده و دوباره ارسال کنید.";
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
