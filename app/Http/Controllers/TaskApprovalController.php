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
    // Cache for bot username to avoid multiple getMe calls
    private static array $botUsernameCache = [];
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

            // Get raw update data for debugging
            $update = $request->json()->all() ?? $request->all();
            
            // Check for callback query (inline button clicks)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type);
                return;
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();
            $messageId = $bot->MessageID();
            
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
            
            // Handle commands (only if not a reply)
            if ($text && !empty(trim($text)) && !$replyToMessageId) {
                $text = trim($text);
                
                // Normalize command (remove @bot_username if present)
                $normalizedCommand = $this->normalizeCommand($text, $bot, $type);
                
                if ($normalizedCommand == '/help') {
                    $this->handleHelp($bot, $type, $chatId);
                    return;
                } elseif ($normalizedCommand == '/pending') {
                    $this->handlePendingStats($bot, $type, $chatId);
                    return;
                } elseif ($normalizedCommand == '/today') {
                    $this->handleTodayStats($bot, $type, $chatId);
                    return;
                } elseif ($normalizedCommand == '/top') {
                    $this->handleTopUsers($bot, $type, $chatId);
                    return;
                } elseif ($normalizedCommand == '/stats') {
                    $this->handleAllStats($bot, $type, $chatId);
                    return;
                } elseif (str_starts_with($normalizedCommand, '/approve ')) {
                    // Handle /approve {id} command
                    $id = trim(str_replace('/approve', '', $normalizedCommand));
                    if (is_numeric($id)) {
                        $this->handleApproveCommand($bot, (int)$id, $userId, $type, $chatId);
                    } else {
                        BotHelper::sendMessageByChatId($bot, $chatId, "❌ شناسه نامعتبر است. فرمت صحیح: /approve {id}");
                    }
                    return;
                }
            }

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
            
            // Add pending counts
            $pendingTasksCount = Task::where('task_status', 'pending_approval')->count();
            $pendingMissionsCount = MissionPersonnel::where('status', 'pending_approval')->count();
            $message .= "\n\n📊 تعداد تسک‌های در انتظار: {$pendingTasksCount}";
            $message .= "\n📊 تعداد ماموریت‌های در انتظار: {$pendingMissionsCount}";
            
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
            
            // Add pending counts
            $pendingTasksCount = Task::where('task_status', 'pending_approval')->count();
            $pendingMissionsCount = MissionPersonnel::where('status', 'pending_approval')->count();
            $message .= "\n\n📊 تعداد تسک‌های در انتظار: {$pendingTasksCount}";
            $message .= "\n📊 تعداد ماموریت‌های در انتظار: {$pendingMissionsCount}";
            
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

    /**
     * Handle /help command
     */
    private function handleHelp($bot, $type, $groupChatId)
    {
        $message = "📖 دستورات ربات تایید:\n\n";
        $message .= "/help - نمایش این راهنما\n";
        $message .= "/pending - تعداد و لیست تسک‌ها/ماموریت‌های در انتظار\n";
        $message .= "/today - آمار امروز (تایید شده، رد شده، در انتظار)\n";
        $message .= "/top - برترین کاربران امروز\n";
        $message .= "/stats - آمار کامل\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 برای تایید: کلمه 'تایید' را به پیام reply کنید\n";
        $message .= "💡 برای رد: پیام خود را به پیام reply کنید";

        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
    }

    /**
     * Handle /pending command
     */
    private function handlePendingStats($bot, $type, $groupChatId)
    {
        $pendingTasks = Task::where('task_status', 'pending_approval')
            ->with('personnel')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $pendingMissions = MissionPersonnel::where('status', 'pending_approval')
            ->with(['personnel', 'mission'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $pendingTasksCount = Task::where('task_status', 'pending_approval')->count();
        $pendingMissionsCount = MissionPersonnel::where('status', 'pending_approval')->count();

        $message = "⏳ تسک‌ها و ماموریت‌های در انتظار:\n\n";
        $message .= "📋 تعداد تسک‌های در انتظار: {$pendingTasksCount}\n";
        $message .= "📋 تعداد ماموریت‌های در انتظار: {$pendingMissionsCount}\n\n";

        if ($pendingTasks->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 آخرین تسک‌های در انتظار:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            foreach ($pendingTasks as $task) {
                $personnelName = $task->personnel ? $task->personnel->first_name . ' ' . $task->personnel->last_name : 'نامشخص';
                $message .= "🆔 تسک #{$task->id}\n";
                $message .= "👤 کاربر: {$personnelName}\n";
                $message .= "🎯 امتیاز: {$task->points}\n";
                $message .= "📅 زمان: " . $task->created_at->format('Y-m-d H:i') . "\n\n";
            }
        }

        if ($pendingMissions->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 آخرین ماموریت‌های در انتظار:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            foreach ($pendingMissions as $missionPersonnel) {
                $personnelName = $missionPersonnel->personnel ? $missionPersonnel->personnel->first_name . ' ' . $missionPersonnel->personnel->last_name : 'نامشخص';
                $missionTitle = $missionPersonnel->mission ? $missionPersonnel->mission->title : 'نامشخص';
                $message .= "🆔 ماموریت #{$missionPersonnel->mission_id}\n";
                $message .= "👤 کاربر: {$personnelName}\n";
                $message .= "📋 عنوان: {$missionTitle}\n";
                $message .= "📅 زمان: " . $missionPersonnel->created_at->format('Y-m-d H:i') . "\n\n";
            }
        }

        if ($pendingTasks->isEmpty() && $pendingMissions->isEmpty()) {
            $message .= "✅ هیچ تسک یا ماموریتی در انتظار تایید نیست.";
        }

        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
    }

    /**
     * Handle /today command
     */
    private function handleTodayStats($bot, $type, $groupChatId)
    {
        $today = now()->startOfDay();

        // Task stats
        $tasksApprovedToday = Task::where('task_status', 'approved')
            ->whereDate('approved_at', $today)
            ->count();
        
        $tasksRejectedToday = Task::where('task_status', 'rejected')
            ->whereDate('rejected_at', $today)
            ->count();
        
        $tasksPending = Task::where('task_status', 'pending_approval')->count();

        // Mission stats
        $missionsApprovedToday = MissionPersonnel::where('status', 'approved')
            ->whereDate('approved_at', $today)
            ->count();
        
        $missionsRejectedToday = MissionPersonnel::where('status', 'rejected')
            ->whereDate('rejected_at', $today)
            ->count();
        
        $missionsPending = MissionPersonnel::where('status', 'pending_approval')->count();

        $message = "📊 آمار امروز (" . now()->format('Y-m-d') . "):\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 تسک‌ها:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ تایید شده: {$tasksApprovedToday}\n";
        $message .= "❌ رد شده: {$tasksRejectedToday}\n";
        $message .= "⏳ در انتظار: {$tasksPending}\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 ماموریت‌ها:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ تایید شده: {$missionsApprovedToday}\n";
        $message .= "❌ رد شده: {$missionsRejectedToday}\n";
        $message .= "⏳ در انتظار: {$missionsPending}\n\n";

        $totalApproved = $tasksApprovedToday + $missionsApprovedToday;
        $totalRejected = $tasksRejectedToday + $missionsRejectedToday;
        $totalPending = $tasksPending + $missionsPending;

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 مجموع:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ تایید شده: {$totalApproved}\n";
        $message .= "❌ رد شده: {$totalRejected}\n";
        $message .= "⏳ در انتظار: {$totalPending}";

        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
    }

    /**
     * Handle /top command
     */
    private function handleTopUsers($bot, $type, $groupChatId)
    {
        $today = now()->startOfDay();

        // Top users by approved tasks today
        $topUsersByTasks = Personnel::withCount([
            'tasks' => function($query) use ($today) {
                $query->where('task_status', 'approved')
                      ->whereDate('approved_at', $today);
            }
        ])
        ->having('tasks_count', '>', 0)
        ->orderBy('tasks_count', 'desc')
        ->limit(10)
        ->get();

        // Top users by total points (all time)
        $topUsersByPoints = Personnel::orderBy('total_points', 'desc')
            ->limit(10)
            ->get();

        $message = "🏆 برترین کاربران امروز (" . now()->format('Y-m-d') . "):\n\n";

        if ($topUsersByTasks->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📋 برترین کاربران بر اساس تعداد تسک‌های تایید شده:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $rank = 1;
            foreach ($topUsersByTasks as $personnel) {
                $message .= "{$rank}. {$personnel->first_name} {$personnel->last_name}\n";
                $message .= "   ✅ {$personnel->tasks_count} تسک تایید شده\n";
                $message .= "   ⭐ {$personnel->total_points} امتیاز کل\n\n";
                $rank++;
            }
        }

        if ($topUsersByPoints->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⭐ برترین کاربران بر اساس امتیاز کل:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $rank = 1;
            foreach ($topUsersByPoints->take(10) as $personnel) {
                $message .= "{$rank}. {$personnel->first_name} {$personnel->last_name}\n";
                $message .= "   ⭐ {$personnel->total_points} امتیاز\n";
                $message .= "   🎖️ {$personnel->rank}\n\n";
                $rank++;
            }
        }

        if ($topUsersByTasks->isEmpty() && $topUsersByPoints->isEmpty()) {
            $message .= "📭 هیچ فعالیتی امروز ثبت نشده است.";
        }

        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
    }

    /**
     * Handle /stats command
     */
    private function handleAllStats($bot, $type, $groupChatId)
    {
        $today = now()->startOfDay();

        // Overall stats
        $totalTasks = Task::count();
        $totalMissions = MissionPersonnel::count();
        $tasksApproved = Task::where('task_status', 'approved')->count();
        $tasksRejected = Task::where('task_status', 'rejected')->count();
        $tasksPending = Task::where('task_status', 'pending_approval')->count();
        $missionsApproved = MissionPersonnel::where('status', 'approved')->count();
        $missionsRejected = MissionPersonnel::where('status', 'rejected')->count();
        $missionsPending = MissionPersonnel::where('status', 'pending_approval')->count();

        // Today's stats
        $tasksApprovedToday = Task::where('task_status', 'approved')
            ->whereDate('approved_at', $today)
            ->count();
        $tasksRejectedToday = Task::where('task_status', 'rejected')
            ->whereDate('rejected_at', $today)
            ->count();
        $missionsApprovedToday = MissionPersonnel::where('status', 'approved')
            ->whereDate('approved_at', $today)
            ->count();
        $missionsRejectedToday = MissionPersonnel::where('status', 'rejected')
            ->whereDate('rejected_at', $today)
            ->count();

        // Top users
        $topUsers = Personnel::orderBy('total_points', 'desc')
            ->limit(5)
            ->get();

        $message = "📊 آمار کامل سیستم:\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 آمار کلی:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📝 کل تسک‌ها: {$totalTasks}\n";
        $message .= "   ✅ تایید شده: {$tasksApproved}\n";
        $message .= "   ❌ رد شده: {$tasksRejected}\n";
        $message .= "   ⏳ در انتظار: {$tasksPending}\n\n";
        $message .= "📝 کل ماموریت‌ها: {$totalMissions}\n";
        $message .= "   ✅ تایید شده: {$missionsApproved}\n";
        $message .= "   ❌ رد شده: {$missionsRejected}\n";
        $message .= "   ⏳ در انتظار: {$missionsPending}\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📅 آمار امروز (" . now()->format('Y-m-d') . "):\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ تسک‌های تایید شده: {$tasksApprovedToday}\n";
        $message .= "❌ تسک‌های رد شده: {$tasksRejectedToday}\n";
        $message .= "✅ ماموریت‌های تایید شده: {$missionsApprovedToday}\n";
        $message .= "❌ ماموریت‌های رد شده: {$missionsRejectedToday}\n\n";

        if ($topUsers->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🏆 برترین کاربران (امتیاز کل):\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $rank = 1;
            foreach ($topUsers as $personnel) {
                $message .= "{$rank}. {$personnel->first_name} {$personnel->last_name}\n";
                $message .= "   ⭐ {$personnel->total_points} امتیاز\n";
                $message .= "   🎖️ {$personnel->rank}\n\n";
                $rank++;
            }
        }

        BotHelper::sendMessageByChatId($bot, $groupChatId, $message);
    }

    /**
     * Handle callback query (inline button clicks)
     */
    private function handleCallbackQuery($bot, array $callbackQuery, string $type): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $userId = $callbackQuery['from']['id'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;

        Log::info('🔘 Task Approval Bot - Callback query received', [
            'callback_data' => $callbackData,
            'user_id' => $userId,
            'message_id' => $messageId,
            'chat_id' => $chatId
        ]);

        // Check if this is the approval group
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if ($chatId != $approvalGroupChatId) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'این دستور فقط در گروه approval کار می‌کند', $type);
            return;
        }

        // Handle approve button clicks
        if (str_starts_with($callbackData, 'approve_task_')) {
            $taskId = (int) str_replace('approve_task_', '', $callbackData);
            $task = Task::where('id', $taskId)
                ->where('task_status', 'pending_approval')
                ->first();

            if ($task) {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال تایید...', $type);
                $this->approveTask($bot, $task, $userId, $type);
            } else {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'تسک یافت نشد یا قبلاً تایید/رد شده است', $type);
            }
        } elseif (str_starts_with($callbackData, 'approve_mission_')) {
            $missionPersonnelId = (int) str_replace('approve_mission_', '', $callbackData);
            $missionPersonnel = MissionPersonnel::where('id', $missionPersonnelId)
                ->where('status', 'pending_approval')
                ->first();

            if ($missionPersonnel) {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال تایید...', $type);
                $this->approveMission($bot, $missionPersonnel, $userId, $type);
            } else {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'ماموریت یافت نشد یا قبلاً تایید/رد شده است', $type);
            }
        } else {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'دستور نامعتبر است', $type);
        }
    }

    /**
     * Handle /approve {id} command
     */
    private function handleApproveCommand($bot, int $id, $userId, string $type, $chatId): void
    {
        // Try to find task first
        $task = Task::where('id', $id)
            ->where('task_status', 'pending_approval')
            ->first();

        if ($task) {
            $this->approveTask($bot, $task, $userId, $type);
            BotHelper::sendMessageByChatId($bot, $chatId, "✅ تسک #{$id} تایید شد.");
            return;
        }

        // Try to find mission
        $missionPersonnel = MissionPersonnel::where('id', $id)
            ->where('status', 'pending_approval')
            ->first();

        if ($missionPersonnel) {
            $this->approveMission($bot, $missionPersonnel, $userId, $type);
            BotHelper::sendMessageByChatId($bot, $chatId, "✅ ماموریت #{$id} تایید شد.");
            return;
        }

        BotHelper::sendMessageByChatId($bot, $chatId, "❌ تسک یا ماموریت با شناسه #{$id} یافت نشد یا قبلاً تایید/رد شده است.");
    }

    /**
     * Normalize command by removing @bot_username if present
     */
    private function normalizeCommand(string $command, $bot, string $type): string
    {
        // Check if command contains @ (bot mention)
        if (!str_contains($command, '@')) {
            return trim($command);
        }
        
        // Get bot username from cache or getMe
        // Use token as cache key for better uniqueness
        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $cacheKey = $type . '_' . substr($token, 0, 10);
        
        if (!isset(self::$botUsernameCache[$cacheKey])) {
            try {
                $getMe = $bot->getMe();
                if ($getMe && isset($getMe['ok']) && $getMe['ok'] && isset($getMe['result']['username'])) {
                    self::$botUsernameCache[$cacheKey] = $getMe['result']['username'];
                } else {
                    // Fallback: extract from command pattern
                    if (preg_match('/@(\w+)$/', $command, $matches)) {
                        self::$botUsernameCache[$cacheKey] = $matches[1];
                    } else {
                        self::$botUsernameCache[$cacheKey] = null;
                    }
                }
            } catch (Exception $e) {
                Log::warning('⚠️ Task Approval Bot - Could not get bot username, trying fallback', [
                    'error' => $e->getMessage()
                ]);
                // Fallback: extract from command pattern
                if (preg_match('/@(\w+)$/', $command, $matches)) {
                    self::$botUsernameCache[$cacheKey] = $matches[1];
                } else {
                    self::$botUsernameCache[$cacheKey] = null;
                }
            }
        }
        
        $botUsername = self::$botUsernameCache[$cacheKey];
        
        if ($botUsername) {
            // Remove @bot_username from command if present
            $pattern = '/@' . preg_quote($botUsername, '/') . '$/';
            $normalized = preg_replace($pattern, '', $command);
            
            Log::info('📝 Task Approval Bot - Command normalized', [
                'original' => $command,
                'bot_username' => $botUsername,
                'normalized' => $normalized
            ]);
            
            return trim($normalized);
        }
        
        // Fallback: remove any @username pattern
        $normalized = preg_replace('/@\w+$/', '', $command);
        return trim($normalized);
    }

    /**
     * Answer callback query
     */
    private function answerCallbackQuery($bot, ?string $callbackQueryId, string $text, string $type): void
    {
        if (!$callbackQueryId) {
            return;
        }

        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        
        if ($type == 'bale') {
            $url = "https://tapi.bale.ai/bot{$token}/answerCallbackQuery";
        } else {
            $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
        }

        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }
}
