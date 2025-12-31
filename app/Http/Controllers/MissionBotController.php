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

            // Check for callback query (inline button clicks)
            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $callbackData = $update['callback_query']['data'] ?? '';
                
                // Check if this is an approval callback (should be handled by TaskApprovalController)
                if (str_starts_with($callbackData, 'approve_task_') || str_starts_with($callbackData, 'approve_mission_')) {
                    // Handle approval callbacks directly here
                    $this->handleApprovalCallbackQuery($bot, $update['callback_query'], $type);
                    return;
                }
                
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botMotherId);
                return;
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

        // Welcome message with inline buttons
        $message = "سلام " . $personnel->first_name . " " . $personnel->last_name . "!\n\n";
        $message .= "به ربات ماموریت خوش آمدید.\n";
        $message .= "درجه فعلی شما: " . $personnel->rank . "\n";
        $message .= "امتیاز کل شما: " . $personnel->total_points . "\n\n";
        $message .= "لطفا یکی از گزینه‌های زیر را انتخاب کنید:";

        // Create inline keyboard with buttons
        $option = [
            array($bot->buildInlineKeyBoardButton('🎲 ماموریت رندوم', callback_data: 'mission_random')),
            array($bot->buildInlineKeyBoardButton('🔍 انتخاب نوع ماموریت', callback_data: 'mission_filter'))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
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
        } elseif ($text == '/details') {
            $this->handleTaskDetails($bot, $personnel, $type);
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
            $message .= "📌 راهنمای انجام تسک:\n\n";
            $message .= "1️⃣ تسک را انجام دهید (مثلاً یک پست در وبلاگ، ویرگول، لینکدین، توییتر، مدیوم، یوتیوب و...)\n\n";
            $message .= "2️⃣ لینک نتیجه کار خود را در این ربات ارسال کنید\n";
            $message .= "   مثال: https://virgool.io/@username/post\n";
            $message .= "   یا: https://www.linkedin.com/posts/...\n";
            $message .= "   یا: https://twitter.com/username/status/...\n";
            $message .= "   یا: https://medium.com/@username/...\n";
            $message .= "   یا: https://youtube.com/watch?v=...\n\n";
            $message .= "3️⃣ منتظر تایید بمانید\n\n";
            $message .= "💡 توجه: لینک باید دال و دلیل انجام تسک شما باشد (مثلاً لینک پست وبلاگ، مقاله، ویدیو و...)\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⚠️ تا زمانی که تسک فعلی تایید یا رد نشود، نمی‌توانید تسک جدیدی رزرو کنید.";
            
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

            // Load prompts for the task
            $task->load('prompts');

            // Send complete task details with inline keyboard
            $this->sendTaskDetailsMessage($bot, $task, $reservedTime, true);
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
        // Find active task (reserved, in_progress, pending_approval) or rejected tasks (for resubmission)
        $task = Task::where('assigned_user_id', $personnelId)
            ->where(function($query) {
                // Active tasks
                $query->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
                      ->where(function($q) {
                          $q->where('reserved_time', '>', now())
                            ->orWhere('task_status', 'pending_approval');
                      })
                      // Or rejected tasks (for resubmission)
                      ->orWhere(function($q) {
                          $q->where('task_status', 'rejected')
                            ->whereNotNull('rejection_reason');
                      });
            })
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک فعالی ندارید.");
            return;
        }

        // If task is already pending_approval and not rejected, don't allow resubmission
        if ($task->task_status === 'pending_approval' && !$task->rejection_reason) {
            BotHelper::sendMessage($bot, "تسک شما در حال بررسی است. لطفا منتظر نتیجه تایید باشید.");
            return;
        }

        // Check if reserved time has passed (only for reserved/in_progress tasks, not for rejected tasks being resubmitted)
        if ($task->task_status !== 'rejected' && $task->reserved_time && now() > $task->reserved_time) {
            $task->update(['task_status' => 'rejected', 'rejected_at' => now()]);
            BotHelper::sendMessage($bot, "متاسفانه زمان رزرو تسک به پایان رسیده است.");
            return;
        }

        // Check if this is a resubmission after rejection
        $isResubmission = $task->task_status === 'rejected' && $task->rejection_reason;
        
        // Update task with final link
        $updateData = [
            'final_link' => $link,
            'task_status' => 'pending_approval',
            'task_time' => now(),
        ];
        
        // If this is a resubmission, clear rejection fields
        if ($isResubmission) {
            $updateData['rejection_reason'] = null;
            $updateData['rejected_at'] = null;
            $updateData['approved_by_chat_id'] = null;
        }
        
        $task->update($updateData);

        // Send to approval group
        $this->sendToApprovalGroup($task, $type, $isResubmission);

        $message = "✅ لینک شما با موفقیت ثبت شد!\n\n";
        if ($isResubmission) {
            $message .= "🔄 لینک جدید شما برای بررسی مجدد ارسال شد.\n\n";
        }
        $message .= "تسک شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.";

        // Add /reserve button to message
        $this->sendMessageWithReserveButton($bot, $message);
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
            // Check if there's a rejected mission for resubmission
            $rejectedMission = \App\Models\MissionPersonnel::where('personnel_id', $personnelId)
                ->where('status', 'rejected')
                ->whereNotNull('rejection_reason')
                ->latest()
                ->first();
            
            $isResubmission = $rejectedMission !== null;
            
            $result = $this->missionService->submitResult($personnelId, $link);
            if ($result) {
                // Find mission to send to approval group
                $missionPersonnel = \App\Models\MissionPersonnel::where('personnel_id', $personnelId)
                    ->where('status', 'pending_approval')
                    ->where('result_link', $link)
                    ->latest()
                    ->first();

                if ($missionPersonnel) {
                    // Check if AI is not selected
                    if (!$missionPersonnel->selected_ai_id) {
                        // Ask user to select AI
                        $this->askForAiSelection($bot, $missionPersonnel, $type);
                        return;
                    }
                    
                    $this->sendMissionToApprovalGroup($missionPersonnel->mission, $missionPersonnel, $type, $isResubmission);
                    $message = "✅ لینک شما با موفقیت ثبت شد!\n\n";
                    if ($isResubmission) {
                        $message .= "🔄 لینک جدید شما برای بررسی مجدد ارسال شد.\n\n";
                    }
                    $message .= "ماموریت شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.";
                    // Add /reserve button to message
                    $this->sendMessageWithReserveButton($bot, $message);
                    return;
                }
            }
        } catch (Exception $e) {
            Log::info('Mission submission failed, trying task', ['error' => $e->getMessage()]);
        }

        // Fallback to task submission
        // Check for active tasks (reserved, in_progress, pending_approval) or rejected tasks (for resubmission)
        $task = Task::where('assigned_user_id', $personnelId)
            ->where(function($query) {
                // Active tasks
                $query->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
                      ->where(function($q) {
                          $q->where('reserved_time', '>', now())
                            ->orWhere('task_status', 'pending_approval');
                      })
                      // Or rejected tasks (for resubmission)
                      ->orWhere(function($q) {
                          $q->where('task_status', 'rejected')
                            ->whereNotNull('rejection_reason');
                      });
            })
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک یا ماموریت فعالی ندارید.");
            return;
        }

        // If task is already pending_approval and not rejected, don't allow resubmission
        if ($task->task_status === 'pending_approval' && !$task->rejection_reason) {
            BotHelper::sendMessage($bot, "تسک شما در حال بررسی است. لطفا منتظر نتیجه تایید باشید.");
            return;
        }

        // Check if reserved time has passed (only for reserved/in_progress tasks, not for rejected tasks being resubmitted)
        if ($task->task_status !== 'rejected' && $task->reserved_time && now() > $task->reserved_time) {
            $task->update(['task_status' => 'rejected', 'rejected_at' => now()]);
            BotHelper::sendMessage($bot, "متاسفانه زمان رزرو تسک به پایان رسیده است.");
            return;
        }

        // Check if this is a resubmission after rejection
        $isResubmission = $task->task_status === 'rejected' && $task->rejection_reason;
        
        // Update task with final link
        $updateData = [
            'final_link' => $link,
            'task_status' => 'pending_approval',
            'task_time' => now(),
        ];
        
        // If this is a resubmission, clear rejection fields
        if ($isResubmission) {
            $updateData['rejection_reason'] = null;
            $updateData['rejected_at'] = null;
            $updateData['approved_by_chat_id'] = null;
        }
        
        $task->update($updateData);

        // Send to approval group
        $this->sendToApprovalGroup($task, $type, $isResubmission);

        $message = "✅ لینک شما با موفقیت ثبت شد!\n\n";
        if ($isResubmission) {
            $message .= "🔄 لینک جدید شما برای بررسی مجدد ارسال شد.\n\n";
        }
        $message .= "تسک شما در صف تایید قرار گرفت. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.";

        // Add /reserve button to message
        $this->sendMessageWithReserveButton($bot, $message);
    }

    /**
     * Send mission to approval group.
     */
    private function sendMissionToApprovalGroup($mission, $missionPersonnel, $type, $isResubmission = false)
    {
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if (!$approvalGroupChatId) {
            Log::warning('MISSION_APPROVAL_GROUP_CHAT_ID not set');
            return;
        }

        $personnel = $missionPersonnel->personnel;
        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $bot = new Telegram($token, $type);

        if ($isResubmission) {
            $message = "🔄 ماموریت برای بررسی مجدد (ارسال مجدد لینک):\n\n";
            $message .= "⚠️ این ماموریت قبلاً رد شده و کاربر لینک جدید ارسال کرده است.\n\n";
        } else {
            $message = "📋 ماموریت جدید برای تایید:\n\n";
        }
        $message .= "شناسه ماموریت: " . $mission->id . "\n";
        $message .= "عنوان: " . $mission->title . "\n";
        $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "کد ملی: " . $personnel->national_code . "\n";
        $message .= "امتیاز: " . $mission->points . "\n";
        $message .= "لینک: " . $missionPersonnel->result_link . "\n\n";
        $message .= "✅ برای تایید: دکمه زیر را بزنید یا کلمه 'تایید' را reply کنید\n";
        $message .= "❌ برای رد: پیام خود را به این پیام reply کنید";

        // Add inline approve button
        $option = [
            array($bot->buildInlineKeyBoardButton('✅ تایید', callback_data: 'approve_mission_' . $missionPersonnel->id))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        
        $result = BotHelper::sendKeyboardMessageToChatIdWithResult($bot, $message, $inlineKeyboard, $approvalGroupChatId);
        
        // Save message_id to mission_personnel for future reference
        if ($result && isset($result['result']['message_id'])) {
            $missionPersonnel->update(['approval_message_id' => $result['result']['message_id']]);
        }
    }

    /**
     * Send task to approval group with complete information
     */
    private function sendToApprovalGroup($task, $type, $isResubmission = false)
    {
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        if (!$approvalGroupChatId) {
            Log::warning('MISSION_APPROVAL_GROUP_CHAT_ID not set');
            return;
        }

        // Load task relationships
        $task->load(['personnel', 'prompts']);

        $personnel = $task->personnel;
        $token = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $bot = new Telegram($token, $type);

        if ($isResubmission) {
            $message = "🔄 تسک برای بررسی مجدد (ارسال مجدد لینک):\n\n";
            $message .= "⚠️ این تسک قبلاً رد شده و کاربر لینک جدید ارسال کرده است.\n\n";
        } else {
            $message = "📋 تسک جدید برای تایید:\n\n";
        }
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📌 اطلاعات تسک:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "🆔 شناسه تسک: " . $task->id . "\n";
        $message .= "📝 نام تسک: " . $task->task_name . "\n";
        $message .= "🎯 امتیاز: " . $task->points . "\n";
        
        if ($task->reserved_time) {
            $message .= "⏰ زمان رزرو: " . $task->reserved_time->format('Y-m-d H:i:s') . "\n";
        }
        
        if ($task->task_time) {
            $message .= "📅 زمان ارسال: " . $task->task_time->format('Y-m-d H:i:s') . "\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 اطلاعات کاربر:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "👤 نام: " . $personnel->first_name . " " . $personnel->last_name . "\n";
        $message .= "🆔 کد ملی: " . $personnel->national_code . "\n";
        $message .= "📞 شماره تماس: " . $personnel->phone_number . "\n";
        $message .= "⭐ امتیاز کل: " . $personnel->total_points . "\n";
        $message .= "🎖️ درجه: " . $personnel->rank . "\n";
        
        // Add prompt content if available
        if ($task->prompts->isNotEmpty()) {
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 پرامپت تسک:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
            foreach ($task->prompts as $index => $prompt) {
                if ($task->prompts->count() > 1) {
                    $message .= "پرامپت " . ($index + 1) . ":\n";
                }
                $message .= $prompt->content . "\n\n";
            }
        }
        
        // Add task name as text content
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📄 متن تسک:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= $task->task_name . "\n";
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🔗 لینک ارسال شده:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= $task->final_link . "\n";
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚡ دستورات:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "✅ برای تایید: دکمه زیر را بزنید یا کلمه 'تایید' را reply کنید\n";
        $message .= "❌ برای رد: پیام خود را به این پیام reply کنید";

        // Add inline approve button
        $option = [
            array($bot->buildInlineKeyBoardButton('✅ تایید', callback_data: 'approve_task_' . $task->id))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        
        $result = BotHelper::sendKeyboardMessageToChatIdWithResult($bot, $message, $inlineKeyboard, $approvalGroupChatId);
        
        // Save message_id to task for future reference
        if ($result && isset($result['result']['message_id'])) {
            $task->update(['approval_message_id' => $result['result']['message_id']]);
            Log::info('Task sent to approval group', [
                'task_id' => $task->id,
                'approval_message_id' => $result['result']['message_id'],
                'personnel_id' => $personnel->id
            ]);
        } else {
            Log::warning('Failed to send task to approval group', [
                'task_id' => $task->id,
                'personnel_id' => $personnel->id
            ]);
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
            
            // Send training media links if available
            $this->sendTrainingMediaLinks($bot, $mission, $personnel, $type);

            // Send training media links if available (for sequential missions)
            $this->sendTrainingMediaLinks($bot, $mission, $personnel, $type);
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
        $message .= "/details - مشاهده جزئیات کامل تسک فعال\n";
        $message .= "/request_mission - درخواست یک ماموریت\n";
        $message .= "/get_training - دریافت آموزش‌های ماموریت فعال\n";
        $message .= "/cancel_mission - لغو ماموریت فعال\n";
        $message .= "/status - مشاهده وضعیت تسک‌ها و ماموریت‌ها\n";
        $message .= "/help - نمایش این راهنما\n\n";
        $message .= "💡 برای ارسال لینک نهایی، فقط لینک را در ربات ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle task details command
     */
    private function handleTaskDetails($bot, $personnel, $type)
    {
        Log::info('📋 Mission Bot - Task details request', [
            'personnel_id' => $personnel->id,
            'type' => $type
        ]);

        // Find active task
        $task = Task::where('assigned_user_id', $personnel->id)
            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
            ->where(function($query) {
                $query->where('reserved_time', '>', now())
                      ->orWhere('task_status', 'pending_approval');
            })
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "❌ شما تسک فعالی ندارید.\n\nبرای رزرو تسک جدید، دستور /reserve را ارسال کنید.");
            return;
        }

        // Load prompts
        $task->load('prompts');

        // Send complete task details
        $this->sendTaskDetailsMessage($bot, $task, $task->reserved_time, false);
    }

    /**
     * Send task details message with complete information
     */
    private function sendTaskDetailsMessage($bot, $task, $reservedTime, $isNewReservation = false)
    {
        $message = $isNewReservation 
            ? "✅ تسک شما با موفقیت رزرو شد!\n\n"
            : "📋 جزئیات کامل تسک شما:\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📌 اطلاعات تسک:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📋 نام تسک: " . $task->task_name . "\n";
        $message .= "🎯 امتیاز: " . $task->points . "\n";
        
        if ($reservedTime) {
            $message .= "⏰ مهلت ارسال: " . $reservedTime->format('Y-m-d H:i') . "\n";
            $message .= "⏳ زمان باقیمانده: " . $reservedTime->diffForHumans() . "\n";
        }
        
        $message .= "📊 وضعیت: " . $this->getStatusText($task->task_status) . "\n\n";

        // Add prompt content if available
        if ($task->prompts->isNotEmpty()) {
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 پرامپت:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
            foreach ($task->prompts as $index => $prompt) {
                if ($task->prompts->count() > 1) {
                    $message .= "پرامپت " . ($index + 1) . ":\n";
                }
                $message .= $prompt->content . "\n\n";
            }
        }
        
        // Add task name as text content for copying
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📄 متن تسک (برای کپی):\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= $task->task_name . "\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📌 راهنمای انجام تسک:\n\n";
        $message .= "1️⃣ تسک را انجام دهید (مثلاً یک پست در وبلاگ، ویرگول، لینکدین، توییتر، مدیوم، یوتیوب و...)\n\n";
        $message .= "2️⃣ لینک نتیجه کار خود را در این ربات ارسال کنید\n";
        $message .= "   مثال: https://virgool.io/@username/post\n";
        $message .= "   یا: https://www.linkedin.com/posts/...\n";
        $message .= "   یا: https://twitter.com/username/status/...\n";
        $message .= "   یا: https://medium.com/@username/...\n";
        $message .= "   یا: https://youtube.com/watch?v=...\n\n";
        $message .= "3️⃣ منتظر تایید بمانید\n\n";
        $message .= "💡 توجه: لینک باید دال و دلیل انجام تسک شما باشد (مثلاً لینک پست وبلاگ، مقاله، ویدیو و...)\n\n";

        // Create inline keyboard with /details button (if not new reservation) and /reserve button
        $option = [];
        if (!$isNewReservation) {
            $option[] = array($bot->buildInlineKeyBoardButton('📋 جزئیات کامل', callback_data: 'task_details'));
        }
        $option[] = array($bot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'));
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    /**
     * Send message with /reserve button
     */
    private function sendMessageWithReserveButton($bot, string $message)
    {
        $option = [
            array($bot->buildInlineKeyBoardButton('🔄 رزرو تسک جدید', callback_data: 'reserve_task'))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
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

    /**
     * Handle callback query (inline button clicks).
     */
    private function handleCallbackQuery($bot, array $callbackQuery, string $type, $botMotherId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['from']['id'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;
        
        // Store callback data in bot for later use
        $bot->Callback_Data($callbackData);

        Log::info('🔘 Mission Bot - Callback query received', [
            'callback_data' => $callbackData,
            'chat_id' => $chatId
        ]);

        // Get bot user
        $botUser = \App\Models\BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->first();

        if (!$botUser) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'کاربر یافت نشد', $type);
            return;
        }

        $personnelId = $botUser->setting('personnel_id');
        if (!$personnelId) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'شما ثبت‌نام نکرده‌اید', $type);
            return;
        }

        $personnel = Personnel::find($personnelId);
        if (!$personnel) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'کاربر یافت نشد', $type);
            return;
        }

        // Handle different callback data
        if ($callbackData == 'mission_random') {
            $this->handleMissionRandom($bot, $personnel, $type, $callbackQueryId);
        } elseif ($callbackData == 'mission_filter') {
            $this->handleMissionFilterMenu($bot, $personnel, $type, $callbackQueryId);
        } elseif (str_starts_with($callbackData, 'filter_duration_')) {
            $duration = (int) str_replace('filter_duration_', '', $callbackData);
            $this->handleMissionFilterByDuration($bot, $personnel, $duration, $type, $callbackQueryId);
        } elseif (str_starts_with($callbackData, 'filter_points_')) {
            $minPoints = (int) str_replace('filter_points_', '', $callbackData);
            $this->handleMissionFilterByPoints($bot, $personnel, $minPoints, $type, $callbackQueryId);
        } elseif ($callbackData == 'filter_tags_menu') {
            $this->handleMissionFilterTagsMenu($bot, $personnel, $type, $callbackQueryId);
        } elseif (str_starts_with($callbackData, 'filter_tag_')) {
            $tagId = (int) str_replace('filter_tag_', '', $callbackData);
            $this->handleMissionFilterByTag($bot, $personnel, $tagId, $type, $callbackQueryId);
        } elseif (str_starts_with($callbackData, 'select_mission_')) {
            $missionId = (int) str_replace('select_mission_', '', $callbackData);
            $this->handleSelectMission($bot, $personnel, $missionId, $type, $callbackQueryId);
        } elseif (str_starts_with($callbackData, 'select_ai_')) {
            // Format: select_ai_{aiId} or select_ai_{aiId}_{missionPersonnelId}
            $parts = explode('_', $callbackData);
            $aiId = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null;
            if ($aiId) {
                $this->handleSelectAi($bot, $personnel, $aiId, $callbackData, $type, $callbackQueryId);
            }
        } elseif ($callbackData == 'show_ai_list') {
            $this->handleShowAiList($bot, $personnel, $type, $callbackQueryId);
        } elseif ($callbackData == 'task_details') {
            $this->handleTaskDetails($bot, $personnel, $type);
            $this->answerCallbackQuery($bot, $callbackQueryId, '', $type);
        } elseif ($callbackData == 'reserve_task') {
            $this->handleReserveTask($bot, $personnel, $type);
            $this->answerCallbackQuery($bot, $callbackQueryId, '', $type);
        } else {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'دستور نامعتبر است', $type);
        }
    }

    /**
     * Handle random mission request.
     */
    private function handleMissionRandom($bot, $personnel, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال انتخاب ماموریت رندوم...', $type);
        
        try {
            $mission = $this->missionService->requestMission($personnel->id, 'random');
            
            if (!$mission) {
                BotHelper::sendMessage($bot, "❌ در حال حاضر ماموریت در دسترس نیست.\n\nلطفا بعداً تلاش کنید.");
                return;
            }

            $message = "✅ ماموریت رندوم شما:\n\n";
            $message .= "📋 عنوان: " . $mission->title . "\n";
            $message .= "📝 توضیحات: " . ($mission->description ?? 'ندارد') . "\n";
            $message .= "🎯 امتیاز: " . $mission->points . "\n";
            if ($mission->duration) {
                $message .= "⏱️ مدت زمان: " . $mission->duration . " دقیقه\n";
            }
            $message .= "\nبرای دریافت آموزش‌ها، دستور /get_training را ارسال کنید.";

            // Add AI selection button if mission has recommended AI
            $option = [];
            if ($mission->ai_id) {
                $option[] = array($bot->buildInlineKeyBoardButton('🤖 انتخاب هوش مصنوعی', callback_data: 'show_ai_list'));
            }
            if (!empty($option)) {
                $inlineKeyboard = $bot->buildInlineKeyBoard($option);
                BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
            } else {
                BotHelper::sendMessage($bot, $message);
            }
            
            // Send prompt and content in plain format
            $this->sendMissionContentPlain($bot, $mission, $type);
            $this->sendTrainingMediaLinks($bot, $mission, $personnel, $type);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error in random mission', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در درخواست ماموریت: " . $e->getMessage());
        }
    }

    /**
     * Handle mission filter menu.
     */
    private function handleMissionFilterMenu($bot, $personnel, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, '', $type);

        $message = "🔍 انتخاب نوع ماموریت:\n\n";
        $message .= "لطفا یکی از گزینه‌های زیر را انتخاب کنید:";

        $option = [
            array($bot->buildInlineKeyBoardButton('⏱️ ماموریت 1 دقیقه‌ای', callback_data: 'filter_duration_1')),
            array($bot->buildInlineKeyBoardButton('🎯 امتیاز 10 یا بیشتر', callback_data: 'filter_points_10')),
            array($bot->buildInlineKeyBoardButton('🏷️ بر اساس تگ', callback_data: 'filter_tags_menu'))
        ];
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    /**
     * Handle mission filter by duration.
     */
    private function handleMissionFilterByDuration($bot, $personnel, int $duration, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال جستجو...', $type);

        try {
            $missions = $this->missionService->getMissionsByDuration($duration, $personnel->tenant_id);
            
            if ($missions->isEmpty()) {
                BotHelper::sendMessage($bot, "❌ ماموریتی با مدت زمان " . $duration . " دقیقه یافت نشد.");
                return;
            }

            $this->showMissionSelection($bot, $missions, $type);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error filtering by duration', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در جستجو: " . $e->getMessage());
        }
    }

    /**
     * Handle mission filter by points.
     */
    private function handleMissionFilterByPoints($bot, $personnel, int $minPoints, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال جستجو...', $type);

        try {
            $missions = $this->missionService->getMissionsByMinPoints($minPoints, $personnel->tenant_id);
            
            if ($missions->isEmpty()) {
                BotHelper::sendMessage($bot, "❌ ماموریتی با امتیاز " . $minPoints . " یا بیشتر یافت نشد.");
                return;
            }

            $this->showMissionSelection($bot, $missions, $type);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error filtering by points', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در جستجو: " . $e->getMessage());
        }
    }

    /**
     * Handle mission filter tags menu.
     */
    private function handleMissionFilterTagsMenu($bot, $personnel, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, '', $type);

        try {
            // Get tags that have missions
            $tags = \App\Models\Tag::whereHas('missions', function ($q) use ($personnel) {
                $q->where('tenant_id', $personnel->tenant_id)
                  ->where('status', 'active');
            })->orderBy('order_column')->get();

            if ($tags->isEmpty()) {
                BotHelper::sendMessage($bot, "❌ هیچ تگی یافت نشد.");
                return;
            }

            $message = "🏷️ انتخاب تگ:\n\n";
            $message .= "لطفا یکی از تگ‌های زیر را انتخاب کنید:";

            $option = [];
            foreach ($tags as $tag) {
                $tagName = $tag->persian_name ?? $tag->name;
                if (is_array($tagName)) {
                    $tagName = $tagName['fa'] ?? '';
                }
                $buttonText = "🏷️ " . $tagName;
                if (strlen($buttonText) > 64) {
                    $buttonText = substr($buttonText, 0, 61) . '...';
                }
                $option[] = array($bot->buildInlineKeyBoardButton($buttonText, callback_data: 'filter_tag_' . $tag->id));
            }

            $inlineKeyboard = $bot->buildInlineKeyBoard($option);
            BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error showing tags menu', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در نمایش تگ‌ها: " . $e->getMessage());
        }
    }

    /**
     * Handle mission filter by tag.
     */
    private function handleMissionFilterByTag($bot, $personnel, int $tagId, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال جستجو...', $type);

        try {
            $missions = $this->missionService->getMissionsByTags([$tagId], $personnel->tenant_id);
            
            if ($missions->isEmpty()) {
                BotHelper::sendMessage($bot, "❌ ماموریتی با این تگ یافت نشد.");
                return;
            }

            $this->showMissionSelection($bot, $missions, $type);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error filtering by tag', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در جستجو: " . $e->getMessage());
        }
    }

    /**
     * Show mission selection menu.
     */
    private function showMissionSelection($bot, $missions, string $type): void
    {
        $message = "📋 ماموریت‌های یافت شده:\n\n";
        $message .= "لطفا یکی را انتخاب کنید:";

        $option = [];
        foreach ($missions as $mission) {
            $buttonText = "🆔 " . $mission->id . " - " . $mission->title;
            if (strlen($buttonText) > 64) {
                $buttonText = substr($buttonText, 0, 61) . '...';
            }
            $option[] = array($bot->buildInlineKeyBoardButton($buttonText, callback_data: 'select_mission_' . $mission->id));
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    /**
     * Handle mission selection.
     */
    private function handleSelectMission($bot, $personnel, int $missionId, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال اختصاص ماموریت...', $type);

        try {
            $assigned = $this->missionService->assignMission($missionId, $personnel->id);
            
            if (!$assigned) {
                BotHelper::sendMessage($bot, "❌ خطا در اختصاص ماموریت. ممکن است ماموریت پر باشد یا شما ماموریت فعالی داشته باشید.");
                return;
            }

            $mission = \App\Models\Mission::find($missionId);
            if (!$mission) {
                BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
                return;
            }

            $message = "✅ ماموریت با موفقیت اختصاص یافت!\n\n";
            $message .= "📋 عنوان: " . $mission->title . "\n";
            $message .= "📝 توضیحات: " . ($mission->description ?? 'ندارد') . "\n";
            $message .= "🎯 امتیاز: " . $mission->points . "\n";
            if ($mission->duration) {
                $message .= "⏱️ مدت زمان: " . $mission->duration . " دقیقه\n";
            }
            $message .= "\nبرای دریافت آموزش‌ها، دستور /get_training را ارسال کنید.";

            // Add AI selection button
            $option = [];
            $option[] = array($bot->buildInlineKeyBoardButton('🤖 انتخاب هوش مصنوعی', callback_data: 'show_ai_list'));
            $inlineKeyboard = $bot->buildInlineKeyBoard($option);
            BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
            
            // Send prompt and content in plain format
            $this->sendMissionContentPlain($bot, $mission, $type);
            $this->sendTrainingMediaLinks($bot, $mission, $personnel, $type);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error selecting mission', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, "❌ خطا در اختصاص ماموریت: " . $e->getMessage());
        }
    }

    /**
     * Send training media links for mission.
     */
    private function sendTrainingMediaLinks($bot, $mission, $personnel, string $type): void
    {
        try {
            $contents = $mission->contents;
            
            if ($contents->isEmpty()) {
                return;
            }

            $message = "📚 لینک‌های آموزش مرتبط با ماموریت:\n\n";
            foreach ($contents as $index => $content) {
                $message .= ($index + 1) . ". " . $content->title . "\n";
                $message .= "🔗 " . $content->content_url . "\n\n";
            }

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error sending training links', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Answer callback query.
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

    /**
     * Ask user to select AI after link submission.
     */
    private function askForAiSelection($bot, $missionPersonnel, string $type): void
    {
        $message = "🤖 لطفا هوش مصنوعی که برای انجام این ماموریت استفاده کرده‌اید را انتخاب کنید:\n\n";
        $message .= "این اطلاعات برای گزارش‌ها و ارزیابی‌ها استفاده می‌شود.";

        $aiLmms = \App\Models\AiLlm::active()->orderBy('sort_order')->get();

        if ($aiLmms->isEmpty()) {
            BotHelper::sendMessage($bot, "❌ در حال حاضر هوش مصنوعی‌ای در دسترس نیست.");
            return;
        }

        $option = [];
        foreach ($aiLmms as $ai) {
            $buttonText = "🤖 " . $ai->name;
            if (strlen($buttonText) > 64) {
                $buttonText = substr($buttonText, 0, 61) . '...';
            }
            $option[] = array($bot->buildInlineKeyBoardButton($buttonText, callback_data: 'select_ai_' . $ai->id . '_' . $missionPersonnel->id));
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    /**
     * Handle AI selection.
     */
    private function handleSelectAi($bot, $personnel, int $aiId, string $callbackData, string $type, ?string $callbackQueryId): void
    {
        // Extract mission_personnel_id from callback data (format: select_ai_{aiId}_{missionPersonnelId})
        $parts = explode('_', $callbackData);
        $missionPersonnelId = isset($parts[3]) && is_numeric($parts[3]) ? (int) $parts[3] : null;

        if (!$missionPersonnelId) {
            // Try to find active mission personnel
            $missionPersonnel = \App\Models\MissionPersonnel::where('personnel_id', $personnel->id)
                ->whereIn('status', ['in_progress', 'pending_approval'])
                ->latest()
                ->first();
        } else {
            $missionPersonnel = \App\Models\MissionPersonnel::find($missionPersonnelId);
        }

        if (!$missionPersonnel) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'ماموریت یافت نشد', $type);
            BotHelper::sendMessage($bot, "❌ ماموریت فعالی یافت نشد.");
            return;
        }

        $ai = \App\Models\AiLlm::find($aiId);
        if (!$ai) {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'هوش مصنوعی یافت نشد', $type);
            return;
        }

        // Update mission personnel with selected AI
        $missionPersonnel->update(['selected_ai_id' => $aiId]);

        $this->answerCallbackQuery($bot, $callbackQueryId, 'هوش مصنوعی انتخاب شد', $type);

        // If mission is pending_approval, send to approval group
        if ($missionPersonnel->status === 'pending_approval') {
            // Check if this is a resubmission (mission was previously rejected)
            $isResubmission = $missionPersonnel->rejection_reason !== null;
            $this->sendMissionToApprovalGroup($missionPersonnel->mission, $missionPersonnel, $type, $isResubmission);
            $message = "✅ هوش مصنوعی انتخاب شد: " . $ai->name . "\n\n";
            if ($isResubmission) {
                $message .= "🔄 ماموریت شما برای بررسی مجدد ارسال شد.\n\n";
            }
            $message .= "ماموریت شما در صف تایید قرار گرفت.";
            BotHelper::sendMessage($bot, $message);
        } else {
            BotHelper::sendMessage($bot, "✅ هوش مصنوعی انتخاب شد: " . $ai->name);
        }
    }

    /**
     * Handle show AI list.
     */
    private function handleShowAiList($bot, $personnel, string $type, ?string $callbackQueryId): void
    {
        $this->answerCallbackQuery($bot, $callbackQueryId, '', $type);

        $message = "🤖 لیست هوش مصنوعی‌های در دسترس:\n\n";
        $message .= "لطفا یکی را انتخاب کنید:";

        $aiLmms = \App\Models\AiLlm::active()->orderBy('sort_order')->get();

        if ($aiLmms->isEmpty()) {
            BotHelper::sendMessage($bot, "❌ در حال حاضر هوش مصنوعی‌ای در دسترس نیست.");
            return;
        }

        // Find active mission personnel
        $missionPersonnel = \App\Models\MissionPersonnel::where('personnel_id', $personnel->id)
            ->whereIn('status', ['reserved', 'in_progress', 'pending_approval'])
            ->latest()
            ->first();

        $option = [];
        foreach ($aiLmms as $ai) {
            $buttonText = "🤖 " . $ai->name;
            if (strlen($buttonText) > 64) {
                $buttonText = substr($buttonText, 0, 61) . '...';
            }
            $callbackData = 'select_ai_' . $ai->id;
            if ($missionPersonnel) {
                $callbackData .= '_' . $missionPersonnel->id;
            }
            $option[] = array($bot->buildInlineKeyBoardButton($buttonText, callback_data: $callbackData));
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    /**
     * Send mission prompt and content in plain format (for copying to AI).
     */
    private function sendMissionContentPlain($bot, $mission, string $type): void
    {
        try {
            // Send prompt if exists (plain, no extra text)
            if ($mission->prompt) {
                BotHelper::sendMessage($bot, $mission->prompt->content);
                Log::info('Prompt sent (plain)', ['mission_id' => $mission->id]);
            }

            // Send contents in order (plain)
            $contents = $mission->contents;
            foreach ($contents as $content) {
                $message = $content->title;
                if ($content->description) {
                    $message .= "\n\n" . $content->description;
                }
                if ($content->content_url) {
                    $message .= "\n\n" . $content->content_url;
                }
                BotHelper::sendMessage($bot, $message);
                // Small delay between messages
                sleep(1);
            }

            // Send AI URL if mission has recommended AI
            if ($mission->ai && $mission->ai->url) {
                $aiMessage = "🔗 لینک دسترسی به " . $mission->ai->name . ":\n" . $mission->ai->url;
                BotHelper::sendMessage($bot, $aiMessage);
            }
        } catch (Exception $e) {
            Log::error('❌ Mission Bot - Error sending plain content', [
                'error' => $e->getMessage(),
                'mission_id' => $mission->id
            ]);
        }
    }

    /**
     * Handle approval callback queries (for inline approve buttons)
     * This is needed because approval buttons are sent by MissionBot but callback goes to MissionBot webhook
     */
    private function handleApprovalCallbackQuery($bot, array $callbackQuery, string $type): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $userId = $callbackQuery['from']['id'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;

        Log::info('🔘 Mission Bot - Approval callback query received', [
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

        // Use the approval bot token (same as mission bot token in this case)
        $approvalToken = $type == 'bale' ? env('MISSION_BOT_TOKEN_BALE') : env('MISSION_BOT_TOKEN_TELEGRAM');
        $approvalBot = new Telegram($approvalToken, $type);

        // Handle approve button clicks
        if (str_starts_with($callbackData, 'approve_task_')) {
            $taskId = (int) str_replace('approve_task_', '', $callbackData);
            $task = \App\Models\Task::where('id', $taskId)
                ->where('task_status', 'pending_approval')
                ->first();

            if ($task) {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال تایید...', $type);
                // Call TaskApprovalController's approveTask method using reflection
                $this->callApprovalMethod('approveTask', $approvalBot, $task, $userId, $type);
            } else {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'تسک یافت نشد یا قبلاً تایید/رد شده است', $type);
            }
        } elseif (str_starts_with($callbackData, 'approve_mission_')) {
            $missionPersonnelId = (int) str_replace('approve_mission_', '', $callbackData);
            $missionPersonnel = \App\Models\MissionPersonnel::where('id', $missionPersonnelId)
                ->where('status', 'pending_approval')
                ->first();

            if ($missionPersonnel) {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'در حال تایید...', $type);
                // Call TaskApprovalController's approveMission method using reflection
                $this->callApprovalMethod('approveMission', $approvalBot, $missionPersonnel, $userId, $type);
            } else {
                $this->answerCallbackQuery($bot, $callbackQueryId, 'ماموریت یافت نشد یا قبلاً تایید/رد شده است', $type);
            }
        } else {
            $this->answerCallbackQuery($bot, $callbackQueryId, 'دستور نامعتبر است', $type);
        }
    }

    /**
     * Call TaskApprovalController method using reflection
     */
    private function callApprovalMethod(string $methodName, $bot, $entity, $userId, string $type): void
    {
        try {
            $approvalController = new \App\Http\Controllers\TaskApprovalController();
            $reflection = new \ReflectionClass($approvalController);
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            $method->invoke($approvalController, $bot, $entity, $userId, $type);
        } catch (Exception $e) {
            Log::error('Error calling approval method', [
                'method' => $methodName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
