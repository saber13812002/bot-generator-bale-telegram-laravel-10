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

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();

            // Handle /start command with personnel_id parameter
            if ($text == '/start' || str_starts_with($text, '/start ')) {
                $this->handleStart($bot, $text, $type, $botMotherId);
            } 
            // Handle task submission (final link)
            else if (filter_var($text, FILTER_VALIDATE_URL)) {
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
                    if ($personnelId) {
                        $this->handleCommands($bot, $text, $personnelId, $type);
                    } else {
                        BotHelper::sendMessage($bot, "لطفا از لینک اختصاصی خود استفاده کنید. برای دریافت لینک، به ربات ثبت‌نام مراجعه کنید.");
                    }
                } else {
                    BotHelper::sendMessage($bot, "لطفا از لینک اختصاصی خود استفاده کنید. برای دریافت لینک، به ربات ثبت‌نام مراجعه کنید.");
                }
            }

        } catch (Exception $e) {
            Log::error('Mission bot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId ?? null,
                'text' => $text ?? null
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

        if (!$personnelId) {
            BotHelper::sendMessage($bot, "لطفا از لینک اختصاصی خود استفاده کنید.");
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

        // Get available tasks (you can implement logic to select a task)
        // For now, we'll create a simple task
        $reservedTime = now()->addHours(2);

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
