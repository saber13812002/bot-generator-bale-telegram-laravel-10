<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\PrayerHelper;
use App\Interfaces\Services\PrayerBotService;
use App\Models\BotUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;
use Exception;

class PrayerBotController extends Controller
{
    protected PrayerBotService $prayerBotService;

    public function __construct(PrayerBotService $prayerBotService)
    {
        $this->prayerBotService = $prayerBotService;
    }

    /**
     * Webhook اصلی ربات نماز قضا
     * 
     * @param Request $request
     * @return int
     */
    public function webhook(Request $request): int
    {
        $startTime = microtime(true);
        
        Log::info('🕌 [PrayerBot] Webhook received', [
            'timestamp' => now()->toDateTimeString()
        ]);

        try {
            // بررسی origin
            if (!$request->has('origin')) {
                Log::warning('⚠️ [PrayerBot] No origin in request');
                return 200;
            }

            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id', 1);
            
            // ایجاد instance ربات
            $bot = $this->createBotInstance($request, $type);
            
            if (!$bot) {
                Log::error('❌ [PrayerBot] Could not create bot instance');
                return 200;
            }

            // لاگ درخواست (ساده‌تر از LogHelper)
            Log::info('📥 [PrayerBot] Request details', [
                'type' => $type,
                'bot_id' => $request->input('bot_id'),
                'bot_mother_id' => $request->input('bot_mother_id')
            ]);

            // استخراج اطلاعات پیام
            $chatId = $bot->ChatID();
            $text = $bot->Text();
            
            Log::info('📨 [PrayerBot] Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type
            ]);

            // دریافت یا ایجاد کاربر
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);

            // پردازش callback query (دکمه‌های inline)
            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $chatId, $type, $botMotherId);
                return 200;
            }

            // پردازش پیام متنی
            if ($text) {
                $this->handleTextMessage($bot, $text, $chatId, $type, $botMotherId);
            }

            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);
            
            Log::info('✅ [PrayerBot] Request processed successfully', [
                'chat_id' => $chatId,
                'type' => $type,
                'processing_time_ms' => $processingTime
            ]);

            return 200;
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error in webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 500;
        }
    }

    /**
     * پردازش callback query (دکمه‌های inline)
     */
    protected function handleCallbackQuery(Telegram $bot, array $callbackQuery, int $chatId, string $type, int $botMotherId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        
        Log::info('🔘 [PrayerBot] Callback query received', [
            'chat_id' => $chatId,
            'callback_data' => $callbackData
        ]);

        // پردازش انتخاب نماز
        if (str_starts_with($callbackData, 'prayer_')) {
            $prayerType = str_replace('prayer_', '', $callbackData);
            $this->handlePrayerSelection($bot, $prayerType, $chatId, $type);
            return;
        }

        // پردازش تنظیمات ایمیل
        if (str_starts_with($callbackData, 'email_')) {
            $this->handleEmailSettings($bot, $callbackData, $chatId, $type);
            return;
        }
    }

    /**
     * پردازش پیام متنی
     */
    protected function handleTextMessage(Telegram $bot, string $text, int $chatId, string $type, int $botMotherId): void
    {
        Log::info('💬 [PrayerBot] Processing text message', [
            'chat_id' => $chatId,
            'text' => $text
        ]);

        // دستور /start
        if ($text === '/start' || $text === 'شروع') {
            $this->handleStart($bot, $chatId, $type);
            return;
        }

        // دستور /help
        if ($text === '/help' || $text === 'راهنما') {
            $this->handleHelp($bot);
            return;
        }

        // دستور /stats
        if ($text === '/stats' || $text === 'آمار') {
            $this->handleStats($bot, $chatId, $type);
            return;
        }

        // دستور /estimate
        if (str_starts_with($text, '/estimate') || str_starts_with($text, 'تخمین')) {
            $this->handleEstimate($bot, $text, $chatId);
            return;
        }

        // دستور /remove_<id>
        if (preg_match('/\/remove[_\s](\d+)/', $text, $matches)) {
            $recordId = (int) $matches[1];
            $this->handleRemove($bot, $recordId, $chatId, $type);
            return;
        }

        // دستور /email
        if ($text === '/email' || $text === 'ایمیل') {
            $this->handleEmailCommand($bot, $chatId);
            return;
        }

        // تشخیص و ثبت عدد (رکعات)
        $number = PrayerHelper::detectNumberInText($text);
        if ($number && PrayerHelper::isValidRakatCount($number)) {
            $this->handleRecordPrayer($bot, $number, $text, $chatId, $type, $botMotherId);
            return;
        }

        // پیام پیش‌فرض
        $message = trans('bot.prayer_bot_unknown_command') . "\n\n";
        $message .= trans('bot.send_number_to_record') . " (2، 3 یا 4)\n";
        $message .= trans('bot.or_use_commands') . ":\n";
        $message .= "/stats - " . trans('bot.view_stats') . "\n";
        $message .= "/estimate - " . trans('bot.set_estimate') . "\n";
        $message .= "/help - " . trans('bot.help');
        
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش دستور /start
     */
    protected function handleStart(Telegram $bot, int $chatId, string $type): void
    {
        Log::info('🌟 [PrayerBot] Processing /start command', ['chat_id' => $chatId]);

        $message = "🕌 " . trans('bot.welcome_prayer_bot') . "\n\n";
        $message .= "📝 " . trans('bot.prayer_bot_description') . "\n\n";
        $message .= "🔢 " . trans('bot.send_number_instructions') . "\n";
        $message .= "  • 2 → " . trans('bot.fajr') . " (صبح)\n";
        $message .= "  • 3 → " . trans('bot.maghrib') . " (مغرب)\n";
        $message .= "  • 4 → " . trans('bot.dhuhr') . "/" . trans('bot.asr') . "/" . trans('bot.isha') . "\n\n";
        $message .= "📋 " . trans('bot.available_commands') . ":\n";
        $message .= "/stats - " . trans('bot.view_stats') . "\n";
        $message .= "/estimate - " . trans('bot.set_estimate') . "\n";
        $message .= "/email - " . trans('bot.email_settings') . "\n";
        $message .= "/help - " . trans('bot.help');

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش دستور /help
     */
    protected function handleHelp(Telegram $bot): void
    {
        $message = "📖 " . trans('bot.prayer_bot_help') . "\n\n";
        $message .= "1️⃣ " . trans('bot.how_to_record') . ":\n";
        $message .= "   " . trans('bot.just_send_number') . "\n\n";
        $message .= "2️⃣ " . trans('bot.how_to_remove') . ":\n";
        $message .= "   " . trans('bot.use_remove_command') . "\n\n";
        $message .= "3️⃣ " . trans('bot.how_to_estimate') . ":\n";
        $message .= "   /estimate 1000\n\n";
        $message .= "4️⃣ " . trans('bot.how_to_email') . ":\n";
        $message .= "   /email\n\n";
        $message .= "✨ " . trans('bot.smart_detection') . "\n";
        $message .= trans('bot.smart_detection_description');

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش دستور /stats
     */
    protected function handleStats(Telegram $bot, int $chatId, string $type): void
    {
        Log::info('📊 [PrayerBot] Processing /stats command', ['chat_id' => $chatId]);

        try {
            $message = $this->prayerBotService->generateStatsMessage($chatId, $type);
            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error getting stats', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            
            $message = "❌ " . trans('bot.error_getting_stats');
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * پردازش دستور /estimate
     */
    protected function handleEstimate(Telegram $bot, string $text, int $chatId): void
    {
        Log::info('🎯 [PrayerBot] Processing /estimate command', [
            'chat_id' => $chatId,
            'text' => $text
        ]);

        // استخراج عدد از دستور
        if (preg_match('/(\d+)/', $text, $matches)) {
            $totalPrayers = (int) $matches[1];

            try {
                $estimate = $this->prayerBotService->setEstimate($chatId, $totalPrayers);

                $message = "✅ " . trans('bot.estimate_set') . "\n\n";
                $message .= "🎯 " . trans('bot.total_prayers') . ": {$totalPrayers}\n";
                $message .= "🔢 " . trans('bot.total_rakats') . ": {$estimate->total_missed_rakats}\n\n";
                $message .= "💪 " . trans('bot.start_recording_now');

                BotHelper::sendMessage($bot, $message);
            } catch (Exception $e) {
                Log::error('❌ [PrayerBot] Error setting estimate', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId
                ]);

                $message = "❌ " . trans('bot.error_setting_estimate');
                BotHelper::sendMessage($bot, $message);
            }
        } else {
            $message = "📝 " . trans('bot.estimate_usage') . "\n\n";
            $message .= trans('bot.example') . ": /estimate 1000\n";
            $message .= trans('bot.or') . ": تخمین 500";

            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * ثبت رکعات نماز
     */
    protected function handleRecordPrayer(Telegram $bot, int $rakats, string $text, int $chatId, string $type, int $botMotherId): void
    {
        Log::info('📝 [PrayerBot] Recording prayer', [
            'chat_id' => $chatId,
            'rakats' => $rakats,
            'text' => $text
        ]);

        try {
            $messageId = $bot->MessageID();
            
            $record = $this->prayerBotService->recordPrayer(
                $chatId,
                $rakats,
                $type,
                $botMotherId,
                $messageId,
                $text
            );

            $prayerName = trans("bot.{$record->prayer_type}");
            
            $message = "✅ " . trans('bot.prayer_recorded') . "\n\n";
            $message .= "🔢 {$rakats} " . trans('bot.rakats') . "\n";
            $message .= "📿 " . trans('bot.prayer_type') . ": {$prayerName}\n";
            $message .= "🆔 " . trans('bot.record_id') . ": {$record->id}\n\n";
            $message .= "🗑️ " . trans('bot.to_remove') . ": /remove_{$record->id}\n\n";
            $message .= PrayerHelper::getRandomEncouragementMessage();

            // ارسال با reply به پیام اصلی
            if ($messageId) {
                BotHelper::sendMessageWithReply($bot, $message, $messageId);
            } else {
                BotHelper::sendMessage($bot, $message);
            }
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error recording prayer', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);

            $message = "❌ " . trans('bot.error_recording_prayer');
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * حذف رکعات ثبت شده
     */
    protected function handleRemove(Telegram $bot, int $recordId, int $chatId, string $type): void
    {
        Log::info('🗑️ [PrayerBot] Removing prayer record', [
            'chat_id' => $chatId,
            'record_id' => $recordId
        ]);

        try {
            $deleted = $this->prayerBotService->removePrayer($recordId, $chatId, $type);

            if ($deleted) {
                $message = "🗑️ " . trans('bot.prayer_removed') . "\n";
                $message .= "🆔 " . trans('bot.record_id') . ": {$recordId}";
            } else {
                $message = "❌ " . trans('bot.prayer_not_found') . "\n";
                $message .= trans('bot.check_record_id');
            }

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error removing prayer', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'record_id' => $recordId
            ]);

            $message = "❌ " . trans('bot.error_removing_prayer');
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * پردازش انتخاب نماز از keyboard
     */
    protected function handlePrayerSelection(Telegram $bot, string $prayerType, int $chatId, string $type): void
    {
        Log::info('🔘 [PrayerBot] Prayer selected from keyboard', [
            'chat_id' => $chatId,
            'prayer_type' => $prayerType
        ]);

        // تعیین تعداد رکعات بر اساس نوع نماز
        $rakatsMap = [
            'fajr' => 2,
            'dhuhr' => 4,
            'asr' => 4,
            'maghrib' => 3,
            'isha' => 4,
        ];

        $rakats = $rakatsMap[$prayerType] ?? 4;

        // ثبت با detection_method = keyboard
        // اینجا می‌توانیم بعداً پیاده‌سازی کنیم
        $message = "✅ " . trans("bot.{$prayerType}") . " - {$rakats} " . trans('bot.rakats');
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش دستور /email
     */
    protected function handleEmailCommand(Telegram $bot, int $chatId): void
    {
        $message = "📧 " . trans('bot.email_settings_title') . "\n\n";
        $message .= trans('bot.email_settings_description') . "\n\n";
        $message .= trans('bot.send_your_email');

        $keyboard = BotHelper::makeEmailSettingsKeyboard();
        BotHelper::messageWithKeyboard($bot->token, $chatId, $message, $keyboard);
    }

    /**
     * پردازش تنظیمات ایمیل
     */
    protected function handleEmailSettings(Telegram $bot, string $callbackData, int $chatId, string $type): void
    {
        Log::info('📧 [PrayerBot] Email settings callback', [
            'chat_id' => $chatId,
            'callback_data' => $callbackData
        ]);

        // پیاده‌سازی کامل در مرحله بعد
        $message = "⚙️ " . trans('bot.email_settings_coming_soon');
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Unsubscribe از ایمیل
     */
    public function unsubscribe(Request $request, string $token)
    {
        Log::info('🔕 [PrayerBot] Unsubscribe request', ['token' => $token]);

        try {
            $user = BotUsers::where('email_unsubscribe_token', $token)->first();

            if (!$user) {
                return view('emails.unsubscribe', ['status' => 'invalid']);
            }

            $user->email_report_frequency = 'never';
            $user->save();

            Log::info('✅ [PrayerBot] User unsubscribed', [
                'chat_id' => $user->chat_id,
                'email' => $user->email
            ]);

            return view('emails.unsubscribe', ['status' => 'success']);
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error unsubscribing', [
                'error' => $e->getMessage(),
                'token' => $token
            ]);

            return view('emails.unsubscribe', ['status' => 'error']);
        }
    }

    /**
     * ایجاد instance ربات
     */
    protected function createBotInstance(Request $request, string $type): ?Telegram
    {
        $token = null;

        // اولویت 1: توکن از query string
        if ($request->has('token')) {
            $token = $request->input('token');
            Log::info('🔑 [PrayerBot] Using token from query string');
        }
        // اولویت 2: توکن از bot_id
        elseif ($request->has('bot_id')) {
            $botId = $request->input('bot_id');
            $bot = \App\Models\Bot::find($botId);
            
            if ($bot) {
                $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
                Log::info('🔑 [PrayerBot] Using token from database', [
                    'bot_id' => $botId,
                    'has_token' => !empty($token)
                ]);
            } else {
                Log::warning('⚠️ [PrayerBot] Bot not found', ['bot_id' => $botId]);
            }
        }
        // اولویت 3: توکن پیش‌فرض از env
        else {
            $token = match($type) {
                'bale' => env('PRAYER_BOT_TOKEN_BALE'),
                'telegram' => env('PRAYER_BOT_TOKEN_TELEGRAM'),
                default => null
            };
            
            if ($token) {
                Log::info('🔑 [PrayerBot] Using token from environment');
            }
        }

        if (!$token) {
            Log::error('❌ [PrayerBot] No token found', [
                'type' => $type,
                'has_bot_id' => $request->has('bot_id'),
                'has_token_param' => $request->has('token')
            ]);
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }
}
