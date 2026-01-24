<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\EmailAdminHelper;
use App\Helpers\LogHelper;
use App\Helpers\ProHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\EmailService;
use App\Interfaces\Services\ProService;
use App\Interfaces\Services\ReverseGeocodingService;
use App\Interfaces\Services\WeatherAlertService;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Models\BotUsers;
use App\Models\Weather;
use App\Models\WeatherAlert;
use App\Models\WeatherHistory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram;

class WeatherController extends Controller
{
    private WeatherTomorrowApiService $weatherTomorrowApiService;
    private WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService;
    private ReverseGeocodingService $reverseGeocodingService;
    private WeatherAlertService $weatherAlertService;
    private ProService $proService;

    // Default location (Qom) if user hasn't set location
    private const DEFAULT_LATITUDE = 34.600209;
    private const DEFAULT_LONGITUDE = 50.828128;

    public function __construct(
        WeatherTomorrowApiService $weatherTomorrowApiService,
        WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService,
        ReverseGeocodingService $reverseGeocodingService,
        WeatherAlertService $weatherAlertService,
        ProService $proService
    ) {
        $this->weatherTomorrowApiService = $weatherTomorrowApiService;
        $this->weatherOpenWeatherMapApiService = $weatherOpenWeatherMapApiService;
        $this->reverseGeocodingService = $reverseGeocodingService;
        $this->weatherAlertService = $weatherAlertService;
        $this->proService = $proService;
    }

    /**
     * Get EmailService instance
     */
    private function getEmailService(): EmailService
    {
        return app(EmailService::class);
    }

    /**
     * تنظیم locale بر اساس زبان ربات
     */
    private function setLocale(Request $request): void
    {
        $botId = $request->input('bot_id');
        if ($botId) {
            $bot = \App\Models\Bot::find($botId);
            if ($bot && $bot->language_code) {
                App::setLocale($bot->language_code);
            }
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(BotRequest $request)
    {
        try {
            $this->setLocale($request);

            $type = $request->input('origin');
            $botId = $request->input('bot_id');
            $botMotherId = $request->input('bot_mother_id', 1);

            if (!$request->has('origin')) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('origin not specified in query string', null, 400);
            }

            // دریافت token از request یا دیتابیس
            $token = $this->getToken($request, $type, $botId);
            if (!$token) {
                Log::error('🌤 [Weather] Token not found', ['type' => $type, 'bot_id' => $botId]);
                return 0;
            }

            $bot = new Telegram($token, $type);
            $chatId = $bot->ChatID();
            $text = $bot->Text() ?? '';

            // دریافت یا ایجاد کاربر
            $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);

            // بررسی callback query
            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $botUser, $botId, $type);
                return 0;
            }

            // بررسی location message
            if (isset($update['message']['location'])) {
                $this->handleLocationMessage($bot, $update['message']['location'], $botUser, $type);
                return 0;
            }

            // پردازش دستورات
            if (str_starts_with($text, '/')) {
                $this->handleCommand($bot, $text, $botUser, $botId, $type, $request);
            } else {
                // اگر location نداریم، درخواست location
                if (!$botUser->hasLocation()) {
                    BotHelper::sendMessage($bot, trans('bot.location_not_set'));
                    return 0;
                }

                // پردازش متن عادی (مثلاً عدد برای forecasting)
                $this->handleTextMessage($bot, $text, $botUser, $botId, $type);
            }

            // لاگ
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            return 0;
        } catch (Exception $exception) {
            Log::error('🌤 [Weather] Error', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * دریافت token از request یا دیتابیس
     */
    private function getToken(Request $request, string $type, ?int $botId): ?string
    {
        // اولویت 1: از query string
        if ($request->has('token')) {
            return $request->input('token');
        }

        // اولویت 2: از دیتابیس
        if ($botId) {
            $bot = \App\Models\Bot::find($botId);
            if ($bot) {
                return $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            }
        }

        // اولویت 3: از env (برای backward compatibility)
        return $type === 'bale' 
            ? env("BOT_WEATHER_TOKEN_BALE") 
            : env("BOT_WEATHER_TOKEN_TELEGRAM");
    }

    /**
     * پردازش دستورات
     */
    private function handleCommand(Telegram $bot, string $text, BotUsers $botUser, ?int $botId, string $type, Request $request): void
    {
        $command = strtolower(trim($text));

        switch ($command) {
            case '/start':
                $this->handleStartCommand($bot, $botUser, $type);
                break;

            case '/location':
            case '/setlocation':
                $this->handleLocationCommand($bot, $type);
                break;

            case '/current':
                $this->handleCurrentCommand($bot, $botUser, $botId, $type);
                break;

            case '/forecasting':
                $this->handleForecastingCommand($bot, $botUser, $botId, $type);
                break;

            case '/alert':
            case '/alerts':
                $this->handleAlertListCommand($bot, $botUser, $botId, $type);
                break;

            case '/alert_add':
                $this->handleAlertAddCommand($bot, $botUser, $botId, $type);
                break;

            case '/email':
            case '/email_settings':
                $this->handleEmailCommand($bot, $botUser, $type);
                break;

            case '/pro':
                $this->handleProCommand($bot, $botUser, $botId, $type);
                break;

            case '/pro_buy':
                $this->handleProBuyCommand($bot, $botUser, $botId, $type);
                break;

            default:
                // اگر عدد است، برای forecasting استفاده می‌شود
                if (is_numeric($text) && intval($text) > 1 && intval($text) < 20) {
                    $this->handleForecastingCommand($bot, $botUser, $botId, $type, intval($text));
                } else {
                    $commands = StringHelper::getWeatherBotCommandsAsPostfixForMessages();
                    BotHelper::sendMessage($bot, trans('bot.command_not_found') . $commands);
                }
        }
    }

    /**
     * دستور /start
     */
    private function handleStartCommand(Telegram $bot, BotUsers $botUser, string $type): void
    {
        $message = trans('bot.welcome') . "\n\n";

        if (!$botUser->hasLocation()) {
            $message .= trans('bot.location_request');
            BotHelper::sendMessage($bot, $message);
        } else {
            $location = $botUser->getLocation();
            $message .= trans('bot.location_current') . ": " . ($location['address'] ?? "{$location['latitude']}, {$location['longitude']}") . "\n\n";
            $message .= StringHelper::getWeatherBotCommandsAsPostfixForMessages();
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * دستور /location
     */
    private function handleLocationCommand(Telegram $bot, string $type): void
    {
        $message = trans('bot.location_request');
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش location message
     */
    private function handleLocationMessage(Telegram $bot, array $locationData, BotUsers $botUser, string $type): void
    {
        $latitude = $locationData['latitude'] ?? null;
        $longitude = $locationData['longitude'] ?? null;

        if (!$latitude || !$longitude) {
            BotHelper::sendMessage($bot, trans('bot.location_invalid'));
            return;
        }

        // Reverse geocoding
        $addressInfo = $this->reverseGeocodingService->getAddressFromCoordinates($latitude, $longitude);
        $address = $addressInfo ? ($addressInfo['address'] ?? "{$latitude}, {$longitude}") : "{$latitude}, {$longitude}";

        // ذخیره location
        $botUser->setLocation($latitude, $longitude, $address);

        Log::info('📍 [Weather] Location saved', [
            'chat_id' => $bot->ChatID(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address
        ]);

        $message = trans('bot.location_saved') . "\n";
        $message .= trans('bot.location_address') . ": " . $address;
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * دستور /current
     */
    private function handleCurrentCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type): void
    {
        $location = $this->getUserLocation($bot, $botUser);
        if (!$location) {
            return;
        }

        try {
            $message = $this->weatherOpenWeatherMapApiService->getMessage($location['latitude'], $location['longitude']);
            $commands = StringHelper::getWeatherBotCommandsAsPostfixForMessages();
            BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
        } catch (Exception $e) {
            Log::error('🌤 [Weather] Error in /current', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, trans('bot.weather_error'));
        }
    }

    /**
     * دستور /forecasting
     */
    private function handleForecastingCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type, ?int $windSpeedLimit = null): void
    {
        $location = $this->getUserLocation($bot, $botUser);
        if (!$location) {
            return;
        }

        $text = $windSpeedLimit ? (string) $windSpeedLimit : "20";

        try {
            $message = $this->weatherTomorrowApiService->getMessage($text, $location['latitude'], $location['longitude']);
            
            if (empty($message)) {
                $message = trans('bot.no_weather_data');
            }

            $commands = StringHelper::getWeatherBotCommandsAsPostfixForMessages();
            BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
        } catch (Exception $e) {
            Log::error('🌤 [Weather] Error in /forecasting', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, trans('bot.weather_error'));
        }
    }

    /**
     * دستور /alert - لیست alerts
     */
    private function handleAlertListCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type): void
    {
        $alerts = $botUser->weatherAlerts()
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->where('is_active', true)
            ->get();

        $message = "🔔 " . trans('bot.alert_list') . "\n\n";

        if ($alerts->isEmpty()) {
            $message .= trans('bot.no_alerts');
        } else {
            foreach ($alerts as $alert) {
                $message .= $this->formatAlert($alert) . "\n";
            }
        }

        $isPro = $this->proService->isPro($botUser->id, $botId ?? 0);
        $activeCount = $botUser->getActiveAlertsCount($botId);
        $maxAlerts = $isPro ? trans('bot.unlimited') : 3;

        $message .= "\n" . trans('bot.alert_count', ['current' => $activeCount, 'max' => $maxAlerts]);

        $keyboard = [
            [['text' => '➕ ' . trans('bot.alert_add'), 'callback_data' => 'alert_add']]
        ];

        foreach ($alerts as $alert) {
            $keyboard[] = [['text' => '❌ ' . trans('bot.delete') . ' #' . $alert->id, 'callback_data' => 'alert_delete_' . $alert->id]];
        }

        $bot->sendMessage([
            'chat_id' => $bot->ChatID(),
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * دستور /alert_add
     */
    private function handleAlertAddCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type): void
    {
        $isPro = $this->proService->isPro($botUser->id, $botId ?? 0);
        $activeCount = $botUser->getActiveAlertsCount($botId);

        if (!$isPro && $activeCount >= 3) {
            $message = trans('bot.alert_limit_reached', ['max' => 3]) . "\n\n";
            $message .= ProHelper::getProMessage('unlimited_alerts');
            BotHelper::sendMessage($bot, $message);
            return;
        }

        $keyboard = [
            [
                ['text' => '🌡️ ' . trans('bot.alert_temperature'), 'callback_data' => 'alert_type_temperature'],
                ['text' => '🌧️ ' . trans('bot.alert_precipitation'), 'callback_data' => 'alert_type_precipitation'],
            ],
            [
                ['text' => '💨 ' . trans('bot.alert_wind'), 'callback_data' => 'alert_type_wind'],
                ['text' => '❄️ ' . trans('bot.alert_snow'), 'callback_data' => 'alert_type_snow'],
            ]
        ];

        $message = "➕ " . trans('bot.alert_add') . "\n\n";
        $message .= trans('bot.alert_select_type');

        $bot->sendMessage([
            'chat_id' => $bot->ChatID(),
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * دستور /email
     */
    private function handleEmailCommand(Telegram $bot, BotUsers $botUser, string $type): void
    {
        // استفاده از کد مشابه ربات نماز قضا
        $message = "⚙️ " . trans('bot.email_settings_title') . "\n\n";

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
            'chat_id' => $bot->ChatID(),
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * دستور /pro
     */
    private function handleProCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type): void
    {
        $isPro = $this->proService->isPro($botUser->id, $botId ?? 0);
        
        $message = "💎 " . trans('bot.pro_status') . "\n\n";

        if ($isPro) {
            $proUser = \App\Models\ProUser::where('bot_user_id', $botUser->id)
                ->when($botId, fn($q) => $q->where('bot_id', $botId))
                ->where('status', 'active')
                ->first();

            $message .= "✅ " . trans('bot.pro_active') . "\n";
            if ($proUser && $proUser->expires_at) {
                $message .= "📅 " . trans('bot.pro_expires_at') . ": " . $proUser->expires_at->format('Y-m-d');
            }
        } else {
            $message .= "❌ " . trans('bot.pro_not_active') . "\n\n";
            $message .= trans('bot.pro_features_description') . "\n\n";
            $message .= ProHelper::getProMessage();

            $keyboard = [
                [['text' => '💳 ' . trans('bot.pro_buy'), 'callback_data' => 'pro_buy']]
            ];

            $bot->sendMessage([
                'chat_id' => $bot->ChatID(),
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
            return;
        }

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * دستور /pro_buy
     */
    private function handleProBuyCommand(Telegram $bot, BotUsers $botUser, ?int $botId, string $type): void
    {
        $userIdentifier = "@" . ($bot->Username() ?? $bot->ChatID());

        $result = $this->proService->requestPurchase($botUser->id, $botId ?? 0, $userIdentifier);

        if (!$result['success']) {
            BotHelper::sendMessage($bot, $result['message'] ?? trans('bot.pro_purchase_error'));
            return;
        }

        // ارسال پیام به ادمین
        $adminContact = env('ADMIN_CONTACT_USERNAME', '@sabertaba');
        $adminMessage = "💳 درخواست خرید Pro\n\n";
        $adminMessage .= "👤 User ID: {$userIdentifier}\n";
        $adminMessage .= "💬 Chat ID: " . $bot->ChatID() . "\n";
        $adminMessage .= "🤖 Bot ID: {$botId}\n";
        $adminMessage .= "🆔 Request ID: " . $result['request_id'] . "\n\n";
        $adminMessage .= "لطفاً با کاربر تماس بگیرید و پس از واریز وجه، با دستور زیر تایید کنید:\n";
        $adminMessage .= "/pro_confirm " . $result['request_id'];

        // ارسال به همه ادمین‌ها
        try {
            \App\Helpers\EmailAdminHelper::sendToAllAdmins($adminMessage, $type);
            Log::info('💳 [Pro] Purchase request sent to admins', [
                'request_id' => $result['request_id'],
                'admin_contact' => $adminContact,
                'type' => $type
            ]);
        } catch (Exception $e) {
            Log::error('💳 [Pro] Error sending to admins', ['error' => $e->getMessage()]);
        }

        $message = trans('bot.pro_purchase_requested') . "\n\n";
        $message .= trans('bot.pro_contact_admin', ['admin' => $adminContact]) . "\n\n";
        $message .= trans('bot.pro_payment_info');

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * پردازش متن عادی
     */
    private function handleTextMessage(Telegram $bot, string $text, BotUsers $botUser, ?int $botId, string $type): void
    {
        // اگر عدد است، برای forecasting استفاده می‌شود
        if (is_numeric($text) && intval($text) > 1 && intval($text) < 20) {
            $this->handleForecastingCommand($bot, $botUser, $botId, $type, intval($text));
        } else {
            // پیش‌فرض forecasting با مقدار 20
            $this->handleForecastingCommand($bot, $botUser, $botId, $type, 20);
        }
    }

    /**
     * دریافت location کاربر یا پیش‌فرض
     */
    private function getUserLocation(Telegram $bot, BotUsers $botUser): ?array
    {
        if ($botUser->hasLocation()) {
            return $botUser->getLocation();
        }

        // استفاده از location پیش‌فرض
        BotHelper::sendMessage($bot, trans('bot.location_not_set'));
        return [
            'latitude' => self::DEFAULT_LATITUDE,
            'longitude' => self::DEFAULT_LONGITUDE,
            'address' => 'Qom, Iran (Default)'
        ];
    }

    /**
     * پردازش callback query
     */
    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, BotUsers $botUser, ?int $botId, string $type): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery['id'],
            'text' => ''
        ]);

        // پردازش callback data
        if (str_starts_with($data, 'alert_type_')) {
            $alertType = str_replace('alert_type_', '', $data);
            $this->handleAlertTypeSelection($bot, $botUser, $botId, $alertType, $type);
        } elseif (str_starts_with($data, 'alert_delete_')) {
            $alertId = (int) str_replace('alert_delete_', '', $data);
            $this->handleAlertDelete($bot, $botUser, $alertId, $type);
        } elseif ($data === 'alert_add') {
            $this->handleAlertAddCommand($bot, $botUser, $botId, $type);
        } elseif ($data === 'pro_buy') {
            $this->handleProBuyCommand($bot, $botUser, $botId, $type);
        } elseif (str_starts_with($data, 'email_')) {
            $this->handleEmailCallback($bot, $botUser, $data, $type);
        }
    }

    /**
     * انتخاب نوع alert
     */
    private function handleAlertTypeSelection(Telegram $bot, BotUsers $botUser, ?int $botId, string $alertType, string $type): void
    {
        $message = trans("bot.alert_set_threshold_{$alertType}") . "\n\n";
        $message .= trans('bot.alert_example_threshold');

        BotHelper::sendMessage($bot, $message);
        
        // باید state را set کنیم تا threshold را دریافت کنیم
        // برای سادگی، از کاربر می‌خواهیم threshold را به صورت "type:value" ارسال کند
    }

    /**
     * حذف alert
     */
    private function handleAlertDelete(Telegram $bot, BotUsers $botUser, int $alertId, string $type): void
    {
        $alert = WeatherAlert::where('id', $alertId)
            ->where('bot_user_id', $botUser->id)
            ->first();

        if (!$alert) {
            BotHelper::sendMessage($bot, trans('bot.alert_not_found'));
            return;
        }

        $alert->is_active = false;
        $alert->save();

        BotHelper::sendMessage($bot, trans('bot.alert_deleted'));
        
        // به‌روزرسانی لیست alerts
        $this->handleAlertListCommand($bot, $botUser, $alert->bot_id, $type);
    }

    /**
     * پردازش callback های email
     */
    private function handleEmailCallback(Telegram $bot, BotUsers $botUser, string $data, string $type): void
    {
        // استفاده از کد مشابه ربات نماز قضا
        // این باید state management داشته باشد
    }

    /**
     * فرمت کردن alert برای نمایش
     */
    private function formatAlert(WeatherAlert $alert): string
    {
        $type = trans("bot.alert_type_{$alert->alert_type}");
        $comparison = trans("bot.alert_comparison_{$alert->comparison_type}");
        $threshold = $alert->threshold_value;
        $hour = $alert->time_hour !== null ? " ساعت {$alert->time_hour}" : "";

        return "• {$type} - {$comparison} {$threshold}{$hour}";
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreweatherRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Weather $weather)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Weather $weather)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateweatherRequest $request, Weather $weather)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Weather $weather)
    {
        //
    }
}
