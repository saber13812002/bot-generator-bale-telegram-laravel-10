<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\ContentService;
use App\Interfaces\Services\MissionService;
use App\Models\BotUsers;
use App\Models\Mission;
use App\Models\Personnel;
use App\Models\Task;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram;

class MissionBotController extends Controller
{
    private MissionService $missionService;
    private ContentService $contentService;

    public function __construct(
        MissionService $missionService,
        ContentService $contentService
    ) {
        $this->missionService = $missionService;
        $this->contentService = $contentService;
    }
    /**
     * Handle mission bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Mission Bot - Webhook received', [
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
            Log::info('📡 Mission Bot - Webhook status check', [
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
            
            // Detect chat info (group/private and start status)
            $chatInfo = BotHelper::detectChatInfo($bot);
            $isGroup = $chatInfo['is_group'];
            
            // Log message received and chat info
            Log::info('📨 Mission Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type,
                'is_group' => $isGroup,
                'chat_type' => $chatInfo['chat_type'],
                'is_started' => $chatInfo['is_started']
            ]);
            
            if ($isGroup) {
                // Handle group messages according to algorithm
                Log::info('👥 Mission Bot - Processing group message', ['chat_id' => $chatId, 'text' => $text]);
                $this->handleGroupMessage($bot, $text, $chatId, $type, $botMotherId);
                Log::info('✅ Mission Bot - Group message processed', ['chat_id' => $chatId]);
                return;
            }

            // Handle /start command with personnel_id parameter
            if ($text == '/start' || str_starts_with($text, '/start ')) {
                Log::info('▶️ Mission Bot - Processing /start command', ['chat_id' => $chatId, 'text' => $text]);
                $this->handleStart($bot, $text, $type, $botMotherId);
            } 
            // Handle task submission (final link)
            else if (filter_var($text, FILTER_VALIDATE_URL)) {
                Log::info('🔗 Mission Bot - Processing URL submission', ['chat_id' => $chatId, 'url' => $text]);
                $this->handleSubmitLink($bot, $text, $chatId, $type);
            }
            // Handle other commands
            else {
                // Check if user has personnel_id in settings
                $botUser = BotUsers::where('chat_id', $chatId)
                    ->where('origin', $type)
                    ->first();
                
                if ($botUser) {
                    $personnelId = $botUser->setting('personnel_id');
                    Log::info('👤 Mission Bot - User found', [
                        'chat_id' => $chatId,
                        'has_personnel_id' => !empty($personnelId),
                        'personnel_id' => $personnelId
                    ]);
                    
                    if ($personnelId) {
                        Log::info('⚙️ Mission Bot - Processing command', ['chat_id' => $chatId, 'text' => $text, 'personnel_id' => $personnelId]);
                        $this->handleCommands($bot, $text, $personnelId, $type);
                    } else {
                        Log::warning('⚠️ Mission Bot - User not registered', ['chat_id' => $chatId]);
                        BotHelper::sendMessage($bot, "شما ثبت‌نام نکرده‌اید. لطفا ابتدا در ربات ثبت‌نام، ثبت‌نام خود را تکمیل کنید.");
                    }
                } else {
                    Log::warning('⚠️ Mission Bot - User not found', ['chat_id' => $chatId]);
                    BotHelper::sendMessage($bot, "شما ثبت‌نام نکرده‌اید. لطفا ابتدا در ربات ثبت‌نام، ثبت‌نام خود را تکمیل کنید.");
                }
            }
            
            Log::info('✅ Mission Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId ?? null,
                'text' => $text ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    /**
     * Handle start command with personnel_id
     */
    private function handleStart($bot, $text, $type, $botMotherId)
    {
        $personnelId = null;

        // Extract personnel_id from /start command
        if (str_starts_with($text, '/start ')) {
            // Handle format: /start?personnel_id=1 or /start personnel_id=1
            if (str_contains($text, '?')) {
                [$command, $params] = BotHelper::getCommandRefferralWhenStart($text, '?');
            } else {
                [$command, $params] = BotHelper::getCommandRefferralWhenStart($text, '=');
            }
            if (isset($params) && isset($params['personnel_id'])) {
                $personnelId = $params['personnel_id'];
            }
        }

        // If no personnel_id in command, check if user is already registered
        if (!$personnelId) {
            $botUser = BotUsers::where('chat_id', $bot->ChatID())
                ->where('origin', $type)
                ->first();
            
            if ($botUser) {
                $personnelId = $botUser->setting('personnel_id');
            }
        }

        if (!$personnelId) {
            // سلام اولیه برای اطمینان از کارکرد ربات
            BotHelper::sendMessage($bot, "👋 سلام! ربات ماموریت آماده است.");
            BotHelper::sendMessage($bot, "شما ثبت‌نام نکرده‌اید. لطفا ابتدا در ربات ثبت‌نام، ثبت‌نام خود را تکمیل کنید.");
            return;
        }

        // Verify personnel exists
        $personnel = Personnel::find($personnelId);
        if (!$personnel) {
            BotHelper::sendMessage($bot, "کاربری با این شناسه یافت نشد.");
            return;
        }

        // Save personnel_id to bot user settings
        $botUser = BotUsers::firstOrNew($bot->ChatID(), $botMotherId, $type);
        $botUser->settings(['personnel_id' => $personnelId]);

        // سلام اولیه برای اطمینان از کارکرد ربات
        BotHelper::sendMessage($bot, "👋 سلام! ربات ماموریت آماده است.");

        // Welcome message
        $message = "سلام " . $personnel->first_name . " " . $personnel->last_name . "!\n\n";
        $message .= "به ربات ماموریت خوش آمدید.\n";
        $message .= "درجه فعلی شما: " . $personnel->rank . "\n";
        $message .= "امتیاز کل شما: " . $personnel->total_points . "\n\n";
        $message .= "برای رزرو یک تسک، دستور /reserve را ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle commands
     */
    private function handleCommands($bot, $text, $personnelId, $type)
    {
        $personnel = Personnel::find($personnelId);
        if (!$personnel) {
            BotHelper::sendMessage($bot, "کاربری یافت نشد.");
            return;
        }

        if ($text == '/reserve') {
            $this->handleReserveTask($bot, $personnel, $type);
        } elseif ($text == '/request_mission' || $text == '/request') {
            $this->handleRequestMission($bot, $personnel, $type);
        } elseif ($text == '/cancel_mission' || $text == '/cancel') {
            $this->handleCancelMission($bot, $personnel, $type);
        } elseif ($text == '/get_training' || $text == '/training') {
            $this->handleGetTraining($bot, $personnel, $type);
        } elseif ($text == '/status') {
            $this->handleTaskStatus($bot, $personnel);
        } elseif ($text == '/help') {
            $this->handleHelp($bot);
        } else {
            BotHelper::sendMessage($bot, "دستور نامعتبر است. از /help برای مشاهده دستورات استفاده کنید.");
        }
    }

    /**
     * Handle task reservation
     */
    private function handleReserveTask($bot, $personnel, $type)
    {
        Log::info('📋 Mission Bot - Reserve task request', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);

        // Check if user has an active task (only reserved, in_progress, or pending_approval)
        // Note: approved and rejected tasks are not considered active
        $activeTask = Task::where('assigned_user_id', $personnel->id)
            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
            ->where(function($query) {
                // Either reserved_time is in the future, or task is pending_approval (no time limit)
                $query->where('reserved_time', '>', now())
                      ->orWhere('task_status', 'pending_approval');
            })
            ->first();

        if ($activeTask) {
            Log::info('⚠️ Mission Bot - Personnel has active task', [
                'personnel_id' => $personnel->id,
                'task_id' => $activeTask->id,
                'task_status' => $activeTask->task_status
            ]);

            // Load prompts for the task
            $activeTask->load('prompts');

            $message = "شما یک تسک فعال دارید:\n\n";
            $message .= "📋 نام تسک: " . $activeTask->task_name . "\n";
            $message .= "📊 وضعیت: " . $this->getStatusText($activeTask->task_status) . "\n";
            
            if ($activeTask->reserved_time) {
                $message .= "⏰ زمان باقیمانده: " . $activeTask->reserved_time->diffForHumans() . "\n";
            }
            
            $message .= "🎯 امتیاز: " . $activeTask->points . "\n\n";
            
            // Add prompt content if available
            if ($activeTask->prompts->isNotEmpty()) {
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "📝 پرامپت:\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                
                foreach ($activeTask->prompts as $index => $prompt) {
                    if ($activeTask->prompts->count() > 1) {
                        $message .= "پرامپت " . ($index + 1) . ":\n";
                    }
                    $message .= $prompt->content . "\n\n";
                }
            }
            
            // Add task name as text content for copying
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📄 متن تسک (برای کپی):\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= $activeTask->task_name . "\n\n";
            
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💡 لطفا تسک فعلی را تکمیل کنید یا منتظر بمانید تا تسک فعلی تایید یا رد شود.";
            
            BotHelper::sendMessage($bot, $message);
            return;
        }

        // Check if there are available tasks to assign
        // TODO: In the future, this should check a task pool or configuration table
        // For now, we'll check if we can create a task
        // You should implement proper task availability logic based on your business requirements
        
        // For now, we'll create a simple task
        // In production, you should check a task pool/configuration to see if tasks are available
        $reservedTime = now()->addHours(2);

        try {
            $task = Task::create([
                'task_name' => 'تسک نمونه ' . now()->format('Y-m-d H:i'),
                'assigned_user_id' => $personnel->id,
                'task_status' => 'reserved',
                'reserved_time' => $reservedTime,
                'assigned_time' => now(),
                'points' => 10, // Default points, should come from task configuration
            ]);

            $message = "✅ تسک شما با موفقیت رزرو شد!\n\n";
            $message .= "نام تسک: " . $task->task_name . "\n";
            $message .= "امتیاز: " . $task->points . "\n";
            $message .= "مهلت ارسال: " . $reservedTime->format('Y-m-d H:i') . "\n";
            $message .= "زمان باقیمانده: " . $reservedTime->diffForHumans() . "\n\n";
            $message .= "لطفا پس از انجام تسک، لینک نهایی را در این ربات ارسال کنید.";

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('Error creating task: ' . $e->getMessage());
            // If task creation fails, inform user that no tasks are available
            BotHelper::sendMessage($bot, "در حال حاضر تسک موجود نیست. لطفا بعداً تلاش کنید.");
        }
    }
    
    /**
     * Handle group messages
     * This method handles messages in group chats according to the algorithm
     */
    private function handleGroupMessage($bot, $text, $chatId, $type, $botMotherId)
    {
        // Check if this is the approval group
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if ($chatId == $approvalGroupChatId) {
            // This is handled by TaskApprovalController
            // We can ignore it here or handle additional group logic
            return;
        }
        
        // Handle group messages according to algorithm
        // For now, we'll check if it's a command or URL
        if ($text == '/start' || str_starts_with($text, '/start ')) {
            // In group, we might want to respond differently
            // For now, we'll just ignore or send a message that bot is active
            return;
        }
        
        // If it's a URL, check if user is registered and has active task
        if (filter_var($text, FILTER_VALIDATE_URL)) {
            // Get user from message (if available in update)
            $request = request();
            $update = $request->json()->all() ?? $request->all();
            $userId = null;
            
            if (isset($update['message']['from']['id'])) {
                $userId = $update['message']['from']['id'];
            }
            
            if ($userId) {
                // Find bot user by user_id (not chat_id, as chat_id is group)
                // Note: In groups, we need to track user_id separately
                // For now, we'll try to find by user_id if available
                $botUser = BotUsers::where('chat_id', $userId)
                    ->where('origin', $type)
                    ->first();
                
                if ($botUser) {
                    $personnelId = $botUser->setting('personnel_id');
                    if ($personnelId) {
                        // Check if user has active task (reserved, in_progress, or pending_approval)
                        $activeTask = Task::where('assigned_user_id', $personnelId)
                            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
                            ->where(function($query) {
                                // Either reserved_time is in the future, or task is pending_approval (no time limit)
                                $query->where('reserved_time', '>', now())
                                      ->orWhere('task_status', 'pending_approval');
                            })
                            ->latest()
                            ->first();
                        
                        if ($activeTask) {
                            // Handle link submission in group
                            $this->handleSubmitLinkInGroup($bot, $text, $userId, $personnelId, $type, $chatId);
                        } else {
                            BotHelper::sendMessage($bot, "شما تسک فعالی ندارید.");
                        }
                    } else {
                        BotHelper::sendMessage($bot, "شما ثبت‌نام نکرده‌اید. لطفا ابتدا در ربات ثبت‌نام، ثبت‌نام خود را تکمیل کنید.");
                    }
                } else {
                    BotHelper::sendMessage($bot, "شما ثبت‌نام نکرده‌اید. لطفا ابتدا در ربات ثبت‌نام، ثبت‌نام خود را تکمیل کنید.");
                }
            }
        }
    }
    
    /**
     * Handle link submission in group
     */
    private function handleSubmitLinkInGroup($bot, $link, $userId, $personnelId, $type, $groupChatId)
    {
        // Find active task (reserved, in_progress, or pending_approval)
        $task = Task::where('assigned_user_id', $personnelId)
            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
            ->where(function($query) {
                // Either reserved_time is in the future, or task is pending_approval (no time limit)
                $query->where('reserved_time', '>', now())
                      ->orWhere('task_status', 'pending_approval');
            })
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک فعالی ندارید.");
            return;
        }

        // If task is already pending_approval, don't allow resubmission
        if ($task->task_status === 'pending_approval') {
            BotHelper::sendMessage($bot, "تسک شما در حال بررسی است. لطفا منتظر نتیجه تایید باشید.");
            return;
        }

        // Check if reserved time has passed (only for reserved/in_progress tasks)
        if ($task->reserved_time && now() > $task->reserved_time) {
            $task->update(['task_status' => 'rejected', 'rejected_at' => now()]);
            BotHelper::sendMessage($bot, "متاسفانه زمان رزرو تسک به پایان رسیده است.");
            return;
        }

        // Update task with final link
        $task->update([
            'final_link' => $link,
            'task_status' => 'pending_approval',
            'task_time' => now(),
        ]);

        // Send to approval group
        $this->sendToApprovalGroup($task, $type);

        $message = "✅ لینک شما با موفقیت ثبت شد!\n\n";
        $message .= "تسک شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle final link submission
     */
    private function handleSubmitLink($bot, $link, $chatId, $type)
    {
        // Find bot user
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->first();

        if (!$botUser) {
            BotHelper::sendMessage($bot, "لطفا ابتدا از لینک اختصاصی خود استفاده کنید.");
            return;
        }

        $personnelId = $botUser->setting('personnel_id');
        if (!$personnelId) {
            BotHelper::sendMessage($bot, "لطفا ابتدا از لینک اختصاصی خود استفاده کنید.");
            return;
        }

        // Try to submit for mission first
        try {
            $result = $this->missionService->submitResult($personnelId, $link);
            if ($result) {
                // Find mission to send to approval group
                $missionPersonnel = \App\Models\MissionPersonnel::where('personnel_id', $personnelId)
                    ->where('status', 'pending_approval')
                    ->where('result_link', $link)
                    ->latest()
                    ->first();

                if ($missionPersonnel) {
                    $this->sendMissionToApprovalGroup($missionPersonnel->mission, $missionPersonnel, $type);
                    BotHelper::sendMessage($bot, "✅ لینک شما با موفقیت ثبت شد!\n\nماموریت شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.");
                    return;
                }
            }
        } catch (Exception $e) {
            Log::info('Mission submission failed, trying task', ['error' => $e->getMessage()]);
        }

        // Fallback to task submission
        $task = Task::where('assigned_user_id', $personnelId)
            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
            ->where(function($query) {
                // Either reserved_time is in the future, or task is pending_approval (no time limit)
                $query->where('reserved_time', '>', now())
                      ->orWhere('task_status', 'pending_approval');
            })
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک یا ماموریت فعالی ندارید.");
            return;
        }

        // If task is already pending_approval, don't allow resubmission
        if ($task->task_status === 'pending_approval') {
            BotHelper::sendMessage($bot, "تسک شما در حال بررسی است. لطفا منتظر نتیجه تایید باشید.");
            return;
        }

        // Check if reserved time has passed (only for reserved/in_progress tasks)
        if ($task->reserved_time && now() > $task->reserved_time) {
            $task->update(['task_status' => 'rejected', 'rejected_at' => now()]);
            BotHelper::sendMessage($bot, "متاسفانه زمان رزرو تسک به پایان رسیده است.");
            return;
        }

        // Update task with final link
        $task->update([
            'final_link' => $link,
            'task_status' => 'pending_approval',
            'task_time' => now(),
        ]);

        // Send to approval group
        $this->sendToApprovalGroup($task, $type);

        $message = "✅ لینک شما با موفقیت ثبت شد!\n\n";
        $message .= "تسک شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Send mission to approval group.
     */
    private function sendMissionToApprovalGroup($mission, $missionPersonnel, $type)
    {
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if (!$approvalGroupChatId) {
            Log::warning('MISSION_APPROVAL_GROUP_CHAT_ID not set');
            return;
        }

        $personnel = $missionPersonnel->personnel;
        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $bot = new Telegram($token, $type);

        $message = "📋 ماموریت جدید برای تایید:\n\n";
        $message .= "شناسه ماموریت: " . $mission->id . "\n";
        $message .= "عنوان: " . $mission->title . "\n";
        $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "کد ملی: " . $personnel->national_code . "\n";
        $message .= "امتیاز: " . $mission->points . "\n";
        $message .= "لینک: " . $missionPersonnel->result_link . "\n\n";
        $message .= "برای تایید، کلمه 'تایید' را به این پیام reply کنید.\n";
        $message .= "برای رد، پیام خود را به این پیام reply کنید.";

        $result = BotHelper::sendMessageByChatId($bot, $approvalGroupChatId, $message);
        
        // Save message_id to mission_personnel for future reference
        if ($result && isset($result['result']['message_id'])) {
            $missionPersonnel->update(['approval_message_id' => $result['result']['message_id']]);
        }
    }

    /**
     * Send task to approval group
     */
    private function sendToApprovalGroup($task, $type)
    {
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if (!$approvalGroupChatId) {
            Log::warning('MISSION_APPROVAL_GROUP_CHAT_ID not set');
            return;
        }

        $personnel = $task->personnel;
        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $bot = new Telegram($token, $type);

        $message = "📋 تسک جدید برای تایید:\n\n";
        $message .= "شناسه تسک: " . $task->id . "\n";
        $message .= "نام تسک: " . $task->task_name . "\n";
        $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "کد ملی: " . $personnel->national_code . "\n";
        $message .= "امتیاز: " . $task->points . "\n";
        $message .= "لینک: " . $task->final_link . "\n\n";
        $message .= "برای تایید، کلمه 'تایید' را به این پیام reply کنید.\n";
        $message .= "برای رد، پیام خود را به این پیام reply کنید.";

        $result = BotHelper::sendMessageByChatId($bot, $approvalGroupChatId, $message);
        
        // Save message_id to task for future reference
        if ($result && isset($result['result']['message_id'])) {
            $task->update(['approval_message_id' => $result['result']['message_id']]);
        }
    }

    /**
     * Handle task status check
     */
    private function handleTaskStatus($bot, $personnel)
    {
        $tasks = Task::where('assigned_user_id', $personnel->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($tasks->isEmpty()) {
            BotHelper::sendMessage($bot, "شما هنوز تسکی ندارید.");
            return;
        }

        $message = "📊 وضعیت تسک‌های شما:\n\n";
        foreach ($tasks as $task) {
            $message .= "• " . $task->task_name . "\n";
            $message .= "  وضعیت: " . $this->getStatusText($task->task_status) . "\n";
            $message .= "  امتیاز: " . $task->points . "\n";
            if ($task->reserved_time) {
                $message .= "  زمان رزرو: " . $task->reserved_time->format('Y-m-d H:i') . "\n";
            }
            $message .= "\n";
        }

        $message .= "امتیاز کل شما: " . $personnel->total_points . "\n";
        $message .= "درجه فعلی: " . $personnel->rank;

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle request mission command
     */
    private function handleRequestMission($bot, $personnel, $type)
    {
        Log::info('📋 Mission Bot - Request mission', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);

        try {
            // Check if personnel has an active mission
            $activeMission = \App\Models\MissionPersonnel::where('personnel_id', $personnel->id)
                ->whereIn('status', ['reserved', 'in_progress', 'pending_approval'])
                ->latest()
                ->first();

            if ($activeMission) {
                Log::info('⚠️ Mission Bot - Personnel has active mission', [
                    'personnel_id' => $personnel->id,
                    'mission_personnel_id' => $activeMission->id,
                    'mission_id' => $activeMission->mission_id,
                    'status' => $activeMission->status
                ]);

                $mission = $activeMission->mission;
                $message = "شما یک ماموریت فعال دارید:\n";
                $message .= "عنوان ماموریت: " . $mission->title . "\n";
                $message .= "وضعیت: " . $this->getMissionStatusText($activeMission->status) . "\n\n";
                $message .= "لطفا ماموریت فعلی را تکمیل کنید یا آن را لغو کنید.";
                BotHelper::sendMessage($bot, $message);
                return;
            }

            // Request mission
            $mission = $this->missionService->requestMission($personnel->id, 'random');

            if (!$mission) {
                Log::warning('❌ Mission Bot - No mission available', [
                    'personnel_id' => $personnel->id
                ]);
                BotHelper::sendMessage($bot, "❌ در حال حاضر ماموریت در دسترس نیست.\n\nلطفا بعداً تلاش کنید.");
                return;
            }

            Log::info('✅ Mission Bot - Mission requested successfully', [
                'personnel_id' => $personnel->id,
                'mission_id' => $mission->id
            ]);

            $message = "✅ ماموریت شما با موفقیت اختصاص یافت!\n\n";
            $message .= "عنوان ماموریت: " . $mission->title . "\n";
            $message .= "توضیحات: " . $mission->description . "\n";
            $message .= "امتیاز: " . $mission->points . "\n\n";
            $message .= "برای دریافت آموزش‌ها، دستور /get_training را ارسال کنید.\n";
            $message .= "برای لغو ماموریت، دستور /cancel_mission را ارسال کنید.";

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error requesting mission', [
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            BotHelper::sendMessage($bot, "❌ خطا در درخواست ماموریت: " . $e->getMessage());
        }
    }

    /**
     * Handle cancel mission command
     */
    private function handleCancelMission($bot, $personnel, $type)
    {
        Log::info('🚫 Mission Bot - Cancel mission request', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);

        try {
            // Find active mission
            $activeMission = \App\Models\MissionPersonnel::where('personnel_id', $personnel->id)
                ->whereIn('status', ['reserved', 'in_progress'])
                ->latest()
                ->first();

            if (!$activeMission) {
                Log::info('⚠️ Mission Bot - No active mission to cancel', [
                    'personnel_id' => $personnel->id
                ]);
                BotHelper::sendMessage($bot, "❌ شما ماموریت فعالی برای لغو ندارید.");
                return;
            }

            // Cancel mission
            $result = $this->missionService->cancelMission($personnel->id, $activeMission->mission_id);

            if (!$result) {
                Log::warning('❌ Mission Bot - Failed to cancel mission', [
                    'personnel_id' => $personnel->id,
                    'mission_id' => $activeMission->mission_id
                ]);
                BotHelper::sendMessage($bot, "❌ خطا در لغو ماموریت. لطفا دوباره تلاش کنید.");
                return;
            }

            // Refresh to get updated status
            $activeMission->refresh();
            $mission = $activeMission->mission;

            Log::info('✅ Mission Bot - Mission cancelled successfully', [
                'personnel_id' => $personnel->id,
                'mission_id' => $mission->id,
                'mission_personnel_id' => $activeMission->id,
                'new_status' => $activeMission->status
            ]);

            $message = "✅ ماموریت شما با موفقیت لغو شد.\n\n";
            $message .= "عنوان ماموریت: " . $mission->title . "\n";
            $message .= "وضعیت جدید: " . $this->getMissionStatusText($activeMission->status) . "\n\n";
            $message .= "اکنون می‌توانید ماموریت جدیدی درخواست کنید.";

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error cancelling mission', [
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            BotHelper::sendMessage($bot, "❌ خطا در لغو ماموریت: " . $e->getMessage());
        }
    }

    /**
     * Get mission status text in Persian
     */
    private function getMissionStatusText($status): string
    {
        return match($status) {
            'reserved' => 'رزرو شده',
            'in_progress' => 'در حال انجام',
            'pending_approval' => 'در انتظار تایید',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'cancelled' => 'لغو شده',
            default => $status,
        };
    }

    /**
     * Handle get training command - sends training content for active mission
     */
    private function handleGetTraining($bot, $personnel, $type)
    {
        Log::info('📥 Mission Bot - Get training request', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);

        // Find active mission for this personnel
        $missionPersonnel = \App\Models\MissionPersonnel::where('personnel_id', $personnel->id)
            ->whereIn('status', ['reserved', 'in_progress'])
            ->latest()
            ->first();

        if (!$missionPersonnel) {
            BotHelper::sendMessage($bot, "❌ شما ماموریت فعالی ندارید.\n\nبرای درخواست ماموریت، دستور /request_mission را ارسال کنید.");
            return;
        }

        $mission = $missionPersonnel->mission;
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
            return;
        }

        try {
            // Use ContentService to send training media
            $this->contentService->sendTrainingMedia($mission->id, $personnel->id, $type);
            
            BotHelper::sendMessage($bot, "✅ آموزش‌های ماموریت در حال ارسال هستند...\n\n");
            BotHelper::sendMessage($bot, "📋 ماموریت: {$mission->title}\n");
            BotHelper::sendMessage($bot, "⏳ لطفا منتظر بمانید تا تمام محتواها ارسال شوند.");
            
            Log::info('✅ Mission Bot - Training request processed', [
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Mission Bot - Error sending training', [
                'error' => $e->getMessage(),
                'mission_id' => $mission->id ?? null,
                'personnel_id' => $personnel->id
            ]);
            BotHelper::sendMessage($bot, "❌ خطا در ارسال آموزش‌ها: " . $e->getMessage());
        }
    }

    /**
     * Handle help command
     */
    private function handleHelp($bot)
    {
        $message = "📖 دستورات ربات ماموریت:\n\n";
        $message .= "/reserve - رزرو یک تسک جدید\n";
        $message .= "/request_mission - درخواست یک ماموریت\n";
        $message .= "/get_training - دریافت آموزش‌های ماموریت فعال\n";
        $message .= "/cancel_mission - لغو ماموریت فعال\n";
        $message .= "/status - مشاهده وضعیت تسک‌ها و ماموریت‌ها\n";
        $message .= "/help - نمایش این راهنما\n\n";
        $message .= "💡 برای ارسال لینک نهایی، فقط لینک را در ربات ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Get status text in Persian
     */
    private function getStatusText($status): string
    {
        return match($status) {
            'reserved' => 'رزرو شده',
            'in_progress' => 'در حال انجام',
            'pending_approval' => 'در انتظار تایید',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            default => $status,
        };
    }
}
