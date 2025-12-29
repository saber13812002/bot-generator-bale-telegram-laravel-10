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
            
            // Get raw update data for debugging
            $update = $request->json()->all() ?? $request->all();
            
            // Get reply_to_message_id and user_id from request data
            $replyToMessageId = null;
            $userId = null; // User ID of the person who replied
            if (isset($update['message']['reply_to_message']['message_id'])) {
                $replyToMessageId = $update['message']['reply_to_message']['message_id'];
            }
            // Get user_id from the message sender (not chat_id which is group id)
            if (isset($update['message']['from']['id'])) {
                $userId = $update['message']['from']['id'];
            }
            
            // Log message received with full context
            Log::info('📨 Task Approval Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'message_id' => $messageId,
                'type' => $type,
                'is_group' => $chatId < 0,
                'user_id' => $userId,
                'reply_to_message_id' => $replyToMessageId,
                'update_structure' => [
                    'has_message' => isset($update['message']),
                    'has_reply_to_message' => isset($update['message']['reply_to_message']),
                    'has_from' => isset($update['message']['from']),
                    'from_id' => $update['message']['from']['id'] ?? null,
                    'from_username' => $update['message']['from']['username'] ?? null,
                    'from_first_name' => $update['message']['from']['first_name'] ?? null
                ]
            ]);

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
                    'text' => $text,
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'raw_update_keys' => array_keys($update)
                ]);
                
                // First check for Task
                Log::info('🔍 Task Approval Bot - Searching for task', [
                    'approval_message_id' => $replyToMessageId
                ]);
                
                $task = Task::where('approval_message_id', $replyToMessageId)
                    ->where('task_status', 'pending_approval')
                    ->first();

                if ($task) {
                    Log::info('✅ Task Approval Bot - Task found', [
                        'task_id' => $task->id,
                        'task_name' => $task->task_name,
                        'task_status' => $task->task_status,
                        'approval_message_id' => $task->approval_message_id
                    ]);
                    
                    $approvalText = mb_strtolower(trim($text));
                    Log::info('📝 Task Approval Bot - Processing approval text', [
                        'original_text' => $text,
                        'normalized_text' => $approvalText,
                        'is_approval' => ($approvalText == 'تایید' || $approvalText == 'تاييد')
                    ]);
                    
                    if ($approvalText == 'تایید' || $approvalText == 'تاييد') {
                        Log::info('✅ Task Approval Bot - Approving task', [
                            'task_id' => $task->id,
                            'user_id' => $userId,
                            'type' => $type
                        ]);
                        $this->approveTask($bot, $task, $userId, $type);
                    } else {
                        // If it's not "تایید", send feedback message to user instead of rejecting
                        Log::info('💬 Task Approval Bot - Sending feedback to user', [
                            'task_id' => $task->id,
                            'user_id' => $userId,
                            'feedback' => $text
                        ]);
                        $this->sendFeedbackToUser($bot, $task, $text, $userId, $type);
                    }
                    return;
                } else {
                    Log::info('❌ Task Approval Bot - Task not found', [
                        'approval_message_id' => $replyToMessageId,
                        'query_result' => 'no task found with this approval_message_id and pending_approval status'
                    ]);
                }

                // Then check for Mission
                Log::info('🔍 Task Approval Bot - Searching for mission', [
                    'approval_message_id' => $replyToMessageId
                ]);
                
                $missionPersonnel = MissionPersonnel::where('approval_message_id', $replyToMessageId)
                    ->where('status', 'pending_approval')
                    ->first();

                if ($missionPersonnel) {
                    Log::info('✅ Task Approval Bot - Mission found', [
                        'mission_personnel_id' => $missionPersonnel->id,
                        'mission_id' => $missionPersonnel->mission_id,
                        'personnel_id' => $missionPersonnel->personnel_id,
                        'status' => $missionPersonnel->status,
                        'approval_message_id' => $missionPersonnel->approval_message_id
                    ]);
                    
                    $approvalText = mb_strtolower(trim($text));
                    Log::info('📝 Task Approval Bot - Processing approval text for mission', [
                        'original_text' => $text,
                        'normalized_text' => $approvalText,
                        'is_approval' => ($approvalText == 'تایید' || $approvalText == 'تاييد')
                    ]);
                    
                    if ($approvalText == 'تایید' || $approvalText == 'تاييد') {
                        Log::info('✅ Task Approval Bot - Approving mission - Starting process', [
                            'mission_personnel_id' => $missionPersonnel->id,
                            'mission_id' => $missionPersonnel->mission_id,
                            'user_id' => $userId,
                            'type' => $type
                        ]);
                        $this->approveMission($bot, $missionPersonnel, $userId, $type);
                        Log::info('✅ Task Approval Bot - Approving mission - Process completed', [
                            'mission_personnel_id' => $missionPersonnel->id
                        ]);
                    } else {
                        // If it's not "تایید", send feedback message to user instead of rejecting
                        Log::info('💬 Task Approval Bot - Sending feedback to user for mission', [
                            'mission_personnel_id' => $missionPersonnel->id,
                            'user_id' => $userId,
                            'feedback' => $text
                        ]);
                        $this->sendFeedbackToUserMission($bot, $missionPersonnel, $text, $userId, $type);
                    }
                    return;
                } else {
                    Log::warning('❌ Task Approval Bot - Mission not found', [
                        'approval_message_id' => $replyToMessageId,
                        'query_result' => 'no mission_personnel found with this approval_message_id and pending_approval status',
                        'debug_query' => 'SELECT * FROM mission_personnel WHERE approval_message_id = ' . $replyToMessageId . ' AND status = "pending_approval"'
                    ]);
                }

                Log::warning('⚠️ Task Approval Bot - Task or Mission not found for reply', [
                    'reply_to_message_id' => $replyToMessageId,
                    'searched_tasks' => 'no task found',
                    'searched_missions' => 'no mission found'
                ]);
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
    private function approveTask($bot, $task, $approvedByUserId, $type)
    {
        $personnel = $task->personnel;
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        
        try {
            // Update task status with transaction to ensure data integrity
            $updated = $task->update([
                'task_status' => 'approved',
                'approved_at' => now(),
                'approved_by_chat_id' => $approvedByUserId, // Use user_id, not group chat_id
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
                'approved_by_user_id' => $approvedByUserId,
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
    private function rejectTask($bot, $task, $rejectionReason, $rejectedByUserId, $type)
    {
        $personnel = $task->personnel;
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        
        try {
            // Update task status with transaction to ensure data integrity
            $updated = $task->update([
                'task_status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
                'approved_by_chat_id' => $rejectedByUserId, // Use user_id, not group chat_id
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
                'rejected_by_user_id' => $rejectedByUserId,
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

        // Add /reserve button to message
        $option = [
            array($bot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessageToChatId($bot, $message, $inlineKeyboard, $botUser->chat_id);
    }

    /**
     * Approve mission
     */
    private function approveMission($bot, $missionPersonnel, $approvedByUserId, $type)
    {
        Log::info('🚀 Mission Approval - Starting approveMission method', [
            'mission_personnel_id' => $missionPersonnel->id,
            'approved_by_user_id' => $approvedByUserId,
            'type' => $type,
            'current_status' => $missionPersonnel->status
        ]);
        
        $personnel = $missionPersonnel->personnel;
        $mission = $missionPersonnel->mission;
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        
        Log::info('📋 Mission Approval - Loaded related data', [
            'personnel_id' => $personnel?->id,
            'mission_id' => $mission?->id,
            'group_chat_id' => $groupChatId,
            'personnel_name' => $personnel ? ($personnel->first_name . ' ' . $personnel->last_name) : 'null',
            'mission_title' => $mission?->title
        ]);
        
        try {
            // Update mission personnel status
            Log::info('💾 Mission Approval - Calling approve method on MissionPersonnel', [
                'mission_personnel_id' => $missionPersonnel->id,
                'approved_by_user_id' => $approvedByUserId
            ]);
            
            $updated = $missionPersonnel->approve($approvedByUserId); // Use user_id, not group chat_id

            Log::info('💾 Mission Approval - Approve method returned', [
                'mission_personnel_id' => $missionPersonnel->id,
                'updated' => $updated
            ]);

            if (!$updated) {
                Log::error("❌ Mission Approval - Failed to update mission status", [
                    'mission_personnel_id' => $missionPersonnel->id,
                    'mission_id' => $mission->id,
                    'personnel_id' => $personnel->id,
                    'approved_by_user_id' => $approvedByUserId
                ]);
                throw new Exception("خطا در ثبت تغییرات ماموریت در دیتابیس");
            }

            // Refresh to get latest data
            Log::info('🔄 Mission Approval - Refreshing mission personnel data', [
                'mission_personnel_id' => $missionPersonnel->id
            ]);
            $missionPersonnel->refresh();

            // Log successful update
            Log::info("✅ Mission Approval - Mission approved and saved to database", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'approved_by_user_id' => $approvedByUserId,
                'approved_at' => $missionPersonnel->approved_at?->toDateTimeString(),
                'status' => $missionPersonnel->status,
                'completed_at' => $missionPersonnel->completed_at?->toDateTimeString()
            ]);

            // Send confirmation to group
            Log::info('📤 Mission Approval - Sending confirmation to group', [
                'group_chat_id' => $groupChatId,
                'mission_id' => $mission->id
            ]);
            
            $message = "✅ ماموریت با شناسه " . $mission->id . " تایید شد.\n";
            $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "امتیاز اضافه شده: " . $mission->points;
            
            $groupMessageResult = BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
            Log::info('📤 Mission Approval - Group message sent', [
                'group_chat_id' => $groupChatId,
                'result' => $groupMessageResult
            ]);

            // Send notification to user via mission bot
            Log::info('📤 Mission Approval - Sending notification to user', [
                'personnel_id' => $personnel->id,
                'type' => $type
            ]);
            $this->notifyUserMission($missionPersonnel, $personnel, 'approved', $type);
            Log::info('📤 Mission Approval - User notification sent');

            // Update personnel rank if needed
            Log::info('⭐ Mission Approval - Updating personnel rank', [
                'personnel_id' => $personnel->id,
                'current_rank' => $personnel->rank,
                'total_points' => $personnel->total_points
            ]);
            $this->updatePersonnelRank($personnel);
            Log::info('⭐ Mission Approval - Personnel rank updated');

            Log::info("✅ Mission Approval - Mission approval process completed successfully", [
                'mission_id' => $mission->id,
                'mission_personnel_id' => $missionPersonnel->id,
                'personnel_id' => $personnel->id
            ]);
        } catch (Exception $e) {
            Log::error("❌ Mission Approval - Error approving mission", [
                'mission_personnel_id' => $missionPersonnel->id,
                'mission_id' => $mission->id ?? null,
                'personnel_id' => $personnel->id ?? null,
                'approved_by_user_id' => $approvedByUserId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reject mission
     */
    private function rejectMission($bot, $missionPersonnel, $rejectionReason, $rejectedByUserId, $type)
    {
        $personnel = $missionPersonnel->personnel;
        $mission = $missionPersonnel->mission;
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        
        try {
            // Update mission personnel status
            $updated = $missionPersonnel->reject($rejectionReason, $rejectedByUserId); // Use user_id, not group chat_id

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
                'rejected_by_user_id' => $rejectedByUserId,
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
        Log::info('📨 Mission Notification - Starting notifyUserMission', [
            'mission_personnel_id' => $missionPersonnel->id,
            'personnel_id' => $personnel->id,
            'status' => $status,
            'type' => $type
        ]);
        
        // Find bot user for this personnel
        Log::info('🔍 Mission Notification - Searching for bot user', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);
        
        $botUser = \App\Models\BotUsers::where('settings->personnel_id', $personnel->id)
            ->where('origin', $type)
            ->first();

        if (!$botUser) {
            Log::warning("❌ Mission Notification - Bot user not found for personnel", [
                'personnel_id' => $personnel->id,
                'type' => $type,
                'query' => 'settings->personnel_id = ' . $personnel->id . ' AND origin = ' . $type
            ]);
            return;
        }

        Log::info('✅ Mission Notification - Bot user found', [
            'bot_user_id' => $botUser->id,
            'chat_id' => $botUser->chat_id,
            'origin' => $botUser->origin
        ]);

        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        Log::info('🔑 Mission Notification - Bot token prepared', [
            'type' => $type,
            'token_preview' => substr($token, 0, 10) . '...'
        ]);
        
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

        Log::info('📤 Mission Notification - Sending message to user', [
            'chat_id' => $botUser->chat_id,
            'message_length' => strlen($message),
            'status' => $status
        ]);
        
        // Add /reserve button to message
        $option = [
            array($bot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        $result = BotHelper::sendKeyboardMessageToChatId($bot, $message, $inlineKeyboard, $botUser->chat_id);
        
        Log::info('📤 Mission Notification - Message sent result', [
            'chat_id' => $botUser->chat_id,
            'result' => $result,
            'status' => $status
        ]);
    }

    /**
     * Send feedback message to user when admin replies (not approval)
     */
    private function sendFeedbackToUser($bot, $task, $feedbackText, $userId, $type)
    {
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        $personnel = $task->personnel;
        
        try {
            // Find bot user for this personnel
            $botUser = \App\Models\BotUsers::where('settings->personnel_id', $personnel->id)
                ->where('origin', $type)
                ->first();

            if (!$botUser) {
                Log::warning("Bot user not found for personnel: " . $personnel->id);
                // Still send confirmation to group
                $message = "⚠️ پیام برای کاربر ارسال نشد (کاربر یافت نشد)\n";
                $message .= "تسک: " . $task->id . "\n";
                $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
                $message .= "پیام: " . $feedbackText;
                BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
                return;
            }

            $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
            $userBot = new Telegram($token, $type);

            // Send feedback message to user
            $message = "📩 پیام از ادمین برای تسک شما:\n\n";
            $message .= "📋 نام تسک: " . $task->task_name . "\n";
            $message .= "🔗 لینک ارسال شده: " . $task->final_link . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💬 پیام ادمین:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= $feedbackText . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💡 لطفا اقدامات لازم را انجام داده و دوباره لینک جدید را ارسال کنید.";

            // Add /reserve button to message
            $option = [
                array($userBot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'))
            ];
            $inlineKeyboard = $userBot->buildInlineKeyBoard($option);
            BotHelper::sendKeyboardMessageToChatId($userBot, $message, $inlineKeyboard, $botUser->chat_id);

            // Update task status to rejected so user can resubmit
            $task->update([
                'task_status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $feedbackText,
                'approved_by_chat_id' => $userId,
            ]);

            // Send confirmation to group
            $groupMessage = "✅ پیام برای کاربر ارسال شد:\n";
            $groupMessage .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $groupMessage .= "تسک: " . $task->id . "\n";
            $groupMessage .= "پیام: " . $feedbackText;
            $groupMessage .= "\n\n⚠️ تسک به وضعیت 'رد شده' تغییر کرد تا کاربر بتواند لینک جدید ارسال کند.";
            
            BotHelper::sendMessageByChatId($bot, $groupChatId, $groupMessage);

            Log::info("Feedback sent to user and task status updated to rejected", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'feedback' => $feedbackText,
                'new_status' => 'rejected'
            ]);
        } catch (Exception $e) {
            Log::error("Error sending feedback to user", [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try to notify group about error
            try {
                $errorMessage = "❌ خطا در ارسال پیام به کاربر:\n";
                $errorMessage .= "تسک: " . $task->id . "\n";
                $errorMessage .= "خطا: " . $e->getMessage();
                BotHelper::sendMessageByChatId($bot, $groupChatId, $errorMessage);
            } catch (Exception $e2) {
                Log::error("Failed to send error message to group", ['error' => $e2->getMessage()]);
            }
        }
    }

    /**
     * Send feedback message to user for mission when admin replies (not approval)
     */
    private function sendFeedbackToUserMission($bot, $missionPersonnel, $feedbackText, $userId, $type)
    {
        $groupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        $personnel = $missionPersonnel->personnel;
        $mission = $missionPersonnel->mission;
        
        try {
            // Find bot user for this personnel
            $botUser = \App\Models\BotUsers::where('settings->personnel_id', $personnel->id)
                ->where('origin', $type)
                ->first();

            if (!$botUser) {
                Log::warning("Bot user not found for personnel: " . $personnel->id);
                // Still send confirmation to group
                $message = "⚠️ پیام برای کاربر ارسال نشد (کاربر یافت نشد)\n";
                $message .= "ماموریت: " . $mission->id . "\n";
                $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
                $message .= "پیام: " . $feedbackText;
                BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
                return;
            }

            $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
            $userBot = new Telegram($token, $type);

            // Send feedback message to user
            $message = "📩 پیام از ادمین برای ماموریت شما:\n\n";
            $message .= "📋 عنوان ماموریت: " . $mission->title . "\n";
            $message .= "🔗 لینک ارسال شده: " . $missionPersonnel->result_link . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💬 پیام ادمین:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= $feedbackText . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💡 لطفا اقدامات لازم را انجام داده و دوباره لینک جدید را ارسال کنید.";

            // Add /reserve button to message
            $option = [
                array($userBot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'))
            ];
            $inlineKeyboard = $userBot->buildInlineKeyBoard($option);
            BotHelper::sendKeyboardMessageToChatId($userBot, $message, $inlineKeyboard, $botUser->chat_id);

            // Update mission personnel status to rejected so user can resubmit
            $missionPersonnel->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $feedbackText,
                'approved_by_chat_id' => $userId,
            ]);

            // Send confirmation to group
            $groupMessage = "✅ پیام برای کاربر ارسال شد:\n";
            $groupMessage .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $groupMessage .= "ماموریت: " . $mission->id . "\n";
            $groupMessage .= "پیام: " . $feedbackText;
            $groupMessage .= "\n\n⚠️ ماموریت به وضعیت 'رد شده' تغییر کرد تا کاربر بتواند لینک جدید ارسال کند.";
            
            BotHelper::sendMessageByChatId($bot, $groupChatId, $groupMessage);

            Log::info("Feedback sent to user for mission and mission status updated to rejected", [
                'mission_id' => $mission->id,
                'mission_personnel_id' => $missionPersonnel->id,
                'personnel_id' => $personnel->id,
                'feedback' => $feedbackText,
                'new_status' => 'rejected'
            ]);
        } catch (Exception $e) {
            Log::error("Error sending feedback to user for mission", [
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try to notify group about error
            try {
                $errorMessage = "❌ خطا در ارسال پیام به کاربر:\n";
                $errorMessage .= "ماموریت: " . $mission->id . "\n";
                $errorMessage .= "خطا: " . $e->getMessage();
                BotHelper::sendMessageByChatId($bot, $groupChatId, $errorMessage);
            } catch (Exception $e2) {
                Log::error("Failed to send error message to group", ['error' => $e2->getMessage()]);
            }
        }
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
