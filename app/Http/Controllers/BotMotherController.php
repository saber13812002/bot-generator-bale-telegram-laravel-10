<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\BotMotherStateHelper;
use App\Helpers\LogHelper;
use App\Helpers\TokenHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Http\Requests\BotRequest;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;
use Gap\SDP\Api as GapBot;


class BotMotherController extends Controller
{
    /**
     * Handle bot mother webhook with interactive bot creation
     * @throws Exception
     */
    public function botMotherWebhook(BotRequest $request)
    {
        if ($request->has('origin') && $request->has('bot_mother_id')) {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            if ($type == 'bale') {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_TELEGRAM"));
            }

            // چک کردن ادمین بودن کاربر
            $chatId = $bot->ChatID();
            if (!AdminHelper::isAdmin($chatId)) {
                $message = "❌ شما دسترسی به این ربات ندارید.\nاین ربات فقط برای ادمین‌ها قابل استفاده است.";
                BotHelper::sendMessage($bot, $message);
                return;
            }

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            // Get raw update data for callback query handling
            $update = $request->json()->all() ?? $request->all();
            
            // Handle callback query (for inline buttons)
            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $callbackData = $callbackQuery['data'] ?? '';
                $callbackChatId = $callbackQuery['message']['chat']['id'] ?? $chatId;
                $currentState = BotMotherStateHelper::getCurrentState($callbackChatId);
                $stateData = BotMotherStateHelper::getData($callbackChatId);
                
                // Handle language selection from callback
                if ($currentState == BotMotherStateHelper::STATE_WAITING_LANGUAGE && str_starts_with($callbackData, 'lang_')) {
                    $selectedLanguage = str_replace('lang_', '', $callbackData);
                    
                    // Answer callback query first
                    $bot->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery['id'],
                        'text' => 'زبان انتخاب شد',
                    ]);
                    
                    // Handle language selection - use callbackChatId for state management
                    // But we need to send message to the same chat
                    $this->handleLanguageSelectionFromCallback($bot, $selectedLanguage, $stateData, $type, $botMotherId, $callbackChatId);
                    return;
                }
            }
            
            $text = $bot->Text();
            $currentState = BotMotherStateHelper::getCurrentState($chatId);
            $stateData = BotMotherStateHelper::getData($chatId);

            // Handle /start or "ساختن" command
            if ($text == '/start' || $text == 'ساختن' || $text == '/new' || strtolower($text) == 'new') {
                $this->handleStart($bot, $type, $botMotherId);
            }
            // Handle duplicate bots list command
            else if ($text == '/duplicates' || $text == '/تکراری' || $text == 'تکراری' || strtolower($text) == 'duplicates') {
                $this->handleDuplicateBots($bot, $type);
            }
            // Handle endpoint selection
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_ENDPOINT_SELECTION) {
                $this->handleEndpointSelection($bot, $text, $type, $botMotherId);
            }
            // Handle type selection (telegram/bale)
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_TYPE) {
                $this->handleTypeSelection($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle language selection
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_LANGUAGE) {
                $this->handleLanguageSelection($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle token input
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_TOKEN) {
                $this->handleTokenInput($bot, $text, $stateData, $type, $botMotherId);
            }
            // Legacy support - if language is provided in request
            else if ($request->has('language')) {
                $message = trans('bot.please wait');
                BotHelper::sendMessage($bot, $message);
                $language = $request->input('language');
                BotHelper::handleRequestBotMother($bot, $type, $language, $botMotherId);
            }
            // Unknown command
            else {
                $message = "❓ دستور نامعتبر است.\n\n";
                $message .= "برای شروع، دستور /start یا 'ساختن' را ارسال کنید.\n";
                $message .= "برای مشاهده ربات‌های تکراری، دستور /duplicates یا 'تکراری' را ارسال کنید.";
                BotHelper::sendMessage($bot, $message);
            }
        }
    }


    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function getIdMother(BotRequest $request)
    {
        if ($request->has('origin') && $request->has('bot_mother_id') && $request->has('token')) {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            if ($type == 'bale') {
                $bot = new Telegram($request->input('token'), 'bale');
            } else if ($type == 'telegram') {
                $bot = new Telegram($request->input('token'));
            } else if ($type == 'gap') {
                $bot = new GapBot($request->input('token'), $request);
            }


            if ($request->has('language')) {
                $message = $this->getChatIdByType($bot);
                $message .= $this->getOtherBots();
                BotHelper::sendMessage($bot, $message);
            }
            LogHelper::log($request, $type, $bot);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function childrenMessageBroadcasterWebhook(Request $request)
    {
        $type = 'bale';
        $baleMotherBot = $this->getMotherBotByType($type);
//        dd(json_decode($request->getContent()), $baleMotherBot);
        $chat_id = $baleMotherBot->ChatID();
        $text = $baleMotherBot->Text();
        $firstName = $baleMotherBot->FirstName();
        $lastName = $baleMotherBot->LastName();
//        dd($firstName);
        if (config('app.env') == 'local') {
            $this->sendMessageRequestContent($chat_id, $request, $baleMotherBot);
        }

        if ($request->has('bot_token') && $request->has('bot_user_name')) {
            $bot_token = $request->input('bot_token');
            $bot_user_name = $request->input('bot_user_name');

            try {
                $botItem = Bot::query()->whereBaleBotName($bot_user_name)
                    ->whereBaleBotToken($bot_token)
                    ->get()
                    ->firstOrFail();
            } catch (Exception $e) {
                BotHelper::sendMessageToSuperAdmin('وب هوک ارسالی به سرور برای روبات بله قادر به تشخیص توکن و یوزرنیم روبات نیست', $type);
                Log::error($e->getMessage());
//                throw $e;
            }
            // TODO: count check
            if (config('app.env') == 'local') {
                $this->sendDbIdMessage($chat_id, $botItem, $baleMotherBot);
            }

            $bot = new Telegram($botItem->bale_bot_token, $type);

            $user = BotUsers::firstOrCreate([
                'chat_id' => $chat_id,
                'bot_id' => $botItem->id,
                'origin' => $type
            ]);

            if ($user->updated_at == $user->created_at) {
                $message = 'وضعیت شما هنوز توسط ادمین روبات تایید نشده است.';
                $message .= 'وضعیت شما در حال بررسی است، پس از تایید مدیر روبات اطلاع داده خواهد شد';
                BotHelper::sendMessage($bot, $message);
                $bale_owner_chat_id = $botItem->bale_owner_chat_id;
                $content = [
                    'chat_id' => $bale_owner_chat_id,
                    'parse_mode' => "html",
                    'text' => 'لطفا روی این دکمه کلیک کنید و فلانی را تایید کنید که بتواند از روبات استفاده کند:'
                ];
                $baleMotherBot->sendMessage($content);
                $content = ['chat_id' => $bale_owner_chat_id, 'text' => config('bot.childbotapproveurl') . '?origin=' . $type . '&chat_id=' . $chat_id . '&bot_id=' . $botItem->id . '&token=' . $botItem->bale_bot_token];
                $baleMotherBot->sendMessage($content);
            } else {
                if ($user->status == 'active' && !str_starts_with($text, "/")) {
                    // TODO: send telegram who's telegram
                    $users = BotUsers::query()->select('chat_id')->whereBotId($botItem->id)
                        ->whereOrigin($type)
                        ->whereStatus('active')
                        ->get();

                    $chatIds = $users->pluck('chat_id')->toArray();
                    $pos = array_search($chat_id . '', $chatIds);
                    unset($chatIds[$pos]);
//                    $array_without_strawberries = array_diff($userList, array($chat_id . ''));


                    foreach ($chatIds as $chatId) {
                        $content = ['chat_id' => $chatId, 'text' => $text . "
متن بالا
از طرف:
" . $firstName . "
" . $lastName
                        ];
                        $bot->sendMessage($content);
                    }


                    BotHelper::sendMessage($bot, 'شما کاربر فعال هستید پیام شما برای همه اعضا بغیر از خودتان ارسال شد');
                }
            }
        } else {
            $content = ['chat_id' => $chat_id, 'text' => 'حاجی توکن درست توی وب هوک ست نشده. چکنیم به ادمین خبر بده @sabertaba'];
            $baleMotherBot->sendMessage($content);
        }

    }


    /**
     * Show the form for creating a new resource.
     */
    public
    function rss(Request $request)
    {
        //asdkjfalkdjf

    }

    /**
     * Show the form for creating a new resource.
     */
    public
    function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public
    function store(StoreBotRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public
    function show(Bot $bot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(Bot $bot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateBotRequest $request, Bot $bot)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(Bot $bot)
    {
        //
    }

    /**
     * @param string $type
     * @return Telegram
     */
    public
    function getMotherBotByType(string $type): Telegram
    {
        $bot_token = TokenHelper::getMotherBotToken($type);
        return new Telegram($bot_token, $type);
    }

    /**
     * @param mixed $chat_id
     * @param Request $request
     * @param Telegram $bale
     * @return array
     */
    public
    function sendMessageRequestContent(mixed $chat_id, Request $request, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getContent())];
        $bale->sendMessage($content);
        //TODO: strange things here
        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getQueryString())];
        $bale->sendMessage($content);
        return $content;
    }

    /**
     * @param mixed $chat_id
     * @param $botItem
     * @param Telegram $bale
     * @return array
     */
    public
    function sendDbIdMessage(mixed $chat_id, $botItem, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => $botItem->id];
        $bale->sendMessage($content);
        return $content;
    }

    /**
     * @param mixed $bot
     * @return string
     */
    public function getChatIdByType($bot): string
    {
        $message = trans('bot.your chat id') . "
: " . $bot->ChatID();

        return $message;
    }

    private function getOtherBots(): string
    {
        $configItems = config('bot.ourbots.getchatid');
        $message = "

" . trans("bot.bots.getchatidbot.our other bots") . ":
";

        foreach ($configItems as $configItemKey => $configItemValue) {
            $message .= $configItemKey . ":" . $configItemValue . "
";
        }

        return $message;

    }

    /**
     * Handle duplicate bots list command
     * 
     * @param Telegram $bot
     * @param string $type
     * @return void
     */
    private function handleDuplicateBots(Telegram $bot, string $type): void
    {
        try {
            // Find duplicate tokens in Telegram bots
            $telegramDuplicates = Bot::select('telegram_bot_token', DB::raw('COUNT(*) as count'), DB::raw('GROUP_CONCAT(id SEPARATOR ", ") as bot_ids'))
                ->whereNotNull('telegram_bot_token')
                ->where('telegram_bot_token', '!=', '')
                ->groupBy('telegram_bot_token')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            // Find duplicate tokens in Bale bots
            $baleDuplicates = Bot::select('bale_bot_token', DB::raw('COUNT(*) as count'), DB::raw('GROUP_CONCAT(id SEPARATOR ", ") as bot_ids'))
                ->whereNotNull('bale_bot_token')
                ->where('bale_bot_token', '!=', '')
                ->groupBy('bale_bot_token')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $message = "🔍 لیست ربات‌های تکراری:\n\n";

            if ($telegramDuplicates->isEmpty() && $baleDuplicates->isEmpty()) {
                $message .= "✅ هیچ ربات تکراری یافت نشد.\n";
                $message .= "همه ربات‌ها توکن‌های منحصر به فرد دارند.";
            } else {
                if ($telegramDuplicates->isNotEmpty()) {
                    $message .= "📱 ربات‌های تلگرام تکراری:\n";
                    foreach ($telegramDuplicates as $duplicate) {
                        $botIds = explode(',', $duplicate->bot_ids);
                        $tokenPreview = substr($duplicate->telegram_bot_token, 0, 15) . '...';
                        $message .= "• توکن: {$tokenPreview}\n";
                        $message .= "  تعداد: {$duplicate->count}\n";
                        $message .= "  Bot IDs: " . implode(', ', $botIds) . "\n\n";
                    }
                }

                if ($baleDuplicates->isNotEmpty()) {
                    $message .= "📱 ربات‌های بله تکراری:\n";
                    foreach ($baleDuplicates as $duplicate) {
                        $botIds = explode(',', $duplicate->bot_ids);
                        $tokenPreview = substr($duplicate->bale_bot_token, 0, 15) . '...';
                        $message .= "• توکن: {$tokenPreview}\n";
                        $message .= "  تعداد: {$duplicate->count}\n";
                        $message .= "  Bot IDs: " . implode(', ', $botIds) . "\n\n";
                    }
                }

                $message .= "💡 نکته: وقتی توکن تکراری ثبت می‌شود، ربات قبلی به‌روزرسانی می‌شود.\n";
            }

            BotHelper::sendMessage($bot, $message);

            // Log
            Log::info('Duplicate bots list requested', [
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'telegram_duplicates_count' => $telegramDuplicates->count(),
                'bale_duplicates_count' => $baleDuplicates->count(),
            ]);

        } catch (Exception $e) {
            $errorMessage = "❌ خطا در دریافت لیست ربات‌های تکراری:\n\n";
            $errorMessage .= $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);

            Log::error('Error getting duplicate bots list', [
                'error' => $e->getMessage(),
                'chat_id' => $bot->ChatID(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Handle start command - show endpoints list
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleStart(Telegram $bot, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        
        // Clear any previous state
        BotMotherStateHelper::clearState($chatId);
        
        $message = "🤖 ربات ساز\n\n";
        $message .= "با این ربات می‌توانید ربات‌های جدید بسازید و به endpoint های مختلف متصل کنید.\n\n";
        $message .= "📋 دستورات:\n";
        $message .= "/start یا 'ساختن' - شروع ساخت ربات جدید\n";
        $message .= "/duplicates یا 'تکراری' - مشاهده ربات‌های تکراری\n\n";
        $message .= WebhookEndpointHelper::getEndpointsListMessage();
        
        BotHelper::sendMessage($bot, $message);
        
        // Set state to waiting for endpoint selection
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_ENDPOINT_SELECTION, [
            'bot_mother_id' => $botMotherId,
            'type' => $type,
        ]);
    }

    /**
     * Handle endpoint selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleEndpointSelection(Telegram $bot, string $text, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $endpoints = WebhookEndpointHelper::getAvailableEndpoints();
        
        // Check if input is a number
        if (!is_numeric($text)) {
            $message = "❌ لطفاً شماره endpoint را ارسال کنید (مثلاً: 1)";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        $selectedIndex = (int)$text - 1;
        
        if ($selectedIndex < 0 || $selectedIndex >= count($endpoints)) {
            $message = "❌ شماره نامعتبر است. لطفاً شماره صحیح را ارسال کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        $selectedEndpoint = $endpoints[$selectedIndex];
        
        $message = "✅ Endpoint انتخاب شد:\n\n";
        $message .= "📝 نام: {$selectedEndpoint['name']}\n";
        $message .= "🔗 Route: {$selectedEndpoint['route']}\n";
        $message .= "📄 توضیحات: {$selectedEndpoint['description']}\n\n";
        $message .= "نوع ربات را انتخاب کنید:\n";
        $message .= "1. تلگرام (Telegram)\n";
        $message .= "2. بله (Bale)\n\n";
        $message .= "شماره نوع ربات را ارسال کنید:";
        
        BotHelper::sendMessage($bot, $message);
        
        // Set state to waiting for type selection
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_TYPE, [
            'bot_mother_id' => $botMotherId,
            'type' => $type,
            'endpoint_id' => $selectedEndpoint['id'],
            'endpoint' => $selectedEndpoint,
        ]);
    }

    /**
     * Handle type selection (telegram/bale)
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleTypeSelection(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        
        $selectedType = null;
        if ($text == '1' || strtolower($text) == 'telegram') {
            $selectedType = 'telegram';
        } else if ($text == '2' || strtolower($text) == 'bale') {
            $selectedType = 'bale';
        } else {
            $message = "❌ لطفاً 1 برای تلگرام یا 2 برای بله را ارسال کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        $endpoint = $stateData['endpoint'];
        
        // Check if endpoint requires language
        if ($endpoint['requires_language']) {
            $message = "✅ نوع ربات انتخاب شد: " . ($selectedType == 'telegram' ? 'تلگرام' : 'بله') . "\n\n";
            
            // Check if endpoint supports multiple languages
            if (isset($endpoint['supports_multiple_languages']) && $endpoint['supports_multiple_languages']) {
                // Show 15 languages with inline buttons
                $message .= "🌍 زبان را انتخاب کنید:\n\n";
                $languages = $this->getSupportedLanguages();
                
                // Create inline keyboard with language buttons
                $buttons = [];
                $row = [];
                foreach ($languages as $langCode => $langName) {
                    $row[] = $bot->buildInlineKeyBoardButton($langName, callback_data: 'lang_' . $langCode);
                    // هر 2 دکمه در یک ردیف
                    if (count($row) == 2) {
                        $buttons[] = $row;
                        $row = [];
                    }
                }
                // اضافه کردن ردیف آخر اگر خالی نبود
                if (!empty($row)) {
                    $buttons[] = $row;
                }
                
                $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
                BotHelper::sendKeyboardMessageToChatId($bot, $message, $inlineKeyboard, $chatId);
            } else {
                // Show only 2 languages (legacy)
                $message .= "زبان را انتخاب کنید:\n";
                $message .= "1. فارسی (fa)\n";
                $message .= "2. انگلیسی (en)\n\n";
                $message .= "شماره زبان را ارسال کنید:";
                
                BotHelper::sendMessage($bot, $message);
            }
            
            // Set state to waiting for language
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_LANGUAGE, array_merge($stateData, [
                'bot_type' => $selectedType,
            ]));
        } else {
            // If doesn't require language, ask for token directly
            $message = "✅ نوع ربات انتخاب شد: " . ($selectedType == 'telegram' ? 'تلگرام' : 'بله') . "\n\n";
            $message .= "لطفاً توکن ربات را ارسال کنید:";
            
            BotHelper::sendMessage($bot, $message);
            
            // Set state to waiting for token
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_TOKEN, array_merge($stateData, [
                'bot_type' => $selectedType,
                'language' => 'fa', // Default language
            ]));
        }
    }

    /**
     * Handle language selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleLanguageSelection(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $this->handleLanguageSelectionInternal($bot, $text, $stateData, $type, $botMotherId, $chatId);
    }

    /**
     * Handle language selection from callback query
     * 
     * @param Telegram $bot
     * @param string $selectedLanguage
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @param int|string $chatId
     * @return void
     */
    private function handleLanguageSelectionFromCallback(Telegram $bot, string $selectedLanguage, array $stateData, string $type, int $botMotherId, $chatId): void
    {
        $this->handleLanguageSelectionInternal($bot, $selectedLanguage, $stateData, $type, $botMotherId, $chatId);
    }

    /**
     * Internal method to handle language selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @param int|string $chatId
     * @return void
     */
    private function handleLanguageSelectionInternal(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId, $chatId): void
    {
        $selectedLanguage = 'fa'; // Default
        $languages = $this->getSupportedLanguages();
        
        // Check if text is a language code
        if (isset($languages[$text])) {
            $selectedLanguage = $text;
        } else if ($text == '1' || strtolower($text) == 'fa' || strtolower($text) == 'فارسی') {
            $selectedLanguage = 'fa';
        } else if ($text == '2' || strtolower($text) == 'en' || strtolower($text) == 'انگلیسی') {
            $selectedLanguage = 'en';
        }
        
        $languageName = $languages[$selectedLanguage] ?? $selectedLanguage;
        $message = "✅ زبان انتخاب شد: {$languageName}\n\n";
        $message .= "لطفاً توکن ربات را ارسال کنید:";
        
        // Send message to the specified chat ID
        BotHelper::sendMessageByChatId($bot, $chatId, $message);
        
        // Set state to waiting for token
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_TOKEN, array_merge($stateData, [
            'language' => $selectedLanguage,
        ]));
    }

    /**
     * Get list of supported languages with their display names
     * 
     * @return array
     */
    private function getSupportedLanguages(): array
    {
        return [
            'fa' => '🇮🇷 فارسی',
            'en' => '🇬🇧 English',
            'ar-IQ' => '🇮🇶 العربية (عراق)',
            'az' => '🇦🇿 Azərbaycan',
            'bs' => '🇧🇦 Bosanski',
            'de-DE' => '🇩🇪 Deutsch',
            'es' => '🇪🇸 Español',
            'fr' => '🇫🇷 Français',
            'he' => '🇮🇱 עברית',
            'pt-BR' => '🇧🇷 Português (Brasil)',
            'pt-PT' => '🇵🇹 Português (Portugal)',
            'ru' => '🇷🇺 Русский',
            'tr' => '🇹🇷 Türkçe',
            'ur' => '🇵🇰 اردو',
            'zh-CN' => '🇨🇳 中文',
        ];
    }

    /**
     * Handle token input and create bot
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     * @throws Exception
     */
    private function handleTokenInput(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $endpointId = $stateData['endpoint_id'];
        $botType = $stateData['bot_type'] ?? $type;
        $language = $stateData['language'] ?? 'fa';
        
        // Validate token
        if (!TokenHelper::isToken($text, $botType)) {
            $message = "❌ توکن نامعتبر است. لطفاً توکن صحیح را ارسال کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        $message = "⏳ در حال ساخت ربات و تنظیم webhook...\nلطفاً صبر کنید.";
        BotHelper::sendMessage($bot, $message);
        
        try {
            // Create bot instance with the provided token
            $newBot = new Telegram($text, $botType);
            
            // Get bot info
            $getMe = $newBot->getMe();
            
            if (!$getMe['ok']) {
                throw new Exception("خطا در دریافت اطلاعات ربات: " . ($getMe['description'] ?? 'Unknown error'));
            }
            
            // Check if bot with this token already exists
            $existingBot = null;
            $isDuplicate = false;
            
            if ($botType == 'bale') {
                $existingBot = Bot::where('bale_bot_token', $text)->first();
            } else {
                $existingBot = Bot::where('telegram_bot_token', $text)->first();
            }
            
            if ($existingBot) {
                // Bot exists, update it instead of creating new
                $isDuplicate = true;
                $botItem = $existingBot;
                $botItem->bot_mother_id = $botMotherId;
                
                if ($botType == 'bale') {
                    $botItem->bale_owner_chat_id = $chatId;
                    $botItem->bale_bot_name = $getMe['result']['username'] ?? null;
                    $botItem->bale_bot_token = $text;
                    $botItem->bale_get_me_api_response = json_encode($getMe['result']);
                    $botItem->bale_bot_status = 'Active';
                } else {
                    $botItem->telegram_owner_chat_id = $chatId;
                    $botItem->telegram_bot_name = $getMe['result']['username'] ?? null;
                    $botItem->telegram_bot_token = $text;
                    $botItem->telegram_get_me_api_response = json_encode($getMe['result']);
                    $botItem->telegram_bot_status = 'Active';
                }
            } else {
                // Create new bot
                $botItem = new Bot();
                $botItem->bot_mother_id = $botMotherId;
                
                if ($botType == 'bale') {
                    $botItem->bale_owner_chat_id = $chatId;
                    $botItem->bale_bot_name = $getMe['result']['username'] ?? null;
                    $botItem->bale_bot_token = $text;
                    $botItem->bale_get_me_api_response = json_encode($getMe['result']);
                    $botItem->bale_bot_status = 'Active';
                } else {
                    $botItem->telegram_owner_chat_id = $chatId;
                    $botItem->telegram_bot_name = $getMe['result']['username'] ?? null;
                    $botItem->telegram_bot_token = $text;
                    $botItem->telegram_get_me_api_response = json_encode($getMe['result']);
                    $botItem->telegram_bot_status = 'Active';
                }
            }
            
            $botItem->save();
            
            // Create webhook URL
            $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $botType, $language, $botMotherId);
            
            // Set webhook
            $setWebhookResult = $newBot->setWebhook($webhookUrl);
            
            if (!$setWebhookResult['ok']) {
                throw new Exception("خطا در تنظیم webhook: " . ($setWebhookResult['description'] ?? 'Unknown error'));
            }
            
            // Update webhook status in database
            if ($botType == 'bale') {
                $botItem->bale_webhook_is_set = 1;
            } else {
                $botItem->telegram_webhook_is_set = 1;
            }
            $botItem->save();
            
            // Verify webhook
            $webhookInfo = BotHelper::checkWebhookInfo($text, $botType);
            
            // Send success message
            if ($isDuplicate) {
                $successMessage = "⚠️ این توکن قبلاً ثبت شده است. ربات قبلی به‌روزرسانی شد.\n\n";
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• نام: @" . ($getMe['result']['username'] ?? 'N/A') . "\n";
                $successMessage .= "• نوع: " . ($botType == 'telegram' ? 'تلگرام' : 'بله') . "\n";
                $successMessage .= "• Bot ID قبلی: {$botItem->id}\n";
                $successMessage .= "• Endpoint: {$stateData['endpoint']['name']}\n";
                $successMessage .= "• Route: {$stateData['endpoint']['route']}\n";
                $successMessage .= "• زبان: " . ($language == 'fa' ? 'فارسی' : 'انگلیسی') . "\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\nبرای ساخت ربات جدید، /start را ارسال کنید.";
            } else {
                $successMessage = "✅ ربات با موفقیت ساخته و ثبت شد!\n\n";
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• نام: @" . ($getMe['result']['username'] ?? 'N/A') . "\n";
                $successMessage .= "• نوع: " . ($botType == 'telegram' ? 'تلگرام' : 'بله') . "\n";
                $successMessage .= "• Endpoint: {$stateData['endpoint']['name']}\n";
                $successMessage .= "• Route: {$stateData['endpoint']['route']}\n";
                $successMessage .= "• زبان: " . ($language == 'fa' ? 'فارسی' : 'انگلیسی') . "\n";
                $successMessage .= "• Bot ID: {$botItem->id}\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\nبرای ساخت ربات جدید، /start را ارسال کنید.";
            }
            
            BotHelper::sendMessage($bot, $successMessage);
            
            // Clear state
            BotMotherStateHelper::clearState($chatId);
            
            // Log success
            if ($isDuplicate) {
                Log::info('Bot updated (duplicate token) via Bot Mother', [
                    'bot_id' => $botItem->id,
                    'endpoint' => $endpointId,
                    'type' => $botType,
                    'chat_id' => $chatId,
                    'is_duplicate' => true,
                ]);
            } else {
                Log::info('Bot created successfully via Bot Mother', [
                    'bot_id' => $botItem->id,
                    'endpoint' => $endpointId,
                    'type' => $botType,
                    'chat_id' => $chatId,
                ]);
            }
            
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در ساخت ربات:\n\n";
            $errorMessage .= $e->getMessage() . "\n\n";
            $errorMessage .= "لطفاً دوباره تلاش کنید یا با ادمین تماس بگیرید.";
            
            BotHelper::sendMessage($bot, $errorMessage);
            
            // Log error
            Log::error('Error creating bot via Bot Mother', [
                'error' => $e->getMessage(),
                'endpoint' => $endpointId,
                'type' => $botType,
                'chat_id' => $chatId,
            ]);
        }
    }

}
