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

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            // Get or create bot user
            $botUser = BotUsers::firstOrNew($bot->ChatID(), $botMotherId, $type);

            $text = $bot->Text();
            $registrationStep = $botUser->setting('registration_step', 'start');

            // Handle /start command
            if ($text == '/start') {
                $this->handleStart($bot, $botUser, $type);
            } 
            // Handle registration steps
            else if ($registrationStep == 'waiting_first_name') {
                $this->handleFirstName($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_last_name') {
                $this->handleLastName($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_national_code') {
                $this->handleNationalCode($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'waiting_phone_number') {
                $this->handlePhoneNumber($bot, $botUser, $text, $type);
            } 
            else if ($registrationStep == 'confirming') {
                $this->handleConfirmation($bot, $botUser, $text, $type);
            } 
            else {
                $this->handleStart($bot, $botUser, $type);
            }

        } catch (Exception $e) {
            Log::error('Personnel registration error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'chat_id' => $bot->ChatID() ?? null,
                'text' => $bot->Text() ?? null
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
            
            // Get bot links (these should be configured in env or config)
            $baleBotUsername = env('PERSONNEL_BALE_BOT_USERNAME', '');
            $telegramBotUsername = env('PERSONNEL_TELEGRAM_BOT_USERNAME', '');
            
            if ($baleBotUsername || $telegramBotUsername) {
                $message .= "لینک‌های ربات‌های اختصاصی شما:\n";
                
                if ($baleBotUsername) {
                    $baleBotLink = config('bot.base_url.bale') . $baleBotUsername;
                    // Create invite link with personnel ID as parameter
                    $baleInviteLink = BotHelper::createChatInviteLink($baleBotUsername, 'personnel_id', $personnel->id, 'bale');
                    $message .= "🔵 بله: " . $baleInviteLink . "\n";
                }
                
                if ($telegramBotUsername) {
                    $telegramBotLink = config('bot.base_url.telegram') . $telegramBotUsername;
                    // Create invite link with personnel ID as parameter
                    $telegramInviteLink = BotHelper::createChatInviteLink($telegramBotUsername, 'personnel_id', $personnel->id, 'telegram');
                    $message .= "🔷 تلگرام: " . $telegramInviteLink . "\n";
                }
            }

            BotHelper::sendMessage($bot, $message);

            // Log the registration
            Log::info("Personnel registered: " . $personnel->id . " - " . $personnel->national_code);

        } catch (Exception $e) {
            Log::error('Error saving personnel: ' . $e->getMessage());
            BotHelper::sendMessage($bot, "خطا در ذخیره اطلاعات. لطفا دوباره تلاش کنید.");
        }
    }
}
