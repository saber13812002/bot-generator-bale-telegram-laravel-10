<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\PrayerHelper;
use App\Interfaces\Services\EmailService;
use App\Interfaces\Services\PrayerBotService;
use App\Models\BotUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Telegram;
use Exception;

class PrayerBotController extends Controller
{
    protected PrayerBotService $prayerBotService;
    protected EmailService $emailService;

    public function __construct(PrayerBotService $prayerBotService, EmailService $emailService)
    {
        $this->prayerBotService = $prayerBotService;
        $this->emailService = $emailService;
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
            $this->handleEmailSettings($bot, $callbackData, $callbackQuery, $chatId, $type, $botMotherId);
            return;
        }

        // پردازش Help callbacks
        if (str_starts_with($callbackData, 'help_')) {
            $this->handleHelpCallback($bot, $callbackQuery, $type);
            return;
        }

        // پردازش Estimate callbacks
        if (str_starts_with($callbackData, 'estimate_')) {
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
            $this->handleEstimateCallback($bot, $callbackQuery, $botUser, $type, $botMotherId);
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
            $this->showHelpMain($bot, $chatId);
            return;
        }

        // دستورات help خاص
        if (str_starts_with($text, '/help_')) {
            $helpType = str_replace('/help_', '', $text);
            $this->showHelp($bot, $chatId, $helpType);
            return;
        }

        // دستور /stats
        if ($text === '/stats' || $text === 'آمار') {
            $this->handleStats($bot, $chatId, $type);
            return;
        }

        // دستور /estimate
        if ($text === '/estimate' || $text === 'تخمین') {
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
            $this->handleEstimateStart($bot, $chatId, $botUser, $type, $botMotherId);
            return;
        }

        // دستور /estimate_status
        if ($text === '/estimate_status' || $text === 'وضعیت تخمین') {
            $this->handleEstimateStatus($bot, $chatId, $type);
            return;
        }

        // چک کردن state برای دریافت عدد تخمین
        $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
        $state = $this->prayerBotService->getState($botUser->id);
        if ($state && $state->state === 'estimate_waiting_value') {
            $this->handleEstimateValue($bot, $chatId, $text, $state, $botUser, $type);
            return;
        }

        // دستور /remove_<id>
        if (preg_match('/\/remove[_\s](\d+)/', $text, $matches)) {
            $recordId = (int) $matches[1];
            $this->handleRemove($bot, $recordId, $chatId, $type);
            return;
        }

        // دستور /email و /set_email
        if ($text === '/email' || $text === '/set_email' || $text === 'ایمیل') {
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
            $this->handleEmailCommand($bot, $chatId, $botUser, $type, $botMotherId);
            return;
        }

        // دستور /email_settings
        if ($text === '/email_settings' || $text === 'تنظیمات ایمیل') {
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
            $this->handleEmailSettingsCommand($bot, $chatId, $botUser, $type);
            return;
        }

        // چک کردن state برای دریافت ایمیل
        $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
        $state = $this->prayerBotService->getState($botUser->id);
        if ($state && $state->state === 'email_waiting_address') {
            $this->handleEmailAddress($bot, $chatId, $text, $state, $botUser, $type, $botMotherId);
            return;
        }

        // چک کردن state برای دریافت کد تایید
        if ($state && $state->state === 'email_waiting_code') {
            $this->handleEmailVerificationCode($bot, $chatId, $text, $state, $botUser, $type);
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
     * نمایش Help اصلی
     */
    protected function showHelpMain(Telegram $bot, int $chatId, ?int $messageId = null): void
    {
        $message = trans('bot.help_main_title') . "\n\n";
        $message .= trans('bot.help_main_welcome') . "\n\n";
        $message .= trans('bot.help_main_features') . "\n\n";
        $message .= trans('bot.help_main_select') . "\n\n";
        $message .= trans('bot.help_main_quick_start');
        
        $keyboard = [
            [
                ['text' => trans('bot.help_btn_commands'), 'callback_data' => 'help_commands'],
                ['text' => trans('bot.help_btn_usage'), 'callback_data' => 'help_usage'],
            ],
            [
                ['text' => trans('bot.help_btn_estimate'), 'callback_data' => 'help_estimate'],
                ['text' => trans('bot.help_btn_report'), 'callback_data' => 'help_report'],
            ],
            [
                ['text' => trans('bot.help_btn_faq'), 'callback_data' => 'help_faq'],
            ]
        ];
        
        if ($messageId) {
            $bot->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        }
    }

    /**
     * نمایش Help خاص
     */
    protected function showHelp(Telegram $bot, int $chatId, string $type, ?int $messageId = null): void
    {
        $message = match($type) {
            'commands' => $this->getHelpCommands(),
            'usage' => $this->getHelpUsage(),
            'estimate' => $this->getHelpEstimate(),
            'report' => $this->getHelpReport(),
            'faq' => $this->getHelpFaq(),
            'main' => null, // برای بازگشت به منوی اصلی
            default => trans('bot.help_main_title')
        };
        
        // اگر main بود، نمایش Help اصلی
        if ($type === 'main') {
            $this->showHelpMain($bot, $chatId, $messageId);
            return;
        }
        
        $keyboard = [
            [
                ['text' => trans('bot.help_btn_back_to_help'), 'callback_data' => 'help_main'],
            ]
        ];
        
        if ($messageId) {
            $bot->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        }
    }

    /**
     * Handle Help Callback
     */
    protected function handleHelpCallback(Telegram $bot, array $callbackQuery, string $type): void
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $callbackData = $callbackQuery['data'];
        
        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery['id']
        ]);
        
        // استخراج نوع help
        $helpType = str_replace('help_', '', $callbackData);
        
        // نمایش help
        $this->showHelp($bot, $chatId, $helpType, $messageId);
    }

    /**
     * متن Help دستورات
     */
    protected function getHelpCommands(): string
    {
        return trans('bot.help_commands_title') . "\n\n" .
               trans('bot.help_commands_main') . "\n\n" .
               trans('bot.help_commands_record') . "\n\n" .
               trans('bot.help_commands_estimate') . "\n\n" .
               trans('bot.help_commands_report') . "\n\n" .
               trans('bot.help_commands_help') . "\n\n" .
               trans('bot.help_commands_tip');
    }

    /**
     * متن Help نحوه استفاده
     */
    protected function getHelpUsage(): string
    {
        return trans('bot.help_usage_title') . "\n\n" .
               trans('bot.help_usage_content');
    }

    /**
     * متن Help تخمین
     */
    protected function getHelpEstimate(): string
    {
        return trans('bot.help_estimate_title') . "\n\n" .
               trans('bot.help_estimate_what') . "\n\n" .
               trans('bot.help_estimate_how') . "\n\n" .
               trans('bot.help_estimate_calc') . "\n\n" .
               trans('bot.help_estimate_progress') . "\n\n" .
               trans('bot.help_estimate_tip');
    }

    /**
     * متن Help گزارش
     */
    protected function getHelpReport(): string
    {
        return trans('bot.help_report_title') . "\n\n" .
               trans('bot.help_report_what') . "\n\n" .
               trans('bot.help_report_how') . "\n\n" .
               trans('bot.help_report_settings') . "\n\n" .
               trans('bot.help_report_time') . "\n\n" .
               trans('bot.help_report_security') . "\n\n" .
               trans('bot.help_report_tip');
    }

    /**
     * متن Help سوالات متداول
     */
    protected function getHelpFaq(): string
    {
        return trans('bot.help_faq_title') . "\n\n" .
               trans('bot.help_faq_content');
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
     * شروع فرآیند تخمین
     */
    protected function handleEstimateStart(Telegram $bot, int $chatId, $botUser, string $type, int $botMotherId): void
    {
        Log::info('📊 [PrayerBot] Estimate start', ['chat_id' => $chatId]);
        
        // دریافت تخمین فعلی
        $currentEstimate = $this->prayerBotService->getProgress($chatId, $type);
        
        $message = trans('bot.help_estimate_title') . "\n\n";
        $message .= trans('bot.help_main_select') . "\n\n";
        
        if ($currentEstimate && isset($currentEstimate['estimate'])) {
            $equivalent = $this->formatEquivalent($currentEstimate['estimate']);
            $message .= trans('bot.help_estimate_current', [
                'rakats' => number_format($currentEstimate['estimate']),
                'equivalent' => $equivalent
            ]) . "\n\n";
        } else {
            $message .= trans('bot.estimate_no_current') . "\n\n";
        }
        
        $keyboard = [
            [
                ['text' => '📅 ' . trans('bot.unit_day'), 'callback_data' => 'estimate_day'],
                ['text' => '📆 ' . trans('bot.unit_week'), 'callback_data' => 'estimate_week'],
            ],
            [
                ['text' => '🗓️ ' . trans('bot.unit_month'), 'callback_data' => 'estimate_month'],
                ['text' => '📊 ' . trans('bot.unit_year'), 'callback_data' => 'estimate_year'],
            ],
            [
                ['text' => '🔢 ' . trans('bot.unit_rakat'), 'callback_data' => 'estimate_rakat'],
            ],
            [
                ['text' => '❌ ' . trans('bot.cancel'), 'callback_data' => 'estimate_cancel'],
            ]
        ];
        
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Handle Estimate Callback
     */
    protected function handleEstimateCallback(Telegram $bot, array $callbackQuery, $botUser, string $type, int $botMotherId): void
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $callbackData = $callbackQuery['data'];
        
        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery['id']
        ]);
        
        // حذف کیبورد
        $bot->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
        
        if ($callbackData === 'estimate_cancel') {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => trans('bot.estimate_cancelled')
            ]);
            return;
        }
        
        // استخراج واحد
        $unit = str_replace('estimate_', '', $callbackData);
        
        // ست کردن state
        $this->prayerBotService->setState(
            $botUser->id,
            $botMotherId,
            'estimate_waiting_value',
            ['unit' => $unit],
            10 // 10 دقیقه
        );
        
        $unitName = trans('bot.unit_' . $unit);
        $message = trans('bot.estimate_unit_selected', ['unit' => $unitName]);
        
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);
    }

    /**
     * Handle Estimate Value
     */
    protected function handleEstimateValue(Telegram $bot, int $chatId, string $text, $state, $botUser, string $type): void
    {
        // چک کردن عدد بودن
        if (!is_numeric($text) || $text <= 0) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => trans('bot.estimate_invalid_number')
            ]);
            return;
        }
        
        $value = (int) $text;
        $unit = $state->getData('unit');
        
        // تبدیل به رکعت
        $rakats = $this->prayerBotService->convertToRakats($value, $unit);
        
        // ذخیره تخمین
        try {
            $this->prayerBotService->setEstimate($chatId, $rakats, "$value $unit");
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error setting estimate', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ " . trans('bot.error_setting_estimate')
            ]);
            return;
        }
        
        // پاک کردن state
        $this->prayerBotService->clearState($botUser->id);
        
        // ارسال پیام تایید
        $unitName = trans('bot.unit_' . $unit);
        $message = trans('bot.estimate_saved', [
            'value' => $value,
            'unit' => $unitName,
            'rakats' => number_format($rakats)
        ]);
        
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);
    }

    /**
     * فرمت معادل برای نمایش
     */
    protected function formatEquivalent(int $rakats): string
    {
        $equivalents = $this->prayerBotService->calculateEquivalents($rakats);
        
        if ($equivalents['years'] >= 1) {
            return round($equivalents['years'], 1) . ' ' . trans('bot.unit_year');
        } elseif ($equivalents['months'] >= 1) {
            return round($equivalents['months'], 1) . ' ' . trans('bot.unit_month');
        } elseif ($equivalents['weeks'] >= 1) {
            return round($equivalents['weeks'], 1) . ' ' . trans('bot.unit_week');
        } else {
            return round($equivalents['days'], 1) . ' ' . trans('bot.unit_day');
        }
    }

    /**
     * پردازش دستور /estimate_status
     */
    protected function handleEstimateStatus(Telegram $bot, int $chatId, string $type): void
    {
        Log::info('📊 [PrayerBot] Processing /estimate_status command', ['chat_id' => $chatId]);

        try {
            $progress = $this->prayerBotService->getProgress($chatId, $type);

            if (!$progress || !isset($progress['estimate']) || $progress['estimate'] <= 0) {
                $message = "📊 " . trans('bot.estimate_status_title') . "\n\n";
                $message .= trans('bot.estimate_status_no_estimate');
                BotHelper::sendMessage($bot, $message);
                return;
            }

            $estimate = $progress['estimate'];
            $recorded = $progress['total_missed_rakats'] - $progress['remaining_rakats'];
            $remaining = $progress['remaining_rakats'];
            $percentage = $progress['progress_percentage'] ?? 0;

            $equivalent = $this->formatEquivalent($estimate);

            $message = "📊 " . trans('bot.estimate_status_title') . "\n\n";
            $message .= "🎯 " . trans('bot.estimate_status_total') . ": " . number_format($estimate) . " " . trans('bot.rakats') . "\n";
            $message .= "📅 " . trans('bot.estimate_status_equivalent') . ": " . $equivalent . "\n\n";
            $message .= "✅ " . trans('bot.estimate_status_recorded') . ": " . number_format($recorded) . " " . trans('bot.rakats') . "\n";
            $message .= "📉 " . trans('bot.estimate_status_remaining') . ": " . number_format($remaining) . " " . trans('bot.rakats') . "\n";
            $message .= "📊 " . trans('bot.estimate_status_progress') . ": " . round($percentage, 1) . "%\n\n";

            if ($percentage >= 75) {
                $message .= "🎉 " . trans('bot.almost_done');
            } elseif ($percentage >= 50) {
                $message .= "💪 " . trans('bot.great_progress');
            } elseif ($percentage >= 25) {
                $message .= "✨ " . trans('bot.keep_going');
            } else {
                $message .= "🌟 " . trans('bot.good_start');
            }

            BotHelper::sendMessage($bot, $message);
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error getting estimate status', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);

            $message = "❌ " . trans('bot.error_getting_stats');
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
     * پردازش دستور /email و /set_email
     */
    protected function handleEmailCommand(Telegram $bot, int $chatId, $botUser, string $type, int $botMotherId): void
    {
        Log::info('📧 [PrayerBot] Processing /email or /set_email command', ['chat_id' => $chatId]);

        // اگر ایمیل ست و verify شده است
        if ($botUser->email && $botUser->email_verified_at) {
            $message = "✅ " . trans('bot.email_already_set') . "\n";
            $message .= "📧 {$botUser->email}\n\n";
            $message .= trans('bot.email_change_instructions');

            $keyboard = [
                [
                    ['text' => '✏️ ' . trans('bot.email_settings_change_email'), 'callback_data' => 'email_change'],
                    ['text' => '🔕 ' . trans('bot.email_settings_unsubscribe'), 'callback_data' => 'email_unsubscribe']
                ]
            ];

            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
            return;
        }

        // ست کردن state برای دریافت ایمیل
        $this->prayerBotService->setState(
            $botUser->id,
            $botMotherId,
            'email_waiting_address',
            null,
            10 // 10 دقیقه
        );

        $message = "📧 " . trans('bot.email_settings_title') . "\n\n";
        $message .= trans('bot.email_settings_description') . "\n\n";
        $message .= trans('bot.send_your_email') . "\n\n";
        $message .= trans('bot.email_format_example');

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش تنظیمات ایمیل (callback)
     */
    protected function handleEmailSettings(Telegram $bot, string $callbackData, array $callbackQuery, int $chatId, string $type, int $botMotherId): void
    {
        Log::info('📧 [PrayerBot] Email settings callback', [
            'chat_id' => $chatId,
            'callback_data' => $callbackData
        ]);

        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery['id']
        ]);

        $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
        
        // پردازش تغییر فرکانس
        if (str_starts_with($callbackData, 'email_frequency_')) {
            $frequency = str_replace('email_frequency_', '', $callbackData);
            $this->updateEmailFrequency($bot, $chatId, $botUser, $frequency);
            return;
        }

        // پردازش لغو اشتراک
        if ($callbackData === 'email_unsubscribe') {
            $this->handleUnsubscribeFromBot($bot, $chatId, $botUser);
            return;
        }

        // پردازش تغییر ایمیل
        if ($callbackData === 'email_change') {
            $this->prayerBotService->setState(
                $botUser->id,
                $botMotherId,
                'email_waiting_address',
                null,
                10
            );
            
            $message = trans('bot.send_your_email') . "\n\n";
            $message .= trans('bot.email_format_example');
            
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $message
            ]);
            return;
        }
    }

    /**
     * پردازش دستور /email_settings
     */
    protected function handleEmailSettingsCommand(Telegram $bot, int $chatId, $botUser, string $type): void
    {
        Log::info('⚙️ [PrayerBot] Processing /email_settings command', ['chat_id' => $chatId]);

        $message = "⚙️ " . trans('bot.email_settings_title') . "\n\n";

        // نمایش تنظیمات فعلی
        if ($botUser->email) {
            $message .= "📧 " . trans('bot.email_settings_current_email') . ": {$botUser->email}\n";
            
            if ($botUser->email_verified_at) {
                $message .= "✅ " . trans('bot.email_settings_verified') . "\n";
            } else {
                $message .= "⚠️ " . trans('bot.email_settings_not_verified') . "\n";
            }
        } else {
            $message .= "❌ " . trans('bot.email_settings_no_email') . "\n";
        }

        $message .= "\n📅 " . trans('bot.email_settings_frequency') . ": " . trans('bot.' . ($botUser->email_report_frequency ?? 'weekly')) . "\n\n";
        $message .= trans('bot.email_settings_instructions');

        $keyboard = [
            [
                ['text' => '📅 ' . trans('bot.daily'), 'callback_data' => 'email_frequency_daily'],
                ['text' => '📆 ' . trans('bot.weekly'), 'callback_data' => 'email_frequency_weekly'],
            ],
            [
                ['text' => '🗓️ ' . trans('bot.monthly'), 'callback_data' => 'email_frequency_monthly'],
                ['text' => '🚫 ' . trans('bot.never'), 'callback_data' => 'email_frequency_never'],
            ],
        ];

        if ($botUser->email && $botUser->email_verified_at) {
            $keyboard[] = [
                ['text' => '🔕 ' . trans('bot.email_settings_unsubscribe'), 'callback_data' => 'email_unsubscribe'],
            ];
        }

        $keyboard[] = [
            ['text' => '✏️ ' . trans('bot.email_settings_change_email'), 'callback_data' => 'email_change'],
        ];

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * پردازش دریافت آدرس ایمیل
     */
    protected function handleEmailAddress(Telegram $bot, int $chatId, string $text, $state, $botUser, string $type, int $botMotherId): void
    {
        // Validation ایمیل
        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => trans('bot.email_invalid_format')
            ]);
            return;
        }

        // تولید کد 6 رقمی
        $code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        // ذخیره ایمیل و کد در دیتابیس
        $botUser->email = $text;
        $botUser->email_verification_code = $code;
        $botUser->email_verification_code_expires_at = now()->addMinutes(10);
        $botUser->email_verified_at = null; // هنوز تایید نشده
        $botUser->save();

        // ارسال کد به ایمیل با Mailtrap API
        try {
            $this->emailService->sendVerificationEmail($text, $code);
            
            Log::info('📧 [PrayerBot] Verification code sent via Mailtrap', [
                'chat_id' => $chatId,
                'email' => $text,
                'code' => $code
            ]);
        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Error sending verification email', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId,
                'email' => $text,
                'mailtrap_config' => [
                    'use_sandbox' => env('MAILTRAP_USE_SANDBOX', true),
                    'has_token' => !empty(env('MAILTRAP_API_TOKEN')),
                    'has_inbox_id' => !empty(env('MAILTRAP_INBOX_ID')),
                ]
            ]);

            // پیام خطا برای کاربر
            try {
                $errorMessage = trans('bot.email_send_error');
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $errorMessage
                ]);
            } catch (Exception $e2) {
                Log::error('❌ [PrayerBot] Failed to send error message to user', [
                    'chat_id' => $chatId,
                    'error' => $e2->getMessage()
                ]);
            }
            return;
        }

        // تغییر state به منتظر کد
        $this->prayerBotService->setState(
            $botUser->id,
            $botMotherId,
            'email_waiting_code',
            ['email' => $text],
            10 // 10 دقیقه
        );

        $message = "✅ " . trans('bot.email_code_sent') . "\n\n";
        $message .= trans('bot.email_enter_code') . "\n\n";
        $message .= "⏱️ " . trans('bot.email_code_expires') . ": 10 " . trans('bot.minutes');

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);
    }

    /**
     * پردازش دریافت کد تایید
     */
    protected function handleEmailVerificationCode(Telegram $bot, int $chatId, string $text, $state, $botUser, string $type): void
    {
        try {
            // چک کردن عدد بودن
            if (!is_numeric($text) || strlen($text) !== 6) {
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => trans('bot.email_code_invalid_format')
                ]);
                return;
            }

            // چک کردن کد
            if ($botUser->email_verification_code !== $text) {
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => trans('bot.email_code_incorrect')
                ]);
                return;
            }

            // چک کردن انقضا
            if ($botUser->email_verification_code_expires_at && $botUser->email_verification_code_expires_at->isPast()) {
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => trans('bot.email_code_expired')
                ]);
                
                // پاک کردن state
                try {
                    $this->prayerBotService->clearState($botUser->id);
                } catch (Exception $e) {
                    Log::error('❌ [PrayerBot] Error clearing state after code expiry', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage()
                    ]);
                }
                return;
            }

            // تایید ایمیل
            try {
                $botUser->email_verified_at = now();
                $botUser->email_verification_code = null;
                $botUser->email_verification_code_expires_at = null;
                
                // تولید توکن لغو اشتراک
                if (!$botUser->email_unsubscribe_token) {
                    $botUser->email_unsubscribe_token = bin2hex(random_bytes(32));
                }
                
                $botUser->save();

                Log::info('✅ [PrayerBot] Email verified in database', [
                    'chat_id' => $chatId,
                    'email' => $botUser->email
                ]);
            } catch (Exception $e) {
                Log::error('❌ [PrayerBot] Error saving email verification', [
                    'chat_id' => $chatId,
                    'email' => $botUser->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❌ " . trans('bot.email_verification_error') . "\n\n" . trans('bot.please_try_again')
                ]);
                return;
            }

            // پاک کردن state
            try {
                $this->prayerBotService->clearState($botUser->id);
            } catch (Exception $e) {
                Log::warning('⚠️ [PrayerBot] Error clearing state after verification', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage()
                ]);
                // ادامه می‌دهیم چون ایمیل تایید شده است
            }

            // ارسال پیام موفقیت
            try {
                $message = "✅ " . trans('bot.email_verified_success') . "\n\n";
                $message .= "📧 " . trans('bot.email_verified_message') . "\n";
                $message .= "📅 " . trans('bot.email_report_frequency') . ": " . trans('bot.' . ($botUser->email_report_frequency ?? 'weekly'));

                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $message
                ]);

                Log::info('✅ [PrayerBot] Email verification success message sent', [
                    'chat_id' => $chatId,
                    'email' => $botUser->email
                ]);
            } catch (Exception $e) {
                Log::error('❌ [PrayerBot] Error sending success message', [
                    'chat_id' => $chatId,
                    'email' => $botUser->email,
                    'error' => $e->getMessage()
                ]);
                // سعی می‌کنیم دوباره ارسال کنیم
                try {
                    $bot->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ " . trans('bot.email_verified_success')
                    ]);
                } catch (Exception $e2) {
                    Log::error('❌ [PrayerBot] Failed to send success message after retry', [
                        'chat_id' => $chatId,
                        'error' => $e2->getMessage()
                    ]);
                }
            }

        } catch (Exception $e) {
            Log::error('❌ [PrayerBot] Unexpected error in handleEmailVerificationCode', [
                'chat_id' => $chatId,
                'text' => $text,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // ارسال پیام خطا به کاربر
            try {
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❌ " . trans('bot.email_verification_error') . "\n\n" . trans('bot.please_try_again')
                ]);
            } catch (Exception $e2) {
                Log::error('❌ [PrayerBot] Failed to send error message to user', [
                    'chat_id' => $chatId,
                    'error' => $e2->getMessage()
                ]);
            }
        }
    }

    /**
     * به‌روزرسانی فرکانس گزارش ایمیل
     */
    protected function updateEmailFrequency(Telegram $bot, int $chatId, $botUser, string $frequency): void
    {
        $botUser->email_report_frequency = $frequency;
        $botUser->save();

        $message = "✅ " . trans('bot.email_frequency_updated') . "\n\n";
        $message .= "📅 " . trans('bot.email_settings_frequency') . ": " . trans('bot.' . $frequency);

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);

        Log::info('📅 [PrayerBot] Email frequency updated', [
            'chat_id' => $chatId,
            'frequency' => $frequency
        ]);
    }

    /**
     * لغو اشتراک از طریق ربات
     */
    protected function handleUnsubscribeFromBot(Telegram $bot, int $chatId, $botUser): void
    {
        $botUser->email_report_frequency = 'never';
        $botUser->save();

        $message = "🔕 " . trans('bot.email_unsubscribed') . "\n\n";
        $message .= trans('bot.email_unsubscribed_message');

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);

        Log::info('🔕 [PrayerBot] User unsubscribed from bot', [
            'chat_id' => $chatId
        ]);
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
