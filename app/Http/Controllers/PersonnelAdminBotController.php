<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Personnel;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram;

class PersonnelAdminBotController extends Controller
{
    /**
     * Handle personnel admin bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Personnel Admin Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            
            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('PERSONNEL_ADMIN_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('PERSONNEL_ADMIN_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Personnel Admin Bot - Webhook status check', [
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
            
            $chatId = $bot->ChatID();
            $text = $bot->Text();
            
            // Log message received
            Log::info('📨 Personnel Admin Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type
            ]);

            // Check if user is admin
            if (!AdminHelper::isAdmin($chatId)) {
                $message = "❌ شما دسترسی به این ربات ندارید.\nاین ربات فقط برای ادمین‌ها قابل استفاده است.";
                BotHelper::sendMessage($bot, $message);
                Log::warning('⚠️ Personnel Admin Bot - Unauthorized access attempt', ['chat_id' => $chatId]);
                return;
            }

            // Get tenant_id from env (هر tenant یک ربات ادمین دارد)
            $tenantId = null;
            if ($type == 'bale') {
                $tenantId = env('PERSONNEL_ADMIN_BOT_TENANT_ID_BALE');
            } else {
                $tenantId = env('PERSONNEL_ADMIN_BOT_TENANT_ID_TELEGRAM');
            }

            if (!$tenantId) {
                Log::error('❌ Personnel Admin Bot - Tenant ID not configured', ['type' => $type]);
                BotHelper::sendMessage($bot, "❌ خطا: تنظیمات ربات ناقص است. لطفا با مدیریت تماس بگیرید.");
                return;
            }

            // Get tenant
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                Log::error('❌ Personnel Admin Bot - Tenant not found', ['tenant_id' => $tenantId]);
                BotHelper::sendMessage($bot, "❌ خطا: تننت یافت نشد. لطفا با مدیریت تماس بگیرید.");
                return;
            }

            // Handle commands
            $command = mb_strtolower(trim($text));
            
            if ($command == '/start') {
                $this->handleStart($bot, $tenant);
            } elseif ($command == '/today' || $command == 'امروز') {
                $this->handleToday($bot, $tenant);
            } elseif ($command == '/all' || $command == 'همه' || $command == 'کل') {
                $this->handleAll($bot, $tenant);
            } elseif ($command == '/help' || $command == 'راهنما') {
                $this->handleHelp($bot);
            } else {
                // Default to help
                $this->handleHelp($bot);
            }
            
            Log::info('✅ Personnel Admin Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Personnel Admin Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $bot->ChatID() ?? null,
                'text' => $bot->Text() ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    /**
     * Handle start command
     */
    private function handleStart($bot, $tenant)
    {
        $message = "👋 سلام! ربات ادمین ثبت‌نام پرسنل\n\n";
        $message .= "تننت: " . $tenant->tenant_name . "\n\n";
        $message .= "دستورات موجود:\n";
        $message .= "/today - لیست ثبت‌نام‌های امروز\n";
        $message .= "/all - لیست کل ثبت‌نام‌ها\n";
        $message .= "/help - راهنما";
        
        BotHelper::sendMessage($bot, $message);
        
        Log::info('✅ Personnel Admin Bot - Start command processed', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->tenant_name
        ]);
    }

    /**
     * Handle today command - show today's registrations
     */
    private function handleToday($bot, $tenant)
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        
        $personnelList = Personnel::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $count = $personnelList->count();
        
        if ($count == 0) {
            $message = "📊 ثبت‌نام‌های امروز (" . $today->format('Y/m/d') . ")\n\n";
            $message .= "هیچ ثبت‌نامی برای امروز وجود ندارد.";
            BotHelper::sendMessage($bot, $message);
            
            Log::info('📊 Personnel Admin Bot - Today command: No registrations', [
                'tenant_id' => $tenant->id,
                'date' => $today->format('Y-m-d')
            ]);
            return;
        }
        
        $message = "📊 ثبت‌نام‌های امروز (" . $today->format('Y/m/d') . ")\n";
        $message .= "تعداد کل: " . $count . "\n\n";
        
        // اگر تعداد زیاد باشد، فقط 50 تا اول را نشان بده
        $displayList = $personnelList->take(50);
        $index = 1;
        
        foreach ($displayList as $personnel) {
            $time = Carbon::parse($personnel->created_at)->format('H:i');
            $message .= $index . ". " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "   کد ملی: " . $personnel->national_code . "\n";
            $message .= "   تلفن: " . $personnel->phone_number . "\n";
            $message .= "   درجه: " . $personnel->rank . "\n";
            $message .= "   زمان: " . $time . "\n\n";
            $index++;
        }
        
        if ($count > 50) {
            $message .= "... و " . ($count - 50) . " مورد دیگر\n";
            $message .= "(فقط 50 مورد اول نمایش داده شد)";
        }
        
        BotHelper::sendMessage($bot, $message);
        
        Log::info('📊 Personnel Admin Bot - Today command processed', [
            'tenant_id' => $tenant->id,
            'count' => $count,
            'date' => $today->format('Y-m-d')
        ]);
    }

    /**
     * Handle all command - show all registrations
     */
    private function handleAll($bot, $tenant)
    {
        $personnelList = Personnel::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $count = $personnelList->count();
        
        if ($count == 0) {
            $message = "📋 لیست کل ثبت‌نام‌ها\n\n";
            $message .= "هیچ ثبت‌نامی وجود ندارد.";
            BotHelper::sendMessage($bot, $message);
            
            Log::info('📋 Personnel Admin Bot - All command: No registrations', [
                'tenant_id' => $tenant->id
            ]);
            return;
        }
        
        // اگر تعداد خیلی زیاد باشد، فقط 50 تا آخر را نشان بده
        $displayList = $personnelList->take(50);
        $index = 1;
        
        $message = "📋 لیست کل ثبت‌نام‌ها\n";
        $message .= "تعداد کل: " . $count . "\n\n";
        
        foreach ($displayList as $personnel) {
            $date = Carbon::parse($personnel->created_at)->format('Y/m/d H:i');
            $message .= $index . ". " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "   کد ملی: " . $personnel->national_code . "\n";
            $message .= "   تلفن: " . $personnel->phone_number . "\n";
            $message .= "   درجه: " . $personnel->rank . "\n";
            $message .= "   تاریخ: " . $date . "\n\n";
            $index++;
        }
        
        if ($count > 50) {
            $message .= "... و " . ($count - 50) . " مورد دیگر\n";
            $message .= "(فقط 50 مورد آخر نمایش داده شد)";
        }
        
        BotHelper::sendMessage($bot, $message);
        
        Log::info('📋 Personnel Admin Bot - All command processed', [
            'tenant_id' => $tenant->id,
            'count' => $count
        ]);
    }

    /**
     * Handle help command
     */
    private function handleHelp($bot)
    {
        $message = "📖 راهنمای ربات ادمین ثبت‌نام پرسنل\n\n";
        $message .= "دستورات موجود:\n\n";
        $message .= "/start - شروع کار با ربات\n";
        $message .= "/today یا 'امروز' - نمایش لیست ثبت‌نام‌های امروز\n";
        $message .= "/all یا 'همه' یا 'کل' - نمایش لیست کل ثبت‌نام‌ها\n";
        $message .= "/help یا 'راهنما' - نمایش این راهنما";
        
        BotHelper::sendMessage($bot, $message);
    }
}

