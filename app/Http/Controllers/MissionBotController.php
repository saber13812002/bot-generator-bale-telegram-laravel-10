<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\BotUsers;
use App\Models\Personnel;
use App\Models\Task;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram;

class MissionBotController extends Controller
{
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
        // Check if user has an active reserved task
        $activeTask = Task::where('assigned_user_id', $personnel->id)
            ->whereIn('task_status', ['reserved', 'in_progress', 'pending_approval'])
            ->where('reserved_time', '>', now())
            ->first();

        if ($activeTask) {
            $message = "شما یک تسک فعال دارید:\n";
            $message .= "نام تسک: " . $activeTask->task_name . "\n";
            $message .= "وضعیت: " . $this->getStatusText($activeTask->task_status) . "\n";
            $message .= "زمان باقیمانده: " . $activeTask->reserved_time->diffForHumans() . "\n\n";
            $message .= "لطفا تسک فعلی را تکمیل کنید یا منتظر بمانید تا زمان رزرو به پایان برسد.";
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
                        // Check if user has active task
                        $activeTask = Task::where('assigned_user_id', $personnelId)
                            ->whereIn('task_status', ['reserved', 'in_progress'])
                            ->where('reserved_time', '>', now())
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
        // Find active task
        $task = Task::where('assigned_user_id', $personnelId)
            ->whereIn('task_status', ['reserved', 'in_progress'])
            ->where('reserved_time', '>', now())
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک فعالی ندارید.");
            return;
        }

        // Check if reserved time has passed
        if (now() > $task->reserved_time) {
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

        // Find active task
        $task = Task::where('assigned_user_id', $personnelId)
            ->whereIn('task_status', ['reserved', 'in_progress'])
            ->where('reserved_time', '>', now())
            ->latest()
            ->first();

        if (!$task) {
            BotHelper::sendMessage($bot, "شما تسک فعالی ندارید.");
            return;
        }

        // Check if reserved time has passed
        if (now() > $task->reserved_time) {
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
     * Handle help command
     */
    private function handleHelp($bot)
    {
        $message = "📖 دستورات ربات ماموریت:\n\n";
        $message .= "/reserve - رزرو یک تسک جدید\n";
        $message .= "/status - مشاهده وضعیت تسک‌ها\n";
        $message .= "/help - نمایش این راهنما\n\n";
        $message .= "برای ارسال لینک نهایی، فقط لینک را در ربات ارسال کنید.";

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
