<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\BotUsers;
use App\Models\Personnel;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class PersonnelRegistrationController extends Controller
{
    /**
     * Handle personnel registration webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Personnel Registration Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            
            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('PERSONNEL_REGISTRATION_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('PERSONNEL_REGISTRATION_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Personnel Registration Bot - Webhook status check', [
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
            Log::info('📨 Personnel Registration Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type,
                'is_group' => $chatId < 0
            ]);

            // Get or create bot user
            $botUser = BotUsers::firstOrNew($bot->ChatID(), $botMotherId, $type);
            $registrationStep = $botUser->setting('registration_step', 'start');
            
            Log::info('👤 Personnel Registration Bot - User info', [
                'chat_id' => $chatId,
                'registration_step' => $registrationStep,
                'has_personnel_id' => !empty($botUser->setting('personnel_id'))
            ]);

            // Handle /start command
            if ($text == '/start') {
                Log::info('▶️ Personnel Registration Bot - Processing /start command', ['chat_id' => $chatId]);
                $this->handleStart($bot, $botUser, $type);
            } 
            // Handle registration steps
            else if ($registrationStep == 'waiting_first_name') {
                Log::info('📝 Personnel Registration Bot - Processing first name', ['chat_id' => $chatId]);
                $this->handleFirstName($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_last_name') {
                Log::info('📝 Personnel Registration Bot - Processing last name', ['chat_id' => $chatId]);
                $this->handleLastName($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_national_code') {
                Log::info('📝 Personnel Registration Bot - Processing national code', ['chat_id' => $chatId]);
                $this->handleNationalCode($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_phone_number') {
                Log::info('📝 Personnel Registration Bot - Processing phone number', ['chat_id' => $chatId]);
                $this->handlePhoneNumber($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'confirming') {
                Log::info('📝 Personnel Registration Bot - Processing confirmation', ['chat_id' => $chatId]);
                $this->handleConfirmation($bot, $botUser, $text, $type);
            } 
            else {
                Log::info('▶️ Personnel Registration Bot - Default to start', ['chat_id' => $chatId]);
                $this->handleStart($bot, $botUser, $type);
            }
            
            Log::info('✅ Personnel Registration Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Personnel Registration Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $bot->ChatID() ?? null,
                'text' => $bot->Text() ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
            if (isset($bot) && isset($botUser)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره از دستور /start استفاده کنید.');
                // Reset registration state
                $botUser->settings(['registration_step' => 'start']);
            } elseif (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    /**
     * Handle start command
     */
    private function handleStart($bot, $botUser, $type)
    {
        // Check if user is already registered
        $personnelId = $botUser->setting('personnel_id');
        if ($personnelId) {
            $personnel = Personnel::find($personnelId);
            if ($personnel) {
                $message = "شما قبلاً ثبت‌نام کرده‌اید!\n\n";
                $message .= "نام: " . $personnel->first_name . " " . $personnel->last_name . "\n";
                $message .= "کد ملی: " . $personnel->national_code . "\n";
                $message .= "درجه: " . $personnel->rank . "\n\n";
                $message .= "برای شروع کار با ربات تسک، از لینک زیر استفاده کنید:\n\n";
                
                // Get messenger bot links
                $baleMessengerBotUsername = env('PERSONNEL_MESSENGER_BALE_BOT_USERNAME', '');
                $telegramMessengerBotUsername = env('PERSONNEL_MESSENGER_TELEGRAM_BOT_USERNAME', '');
                
                if ($baleMessengerBotUsername) {
                    $baleInviteLink = BotHelper::createChatInviteLink($baleMessengerBotUsername, 'personnel_id', $personnel->id, 'bale');
                    $message .= "🔵 بله: " . $baleInviteLink . "\n";
                }
                
                if ($telegramMessengerBotUsername) {
                    $telegramInviteLink = BotHelper::createChatInviteLink($telegramMessengerBotUsername, 'personnel_id', $personnel->id, 'telegram');
                    $message .= "🔷 تلگرام: " . $telegramInviteLink . "\n";
                }
                
                BotHelper::sendMessage($bot, $message);
                return;
            }
        }
        
        $message = "سلام خوش آمدید!\n\n";
        $message .= "برای ثبت‌نام در سیستم، لطفا اطلاعات زیر را وارد کنید:\n";
        $message .= "نام خود را وارد کنید:";
        
        BotHelper::sendMessage($bot, $message);
        
        $botUser->settings(['registration_step' => 'waiting_first_name']);
    }

    /**
     * Handle first name input
     */
    private function handleFirstName($bot, $botUser, $text, $type)
    {
        if (empty(trim($text))) {
            BotHelper::sendMessage($bot, "نام نمی‌تواند خالی باشد. لطفا نام خود را وارد کنید:");
            return;
        }

        $botUser->settings([
            'registration_step' => 'waiting_last_name',
            'registration_data' => array_merge($botUser->setting('registration_data', []), ['first_name' => trim($text)])
        ]);

        BotHelper::sendMessage($bot, "نام خانوادگی خود را وارد کنید:");
    }

    /**
     * Handle last name input
     */
    private function handleLastName($bot, $botUser, $text, $type)
    {
        if (empty(trim($text))) {
            BotHelper::sendMessage($bot, "نام خانوادگی نمی‌تواند خالی باشد. لطفا نام خانوادگی خود را وارد کنید:");
            return;
        }

        $botUser->settings([
            'registration_step' => 'waiting_national_code',
            'registration_data' => array_merge($botUser->setting('registration_data', []), ['last_name' => trim($text)])
        ]);

        BotHelper::sendMessage($bot, "کد ملی خود را وارد کنید (10 رقم):");
    }

    /**
     * Handle national code input
     */
    private function handleNationalCode($bot, $botUser, $text, $type)
    {
        $nationalCode = preg_replace('/[^0-9]/', '', $text);
        
        if (strlen($nationalCode) != 10) {
            BotHelper::sendMessage($bot, "کد ملی باید 10 رقم باشد. لطفا دوباره وارد کنید:");
            return;
        }

        // Check if national code already exists
        if (Personnel::where('national_code', $nationalCode)->exists()) {
            BotHelper::sendMessage($bot, "این کد ملی قبلا ثبت شده است. لطفا کد ملی صحیح را وارد کنید:");
            return;
        }

        $botUser->settings([
            'registration_step' => 'waiting_phone_number',
            'registration_data' => array_merge($botUser->setting('registration_data', []), ['national_code' => $nationalCode])
        ]);

        BotHelper::sendMessage($bot, "شماره موبایل خود را وارد کنید (به صورت استاندارد مانند 09123456789):");
    }

    /**
     * Handle phone number input
     */
    private function handlePhoneNumber($bot, $botUser, $text, $type)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $text);
        
        // Normalize phone number (remove leading zeros, add 0 if starts with 9)
        if (strlen($phoneNumber) == 10 && substr($phoneNumber, 0, 1) == '9') {
            $phoneNumber = '0' . $phoneNumber;
        }
        
        if (!preg_match('/^09[0-9]{9}$/', $phoneNumber)) {
            BotHelper::sendMessage($bot, "شماره موبایل باید به صورت استاندارد (مثل 09123456789) باشد. لطفا دوباره وارد کنید:");
            return;
        }

        $registrationData = array_merge($botUser->setting('registration_data', []), ['phone_number' => $phoneNumber]);
        
        $botUser->settings([
            'registration_step' => 'confirming',
            'registration_data' => $registrationData
        ]);

        // Show confirmation message
        $message = "لطفا اطلاعات زیر را تایید کنید:\n\n";
        $message .= "نام: " . $registrationData['first_name'] . "\n";
        $message .= "نام خانوادگی: " . $registrationData['last_name'] . "\n";
        $message .= "کد ملی: " . $registrationData['national_code'] . "\n";
        $message .= "شماره موبایل: " . $registrationData['phone_number'] . "\n\n";
        $message .= "برای تایید، کلمه 'تایید' را ارسال کنید.\n";
        $message .= "برای تصحیح اطلاعات، کلمه 'لغو' را ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle confirmation
     */
    private function handleConfirmation($bot, $botUser, $text, $type)
    {
        $text = mb_strtolower(trim($text));
        
        if ($text == 'تایید' || $text == 'تاييد') {
            $this->savePersonnel($bot, $botUser, $type);
        } 
        else if ($text == 'لغو') {
            $this->handleStart($bot, $botUser, $type);
        } 
        else {
            BotHelper::sendMessage($bot, "لطفا 'تایید' یا 'لغو' را ارسال کنید.");
        }
    }

    /**
     * Save personnel to database
     */
    private function savePersonnel($bot, $botUser, $type)
    {
        $registrationData = $botUser->setting('registration_data', []);
        
        if (!isset($registrationData['first_name']) || !isset($registrationData['last_name']) 
            || !isset($registrationData['national_code']) || !isset($registrationData['phone_number'])) {
            BotHelper::sendMessage($bot, "خطا در ثبت اطلاعات. لطفا دوباره تلاش کنید.");
            $this->handleStart($bot, $botUser, $type);
            return;
        }

        // Get Saber tenant
        $tenant = Tenant::where('tenant_name', 'صابر')->first();
        
        if (!$tenant) {
            BotHelper::sendMessage($bot, "خطا در سیستم. لطفا با مدیریت تماس بگیرید.");
            return;
        }

        try {
            // Check if personnel already exists
            $existingPersonnel = Personnel::where('national_code', $registrationData['national_code'])->first();
            
            if ($existingPersonnel) {
                BotHelper::sendMessage($bot, "این کد ملی قبلا ثبت شده است.");
                $this->handleStart($bot, $botUser, $type);
                return;
            }

            // Create personnel
            $personnel = Personnel::create([
                'first_name' => $registrationData['first_name'],
                'last_name' => $registrationData['last_name'],
                'national_code' => $registrationData['national_code'],
                'phone_number' => $registrationData['phone_number'],
                'tenant_id' => $tenant->id,
                'rank' => 'سرباز صفر',
            ]);

            // Clear registration data
            $botUser->settings([
                'registration_step' => 'completed',
                'personnel_id' => $personnel->id
            ]);

            // Send success message
            $message = "✅ ثبت‌نام شما با موفقیت انجام شد!\n\n";
            $message .= "درجه شما: سرباز صفر\n\n";
            
            // Get messenger bot links (these should be configured in env or config)
            // These are the messenger bots, NOT the registration bot
            $baleMessengerBotUsername = env('PERSONNEL_MESSENGER_BALE_BOT_USERNAME', '');
            $telegramMessengerBotUsername = env('PERSONNEL_MESSENGER_TELEGRAM_BOT_USERNAME', '');
            
            if ($baleMessengerBotUsername || $telegramMessengerBotUsername) {
                $message .= "لینک‌های ربات‌های اختصاصی شما:\n";
                
                if ($baleMessengerBotUsername) {
                    // Create invite link with personnel ID as parameter for messenger bot
                    $baleInviteLink = BotHelper::createChatInviteLink($baleMessengerBotUsername, 'personnel_id', $personnel->id, 'bale');
                    $message .= "🔵 بله: " . $baleInviteLink . "\n";
                }
                
                if ($telegramMessengerBotUsername) {
                    // Create invite link with personnel ID as parameter for messenger bot
                    $telegramInviteLink = BotHelper::createChatInviteLink($telegramMessengerBotUsername, 'personnel_id', $personnel->id, 'telegram');
                    $message .= "🔷 تلگرام: " . $telegramInviteLink . "\n";
                }
            }

            BotHelper::sendMessage($bot, $message);

            // Log the registration
            Log::info('✅ Personnel Registration Bot - Registration completed successfully', [
                'personnel_id' => $personnel->id,
                'national_code' => $personnel->national_code,
                'chat_id' => $bot->ChatID(),
                'first_name' => $personnel->first_name,
                'last_name' => $personnel->last_name
            ]);

        } catch (Exception $e) {
            Log::error('Error saving personnel: ' . $e->getMessage());
            BotHelper::sendMessage($bot, "خطا در ذخیره اطلاعات. لطفا دوباره تلاش کنید.");
        }
    }
}
