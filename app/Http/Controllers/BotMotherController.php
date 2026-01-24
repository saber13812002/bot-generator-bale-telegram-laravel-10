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
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\PresenterBot;
use App\Models\PsychologyTestBot;
use App\Models\PsychologyTestCategory;
use App\Models\PsychologyTestQuestion;
use App\Models\PsychologyTestBotAdmin;
use App\Services\BotMessageBroadcastService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
        Log::info('🤖 [BotMother] Webhook received', [
            'has_origin' => $request->has('origin'),
            'has_bot_mother_id' => $request->has('bot_mother_id'),
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
        ]);

        if ($request->has('origin') && $request->has('bot_mother_id')) {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_BALE");
                Log::info('🤖 [BotMother] Creating Bale bot instance', [
                    'has_custom_token' => $request->has('token'),
                    'using_env_token' => !$request->has('token'),
                ]);
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_TELEGRAM");
                $bot = new Telegram($token);
            }

            // چک کردن ادمین بودن کاربر
            $chatId = $bot->ChatID();
            Log::info('🤖 [BotMother] Checking admin status', [
                'chat_id' => $chatId,
                'type' => $type,
            ]);
            
            if (!AdminHelper::isAdmin($chatId)) {
                Log::warning('⚠️ [BotMother] User is not admin', [
                    'chat_id' => $chatId,
                ]);
                $message = "❌ شما دسترسی به این ربات ندارید.\nاین ربات فقط برای ادمین‌ها قابل استفاده است.";
                BotHelper::sendMessage($bot, $message);
                return;
            }
            
            Log::info('✅ [BotMother] User is admin, processing request', [
                'chat_id' => $chatId,
            ]);

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
                
                // Handle broadcast language selection from callback
                if ($currentState == BotMotherStateHelper::STATE_WAITING_BROADCAST_LANGUAGE && str_starts_with($callbackData, 'broadcast_lang_')) {
                    $selectedLanguage = str_replace('broadcast_lang_', '', $callbackData);
                    
                    // Answer callback query first
                    $bot->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery['id'],
                        'text' => 'زبان انتخاب شد',
                    ]);
                    
                    // Handle broadcast language selection
                    $this->handleBroadcastLanguageSelection($bot, $selectedLanguage, $stateData, $type, $botMotherId);
                    return;
                }
                
                // Handle Quran bots language selection from callback
                if ($currentState == BotMotherStateHelper::STATE_WAITING_QURAN_BOTS_LANGUAGE && str_starts_with($callbackData, 'quran_bots_lang_')) {
                    $selectedLanguage = str_replace('quran_bots_lang_', '', $callbackData);
                    
                    // Answer callback query first
                    $bot->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery['id'],
                        'text' => 'زبان انتخاب شد',
                    ]);
                    
                    // Handle Quran bots language selection
                    $this->handleQuranBotsLanguageSelection($bot, $selectedLanguage, $stateData, $type, $botMotherId);
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
            // Handle statistics command
            else if ($text == '/statistics' || $text == '/استاتستیکس' || $text == 'استاتستیکس' || strtolower($text) == 'statistics') {
                $this->handleStatistics($bot, $type, $botMotherId);
            }
            // Handle broadcast command
            else if ($text == '/broadcast' || $text == '/ارسال_همگانی' || $text == 'ارسال همگانی' || strtolower($text) == 'broadcast') {
                $this->handleBroadcastStart($bot, $type, $botMotherId);
            }
            // Handle help command
            else if ($text == '/help' || $text == '/راهنما' || $text == 'راهنما' || $text == 'help' || strtolower($text) == 'help') {
                $this->handleHelp($bot, $type);
            }
            // Handle clear cache command
            else if ($text == '/clear_cache' || $text == '/پاک_کش' || $text == 'پاک کش' || strtolower($text) == 'clear_cache' || strtolower($text) == 'clear cache') {
                $this->handleClearCache($bot, $type, $botMotherId);
            }
            // Handle logs command
            else if ($text == '/logs' || $text == '/لاگ' || $text == 'لاگ' || strtolower($text) == 'logs') {
                $this->handleLogs($bot, $type);
            }
            // Handle Quran bots introduction command
            else if ($text == '/quran_bots' || $text == '/ربات_قرآن' || $text == 'ربات قرآن' || strtolower($text) == 'quran_bots' || strtolower($text) == 'quran bots') {
                $this->handleQuranBotsIntroduction($bot, $type, $botMotherId);
            }
            // Handle Quran bots language selection
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_QURAN_BOTS_LANGUAGE) {
                $this->handleQuranBotsLanguageSelection($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle Quran bots source selection
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_QURAN_BOTS_SOURCE) {
                $this->handleQuranBotsSourceSelection($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle broadcast language selection
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_BROADCAST_LANGUAGE) {
                $this->handleBroadcastLanguageSelection($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle broadcast message input
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_BROADCAST_MESSAGE) {
                $this->handleBroadcastMessageInput($bot, $text, $stateData, $type, $botMotherId);
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
            // Handle presenter bot content input
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_PRESENTER_CONTENT) {
                $this->handlePresenterContentInput($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle psychology test questions input
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_PSYCHOLOGY_QUESTIONS) {
                $this->handlePsychologyQuestionsInput($bot, $text, $stateData, $type, $botMotherId);
            }
            // Handle category descriptions input
            else if ($currentState == BotMotherStateHelper::STATE_WAITING_CATEGORY_DESCRIPTIONS) {
                $this->handleCategoryDescriptionsInput($bot, $text, $stateData, $type, $botMotherId);
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
                $message .= "برای شروع مجدد ربات، /start را ارسال کنید.\n\n";
                $message .= "برای مشاهده لیست دستورات، /help را ارسال کنید.";
                $message .= "برای مشاهده آماردهای ربات، /statistics را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های تکراری، /duplicates را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های جدید، /new را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های فعال، /active را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های غیر فعال، /inactive را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های حذف شده، /deleted را ارسال کنید.";
                $message .= "برای مشاهده لیست ربات‌های غیر فعال، /inactive را ارسال کنید.";
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
        
        // نمایش دستور help
        $this->sendHelpMessage($bot, $type);
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
        $message .= WebhookEndpointHelper::getEndpointsListMessage();
        $message .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 برای مشاهده لیست کامل دستورات: /help";
        
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
            
            // برای ربات هواشناسی فقط فارسی و انگلیسی
            if ($endpointId === 'weather-bot') {
                $message .= "🌍 زبان را انتخاب کنید:\n\n";
                $languages = [
                    'fa' => '🇮🇷 فارسی',
                    'en' => '🇬🇧 English',
                ];
                
                $buttons = [
                    [
                        $bot->buildInlineKeyBoardButton($languages['fa'], callback_data: 'lang_fa'),
                        $bot->buildInlineKeyBoardButton($languages['en'], callback_data: 'lang_en'),
                    ]
                ];
                
                $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
                BotHelper::sendKeyboardMessageToChatId($bot, $message, $inlineKeyboard, $chatId);
            }
            // Check if endpoint supports multiple languages
            elseif (isset($endpoint['supports_multiple_languages']) && $endpoint['supports_multiple_languages']) {
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
            'it' => '🇮🇹 Italiano',
            'id' => '🇮🇩 Bahasa Indonesia',
            'sw' => '🇰🇪 Kiswahili',
            'pt-BR' => '🇧🇷 Português (Brasil)',
            'pt-PT' => '🇵🇹 Português (Portugal)',
            'ru' => '🇷🇺 Русский',
            'tr' => '🇹🇷 Türkçe',
            'ur' => '🇵🇰 اردو',
            'zh-CN' => '🇨🇳 中文',
        ];
    }

    /**
     * Get display name for a language code
     * 
     * @param string $languageCode
     * @return string
     */
    private function getLanguageDisplayName(string $languageCode): string
    {
        $languages = $this->getSupportedLanguages();
        return $languages[$languageCode] ?? $languageCode;
    }

    /**
     * Normalize language code for statistics
     * Converts codes like ar-IQ -> ar, de-DE -> de, zh-CN -> zh
     * 
     * @param string $languageCode
     * @return string
     */
    private function normalizeLanguageCode(string $languageCode): string
    {
        if (!$languageCode) {
            return 'fa'; // default
        }
        
        // تبدیل ar-IQ -> ar, de-DE -> de, etc.
        if (strpos($languageCode, '-') !== false) {
            return explode('-', $languageCode)[0];
        }
        
        return $languageCode;
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
            
            // تنظیم endpoint_id، language_code و type برای ارتباط با جدول webhook_endpoints
            $botItem->endpoint_id = $endpointId;
            $botItem->language_code = $language;
            $botItem->type = $botType;
            
            // Validation: بررسی اینکه ربات اطلاعات کافی دارد
            $validationErrors = [];
            
            if ($botType == 'telegram') {
                if (empty($botItem->telegram_bot_token)) {
                    $validationErrors[] = 'telegram_bot_token نمی‌تواند خالی باشد';
                }
                if (empty($botItem->telegram_bot_name)) {
                    $validationErrors[] = 'telegram_bot_name نمی‌تواند خالی باشد';
                }
            } elseif ($botType == 'bale') {
                if (empty($botItem->bale_bot_token)) {
                    $validationErrors[] = 'bale_bot_token نمی‌تواند خالی باشد';
                }
                if (empty($botItem->bale_bot_name)) {
                    $validationErrors[] = 'bale_bot_name نمی‌تواند خالی باشد';
                }
            }
            
            if (empty($botItem->bot_mother_id) || $botItem->bot_mother_id <= 0) {
                $validationErrors[] = 'bot_mother_id باید بزرگتر از 0 باشد';
            }
            
            // اگر خطای validation وجود داشت، ربات را ذخیره نکن
            if (!empty($validationErrors)) {
                $errorMessage = "❌ خطا در ثبت ربات:\n\n";
                foreach ($validationErrors as $error) {
                    $errorMessage .= "• {$error}\n";
                }
                $errorMessage .= "\nلطفاً دوباره تلاش کنید.";
                BotHelper::sendMessage($bot, $errorMessage);
                
                Log::error('Bot validation failed', [
                    'chat_id' => $chatId,
                    'bot_type' => $botType,
                    'errors' => $validationErrors,
                ]);
                return;
            }
            
            $botItem->save();
            
            // Check if this is presenter bot - if so, ask for content
            if ($endpointId == 'webhook-presenter-bot') {
                // Set state to wait for content
                BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_PRESENTER_CONTENT, array_merge($stateData, [
                    'bot_id' => $botItem->id,
                    'content_lines' => [], // برای جمع‌آوری خطوط
                ]));
                
                $message = "✅ ربات با موفقیت ثبت شد!\n\n";
                $message .= "📝 حالا لطفاً محتوای ربات را ارسال کنید.\n";
                $message .= "می‌توانید محتوا را در یک یا چند پیام ارسال کنید.\n";
                $message .= "هر خط با Enter (\\n) از خط بعدی جدا می‌شود.\n";
                $message .= "وقتی تمام محتوا را ارسال کردید، کلمه 'پایان' را ارسال کنید.\n\n";
                $message .= "💡 نکته: خطوط خالی و فاصله‌های اضافی به صورت خودکار حذف می‌شوند.";
                
                BotHelper::sendMessage($bot, $message);
                return; // Return early - don't set webhook yet
            }
            
            // Check if this is psychology test bot - if so, create bot record and ask for questions
            if ($endpointId == 'webhook-psychology-test') {
                // Create psychology test bot record
                $psychologyTestBot = PsychologyTestBot::create([
                    'bot_id' => $botItem->id,
                    'title' => 'تست‌های روانشناسی',
                    'description' => null,
                ]);
                
                // Add creator as admin
                PsychologyTestBotAdmin::create([
                    'psychology_test_bot_id' => $psychologyTestBot->id,
                    'chat_id' => $chatId,
                    'origin' => $botType,
                    'is_creator' => true,
                ]);
                
                // Set state to wait for questions
                BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_PSYCHOLOGY_QUESTIONS, array_merge($stateData, [
                    'bot_id' => $botItem->id,
                    'psychology_test_bot_id' => $psychologyTestBot->id,
                    'question_lines' => [], // برای جمع‌آوری سوالات
                ]));
                
                $message = "✅ ربات تست روانشناسی با موفقیت ثبت شد!\n\n";
                $message .= "📝 حالا لطفاً سوالات را به فرمت زیر ارسال کنید:\n\n";
                $message .= "فرمت: سوال [دسته, وزن, جهت]\n\n";
                $message .= "مثال:\n";
                $message .= "آیا در جمع‌ها راحت هستید؟ [برون‌گرا, 1.0, 1]\n";
                $message .= "ترجیح می‌دهید تنها باشید؟ [درون‌گرا, 0.9, 1]\n\n";
                $message .= "توضیحات:\n";
                $message .= "• وزن: عدد بین 0 تا 1 (پیش‌فرض: 1.0)\n";
                $message .= "• جهت: 0 = خیلی کم به سمت دسته، 1 = خیلی زیاد به سمت دسته\n\n";
                $message .= "می‌توانید سوالات را در یک یا چند پیام ارسال کنید.\n";
                $message .= "وقتی تمام سوالات را ارسال کردید، کلمه 'پایان' را ارسال کنید.";
                
                BotHelper::sendMessage($bot, $message);
                return; // Return early - don't set webhook yet
            }
            
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
            
            // Log webhook registration
            Log::info('Bot webhook registered via Bot Mother', [
                'bot_id' => $botItem->id,
                'bot_mother_id' => $botMotherId,
                'type' => $botType,
                'language' => $language,
                'endpoint_id' => $endpointId,
                'webhook_url' => $webhookUrl,
            ]);
            
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
                $successMessage .= "• زبان: " . $this->getLanguageDisplayName($language) . "\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\n\n💡 برای مشاهده لیست دستورات: /help";
            } else {
                $successMessage = "✅ ربات با موفقیت ساخته و ثبت شد!\n\n";
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• نام: @" . ($getMe['result']['username'] ?? 'N/A') . "\n";
                $successMessage .= "• نوع: " . ($botType == 'telegram' ? 'تلگرام' : 'بله') . "\n";
                $successMessage .= "• Endpoint: {$stateData['endpoint']['name']}\n";
                $successMessage .= "• Route: {$stateData['endpoint']['route']}\n";
                $successMessage .= "• زبان: " . $this->getLanguageDisplayName($language) . "\n";
                $successMessage .= "• Bot ID: {$botItem->id}\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\n\n💡 برای مشاهده لیست دستورات: /help";
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

    /**
     * Handle presenter bot content input
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handlePresenterContentInput(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $botId = $stateData['bot_id'] ?? null;
        $contentLines = $stateData['content_lines'] ?? [];
        $endpointId = $stateData['endpoint_id'] ?? 'webhook-presenter-bot';
        $botType = $stateData['bot_type'] ?? $type;
        $language = $stateData['language'] ?? 'fa';
        
        if (!$botId) {
            BotHelper::sendMessage($bot, "❌ خطا: اطلاعات ربات یافت نشد. لطفاً دوباره شروع کنید.");
            BotMotherStateHelper::clearState($chatId);
            return;
        }
        
        // Check if user wants to clear/reset content
        if (strtolower(trim($text)) == 'پاک' || strtolower(trim($text)) == 'clear' || strtolower(trim($text)) == 'reset') {
            // Reset content lines
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_PRESENTER_CONTENT, array_merge($stateData, [
                'content_lines' => [],
            ]));
            
            $message = "🗑️ محتوای قبلی پاک شد.\n\n";
            $message .= "📝 حالا می‌توانید محتوای جدید را ارسال کنید.\n";
            $message .= "وقتی تمام محتوا را ارسال کردید، کلمه 'پایان' را ارسال کنید.";
            
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        // Check if user wants to finish
        if (strtolower(trim($text)) == 'پایان' || strtolower(trim($text)) == 'end' || strtolower(trim($text)) == 'finish') {
            if (empty($contentLines)) {
                BotHelper::sendMessage($bot, "❌ هیچ محتوایی ارسال نشده است. لطفاً حداقل یک خط محتوا ارسال کنید.");
                return;
            }
            
            // Save content to database
            try {
                // Content lines are already cleaned (empty lines removed and trimmed)
                // Just join them with newlines
                $content = implode("\n", $contentLines);
                $lines = $contentLines; // Use the already cleaned lines
                
                // Create or update presenter bot
                $presenterBot = PresenterBot::updateOrCreate(
                    ['bot_id' => $botId],
                    ['content' => $content]
                );
                
                // Get bot item
                $botItem = Bot::find($botId);
                if (!$botItem) {
                    throw new Exception("ربات یافت نشد");
                }
                
                // Create webhook URL
                $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $botType, $language, $botMotherId);
                
                // Get token
                $token = $botType == 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
                $newBot = new Telegram($token, $botType);
                
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
                $webhookInfo = BotHelper::checkWebhookInfo($token, $botType);
                
                // Send success message
                $successMessage = "✅ ربات پرزنتر با موفقیت ساخته شد!\n\n";
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• Bot ID: {$botItem->id}\n";
                $successMessage .= "• تعداد خطوط: " . count($lines) . "\n";
                $successMessage .= "• نوع: " . ($botType == 'telegram' ? 'تلگرام' : 'بله') . "\n";
                $successMessage .= "• زبان: " . $this->getLanguageDisplayName($language) . "\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\n\n💡 برای مشاهده لیست دستورات: /help";
                
                BotHelper::sendMessage($bot, $successMessage);
                
                // Clear state
                BotMotherStateHelper::clearState($chatId);
                
                // Log success
                Log::info('Presenter bot created successfully via Bot Mother', [
                    'bot_id' => $botItem->id,
                    'presenter_bot_id' => $presenterBot->id,
                    'content_lines_count' => count($lines),
                    'type' => $botType,
                    'chat_id' => $chatId,
                ]);
                
            } catch (Exception $e) {
                $errorMessage = "❌ خطا در ذخیره محتوا:\n\n";
                $errorMessage .= $e->getMessage() . "\n\n";
                $errorMessage .= "لطفاً دوباره تلاش کنید یا با ادمین تماس بگیرید.";
                
                BotHelper::sendMessage($bot, $errorMessage);
                
                // Log error
                Log::error('Error saving presenter bot content', [
                    'error' => $e->getMessage(),
                    'bot_id' => $botId,
                    'chat_id' => $chatId,
                ]);
            }
        } else {
            // Split text by newlines and add all lines
            $linesFromMessage = explode("\n", $text);
            
            // Remove empty lines and trim each line
            foreach ($linesFromMessage as $line) {
                $trimmedLine = trim($line);
                // Remove lines that are only whitespace or empty
                if ($trimmedLine !== '') {
                    $contentLines[] = $trimmedLine;
                }
            }
            
            // Update state
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_PRESENTER_CONTENT, array_merge($stateData, [
                'content_lines' => $contentLines,
            ]));
            
            // Send confirmation
            $totalLines = count($contentLines);
            $message = "✅ " . $totalLines . " خط ثبت شد.\n\n";
            $message .= "خط بعدی را ارسال کنید یا برای پایان، کلمه 'پایان' را ارسال کنید.";
            
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * Handle psychology test questions input
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handlePsychologyQuestionsInput(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $botId = $stateData['bot_id'] ?? null;
        $psychologyTestBotId = $stateData['psychology_test_bot_id'] ?? null;
        $questionLines = $stateData['question_lines'] ?? [];
        $endpointId = $stateData['endpoint_id'] ?? 'webhook-psychology-test';
        $botType = $stateData['bot_type'] ?? $type;
        $language = $stateData['language'] ?? 'fa';
        
        if (!$botId || !$psychologyTestBotId) {
            BotHelper::sendMessage($bot, "❌ خطا: اطلاعات ربات یافت نشد. لطفاً دوباره شروع کنید.");
            BotMotherStateHelper::clearState($chatId);
            return;
        }
        
        // Check if user wants to finish
        if (strtolower(trim($text)) == 'پایان' || strtolower(trim($text)) == 'end' || strtolower(trim($text)) == 'finish') {
            if (empty($questionLines)) {
                BotHelper::sendMessage($bot, "❌ هیچ سوالی ارسال نشده است. لطفاً حداقل یک سوال ارسال کنید.");
                return;
            }
            
            // Parse and save questions
            try {
                $categories = [];
                $questions = [];
                $order = 0;
                
                foreach ($questionLines as $line) {
                    // Parse format: سوال [دسته, وزن, جهت]
                    // Example: آیا در جمع‌ها راحت هستید؟ [برون‌گرا, 1.0, 1]
                    if (preg_match('/^(.+?)\s*\[([^,]+),\s*([\d.]+),\s*([01])\]$/', trim($line), $matches)) {
                        $questionText = trim($matches[1]);
                        $categoryName = trim($matches[2]);
                        $weight = floatval($matches[3]);
                        $direction = intval($matches[4]);
                        
                        // Limit weight between 0 and 1
                        if ($weight < 0) $weight = 0;
                        if ($weight > 1) $weight = 1;
                        
                        // Store category if not exists
                        if (!isset($categories[$categoryName])) {
                            $categories[$categoryName] = [
                                'name' => $categoryName,
                                'description' => null,
                            ];
                        }
                        
                        // Store question
                        $questions[] = [
                            'category_name' => $categoryName,
                            'question_text' => $questionText,
                            'weight' => $weight,
                            'direction' => $direction,
                            'order' => $order++,
                        ];
                    }
                }
                
                if (empty($questions)) {
                    BotHelper::sendMessage($bot, "❌ خطا: فرمت سوالات نامعتبر است. لطفاً سوالات را با فرمت صحیح ارسال کنید.");
                    return;
                }
                
                // Create categories
                $categoryMap = [];
                foreach ($categories as $categoryName => $categoryData) {
                    $category = PsychologyTestCategory::updateOrCreate(
                        [
                            'psychology_test_bot_id' => $psychologyTestBotId,
                            'name' => $categoryName,
                        ],
                        [
                            'description' => null,
                        ]
                    );
                    $categoryMap[$categoryName] = $category->id;
                }
                
                // Create questions
                foreach ($questions as $questionData) {
                    PsychologyTestQuestion::create([
                        'psychology_test_bot_id' => $psychologyTestBotId,
                        'psychology_test_category_id' => $categoryMap[$questionData['category_name']],
                        'question_text' => $questionData['question_text'],
                        'weight' => $questionData['weight'],
                        'direction' => $questionData['direction'],
                        'order' => $questionData['order'],
                    ]);
                }
                
                // Get all categories for description input
                $allCategories = PsychologyTestCategory::where('psychology_test_bot_id', $psychologyTestBotId)
                    ->orderBy('name')
                    ->get();
                
                // Set state to wait for category descriptions
                BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_CATEGORY_DESCRIPTIONS, array_merge($stateData, [
                    'category_index' => 0,
                    'category_ids' => $allCategories->pluck('id')->toArray(),
                ]));
                
                // Ask for first category description
                if ($allCategories->count() > 0) {
                    $firstCategory = $allCategories->first();
                    $message = "✅ " . count($questions) . " سوال با " . count($categories) . " دسته ثبت شد!\n\n";
                    $message .= "📝 حالا برای هر دسته، توضیحات را وارد کنید.\n\n";
                    $message .= "دسته 1/" . $allCategories->count() . ": {$firstCategory->name}\n\n";
                    $message .= "لطفاً توضیحات این دسته را ارسال کنید:";
                    
                    BotHelper::sendMessage($bot, $message);
                } else {
                    throw new Exception("خطا: هیچ دسته‌ای یافت نشد");
                }
                
            } catch (Exception $e) {
                $errorMessage = "❌ خطا در ذخیره سوالات:\n\n";
                $errorMessage .= $e->getMessage() . "\n\n";
                $errorMessage .= "لطفاً دوباره تلاش کنید یا با ادمین تماس بگیرید.";
                
                BotHelper::sendMessage($bot, $errorMessage);
                
                Log::error('Error saving psychology test questions', [
                    'error' => $e->getMessage(),
                    'psychology_test_bot_id' => $psychologyTestBotId,
                    'chat_id' => $chatId,
                ]);
            }
        } else {
            // Split text by newlines and add all lines
            $linesFromMessage = explode("\n", $text);
            
            // Remove empty lines and trim each line
            foreach ($linesFromMessage as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine !== '') {
                    $questionLines[] = $trimmedLine;
                }
            }
            
            // Update state
            BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_PSYCHOLOGY_QUESTIONS, array_merge($stateData, [
                'question_lines' => $questionLines,
            ]));
            
            // Send confirmation
            $totalLines = count($questionLines);
            $message = "✅ " . $totalLines . " سوال ثبت شد.\n\n";
            $message .= "سوال بعدی را ارسال کنید یا برای پایان، کلمه 'پایان' را ارسال کنید.";
            
            BotHelper::sendMessage($bot, $message);
        }
    }

    /**
     * Handle category descriptions input
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleCategoryDescriptionsInput(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $botId = $stateData['bot_id'] ?? null;
        $categoryIndex = $stateData['category_index'] ?? 0;
        $categoryIds = $stateData['category_ids'] ?? [];
        $endpointId = $stateData['endpoint_id'] ?? 'webhook-psychology-test';
        $botType = $stateData['bot_type'] ?? $type;
        $language = $stateData['language'] ?? 'fa';
        
        if (!$botId || empty($categoryIds)) {
            BotHelper::sendMessage($bot, "❌ خطا: اطلاعات ربات یافت نشد. لطفاً دوباره شروع کنید.");
            BotMotherStateHelper::clearState($chatId);
            return;
        }
        
        try {
            // Get current category
            $currentCategoryId = $categoryIds[$categoryIndex] ?? null;
            if (!$currentCategoryId) {
                throw new Exception("دسته یافت نشد");
            }
            
            $currentCategory = PsychologyTestCategory::find($currentCategoryId);
            if (!$currentCategory) {
                throw new Exception("دسته یافت نشد");
            }
            
            // Save description
            $currentCategory->description = trim($text);
            $currentCategory->save();
            
            // Move to next category
            $categoryIndex++;
            
            // Check if all categories are done
            if ($categoryIndex >= count($categoryIds)) {
                // All categories done - set webhook
                $botItem = Bot::find($botId);
                if (!$botItem) {
                    throw new Exception("ربات یافت نشد");
                }
                
                // Create webhook URL
                $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $botType, $language, $botMotherId);
                
                // Get token
                $token = $botType == 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
                $newBot = new Telegram($token, $botType);
                
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
                $webhookInfo = BotHelper::checkWebhookInfo($token, $botType);
                
                // Send success message
                $successMessage = "✅ ربات تست روانشناسی با موفقیت ساخته شد!\n\n";
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• Bot ID: {$botItem->id}\n";
                $successMessage .= "• تعداد دسته‌ها: " . count($categoryIds) . "\n";
                $successMessage .= "• نوع: " . ($botType == 'telegram' ? 'تلگرام' : 'بله') . "\n\n";
                $successMessage .= "🔗 Webhook URL:\n{$webhookUrl}\n\n";
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successMessage .= "✅ Webhook با موفقیت تنظیم شد.\n";
                    $successMessage .= "📊 Pending Updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
                } else {
                    $successMessage .= "⚠️ Webhook تنظیم شد اما تایید نشد. لطفاً بررسی کنید.\n";
                }
                
                $successMessage .= "\n\n💡 برای مشاهده لیست دستورات: /help";
                
                BotHelper::sendMessage($bot, $successMessage);
                
                // Clear state
                BotMotherStateHelper::clearState($chatId);
                
                // Log success
                Log::info('Psychology test bot created successfully via Bot Mother', [
                    'bot_id' => $botItem->id,
                    'psychology_test_bot_id' => $currentCategory->psychology_test_bot_id,
                    'categories_count' => count($categoryIds),
                    'type' => $botType,
                    'chat_id' => $chatId,
                ]);
            } else {
                // Ask for next category description
                $nextCategoryId = $categoryIds[$categoryIndex];
                $nextCategory = PsychologyTestCategory::find($nextCategoryId);
                
                if ($nextCategory) {
                    $message = "✅ توضیحات دسته '{$currentCategory->name}' ثبت شد.\n\n";
                    $message .= "دسته " . ($categoryIndex + 1) . "/" . count($categoryIds) . ": {$nextCategory->name}\n\n";
                    $message .= "لطفاً توضیحات این دسته را ارسال کنید:";
                    
                    BotHelper::sendMessage($bot, $message);
                    
                    // Update state
                    BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_CATEGORY_DESCRIPTIONS, array_merge($stateData, [
                        'category_index' => $categoryIndex,
                    ]));
                }
            }
            
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در ذخیره توضیحات:\n\n";
            $errorMessage .= $e->getMessage() . "\n\n";
            $errorMessage .= "لطفاً دوباره تلاش کنید یا با ادمین تماس بگیرید.";
            
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error saving category descriptions', [
                'error' => $e->getMessage(),
                'category_index' => $categoryIndex,
                'chat_id' => $chatId,
            ]);
        }
    }

    /**
     * Handle statistics command
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleStatistics(Telegram $bot, string $type, int $botMotherId): void
    {
        try {
            // ارسال پیام "در حال پردازش"
            $processingMessage = trans('bot.processing your request');
            if ($processingMessage == 'bot.processing your request') {
                $processingMessage = '⏳ در حال پردازش درخواست شما...';
            }
            BotHelper::sendMessage($bot, $processingMessage);
            
            // استفاده از cache برای آمار (1 ساعت)
            $cacheKey = "bot_mother_statistics_{$botMotherId}";
            $cacheDuration = 3600; // 1 ساعت (60 دقیقه)
            
            // بررسی اینکه آیا cache وجود دارد یا نه
            $isCached = Cache::has($cacheKey);
            
            $statisticsData = Cache::remember($cacheKey, $cacheDuration, function () use ($botMotherId) {
                return $this->calculateStatistics($botMotherId);
            });
            
            // پیام cache
            $cacheInfo = '';
            if ($isCached) {
                $cacheInfo = "\n\n💡 نکته: این آمار به دلیل سنگینی محاسبات، هر 1 ساعت یک بار به‌روزرسانی می‌شود.\n";
                $cacheInfo .= "آخرین به‌روزرسانی: " . now()->format('Y-m-d H:i:s');
            } else {
                $cacheInfo = "\n\n💡 نکته: این آمار به دلیل سنگینی محاسبات، هر 1 ساعت یک بار به‌روزرسانی می‌شود.";
            }
            
            $message = "📊 آمار ربات مادر\n\n";
            $message .= $statisticsData['message'];
            $message .= $cacheInfo;
            
            // Log قبل از ارسال
            Log::info('Statistics message prepared', [
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'bot_mother_id' => $botMotherId,
                'is_cached' => $isCached,
                'bots_count' => $statisticsData['bots_count'] ?? 0,
                'message_length' => strlen($message),
            ]);
            
            // استفاده از sendLongMessage برای پیام‌های طولانی
            try {
                BotHelper::sendLongMessage($message, $bot);
                Log::info('Statistics message sent successfully', [
                    'chat_id' => $bot->ChatID(),
                    'type' => $type,
                ]);
            } catch (Exception $sendException) {
                Log::error('Error sending statistics message', [
                    'error' => $sendException->getMessage(),
                    'chat_id' => $bot->ChatID(),
                    'type' => $type,
                    'message_length' => strlen($message),
                ]);
                // ارسال پیام خطا به کاربر
                $errorMsg = "❌ خطا در ارسال آمار. لطفاً دوباره تلاش کنید.";
                BotHelper::sendMessage($bot, $errorMsg);
            }
            
            // Log
            Log::info('Statistics requested', [
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'bot_mother_id' => $botMotherId,
                'is_cached' => $isCached,
                'bots_count' => $statisticsData['bots_count'] ?? 0,
            ]);
            
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در دریافت آمار:\n\n";
            $errorMessage .= $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error getting statistics', [
                'error' => $e->getMessage(),
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'bot_mother_id' => $botMotherId,
            ]);
        }
    }

    /**
     * محاسبه آمار ربات مادر (برای استفاده در cache)
     * 
     * @param int $botMotherId
     * @return array
     */
    private function calculateStatistics(int $botMotherId): array
    {
        $message = '';
        
        // آمار کلی ربات مادر
        $totalSubscribers = BotLog::where('bot_mother_id', $botMotherId)
            ->distinct('chat_id')
            ->count('chat_id');
            
            // استارت در 1 هفته قبل
            $startedLastWeek = BotLog::where('bot_mother_id', $botMotherId)
                ->where('created_at', '>=', now()->subWeek())
                ->where('text', '/start')
                ->distinct('chat_id')
                ->count('chat_id');
        
        // استارت در 1 ماه قبل
        $startedLastMonth = BotLog::where('bot_mother_id', $botMotherId)
            ->where('created_at', '>=', now()->subMonth())
            ->where('text', '/start')
            ->distinct('chat_id')
            ->count('chat_id');
        
        // استارت در 1 سال قبل
        $startedLastYear = BotLog::where('bot_mother_id', $botMotherId)
            ->where('created_at', '>=', now()->subYear())
            ->where('text', '/start')
            ->distinct('chat_id')
            ->count('chat_id');
        
        $message .= "📈 آمار کلی:\n";
        $message .= "• کل مشترکین: {$totalSubscribers}\n";
        $message .= "• استارت در 1 هفته قبل: {$startedLastWeek}\n";
        $message .= "• استارت در 1 ماه قبل: {$startedLastMonth}\n";
        $message .= "• استارت در 1 سال قبل: {$startedLastYear}\n\n";
        
        // لیست ربات‌های ساخته شده (فقط ربات‌هایی که token دارند)
        $bots = Bot::where('bot_mother_id', $botMotherId)
                ->where(function($query) {
                    $query->whereNotNull('telegram_bot_token')
                        ->where('telegram_bot_token', '!=', '')
                        ->orWhere(function($q) {
                            $q->whereNotNull('bale_bot_token')
                                ->where('bale_bot_token', '!=', '');
                        });
                })
                ->get();
        
        // فیلتر کردن ربات‌هایی که لاگ ندارند یا اطلاعات ندارند (یعنی deactivate هستند یا استفاده نمی‌شوند)
        $activeBots = $bots->filter(function($botItem) {
                // بررسی وجود لاگ بر اساس bot_id (روش جدید)
                $hasLogs = BotLog::where('bot_id', $botItem->id)
                    ->whereNotNull('bot_id')
                    ->exists();
                
                // اگر bot_id در لاگ‌ها موجود نباشد، از روش قدیمی استفاده می‌کنیم (سازگاری با لاگ‌های قدیمی)
                if (!$hasLogs) {
                    // Fallback: بررسی بر اساس type و bot_mother_id
                    $hasLogs = BotLog::where('bot_mother_id', $botItem->bot_mother_id)
                        ->where(function($query) use ($botItem) {
                            if ($botItem->telegram_bot_token) {
                                $query->where('type', 'telegram');
                            } elseif ($botItem->bale_bot_token) {
                                $query->where('type', 'bale');
                            }
                        })
                        ->exists();
                }
                
                if (!$hasLogs) {
                    return false;
                }
                
                // بررسی وجود endpoint (اولویت با bot_id)
                $endpointUri = BotLog::where('bot_id', $botItem->id)
                    ->whereNotNull('bot_id')
                    ->whereNotNull('webhook_endpoint_uri')
                    ->where('webhook_endpoint_uri', '!=', '')
                    ->value('webhook_endpoint_uri');
                
                // Fallback: اگر endpoint با bot_id پیدا نشد، از روش قدیمی استفاده می‌کنیم
                if (!$endpointUri) {
                    $endpointUri = BotLog::where('bot_mother_id', $botItem->bot_mother_id)
                        ->where(function($query) use ($botItem) {
                            if ($botItem->telegram_bot_token) {
                                $query->where('type', 'telegram');
                            } elseif ($botItem->bale_bot_token) {
                                $query->where('type', 'bale');
                            }
                        })
                        ->whereNotNull('webhook_endpoint_uri')
                        ->where('webhook_endpoint_uri', '!=', '')
                        ->value('webhook_endpoint_uri');
                }
                
                // اگر endpoint ندارند، نمایش نده
                if (!$endpointUri) {
                    return false;
                }
                
                return true;
        });
        
        if ($activeBots->isEmpty()) {
            $message .= "📭 هیچ ربات فعالی با اطلاعات یافت نشد.\n";
        } else {
            $message .= "🤖 لیست ربات‌های فعال ({$activeBots->count()} ربات):\n\n";
            
            foreach ($activeBots as $index => $botItem) {
                    $botNumber = $index + 1;
                    $botName = $botItem->telegram_bot_name ?? $botItem->bale_bot_name ?? 'N/A';
                    $botType = $botItem->telegram_bot_token ? 'Telegram' : ($botItem->bale_bot_token ? 'Bale' : 'N/A');
                    $createdAt = $botItem->created_at ? $botItem->created_at->format('Y-m-d H:i') : 'N/A';
                    
                    // استفاده از language_code از جدول bots (اولویت اول)
                    $languageCode = $botItem->language_code;
                    $normalizedLanguage = $this->normalizeLanguageCode($languageCode ?? 'fa');
                    
                    // تشخیص endpoint از bot_logs (اولویت با bot_id)
                    $endpointUri = BotLog::where('bot_id', $botItem->id)
                        ->whereNotNull('bot_id')
                        ->whereNotNull('webhook_endpoint_uri')
                        ->where('webhook_endpoint_uri', '!=', '')
                        ->value('webhook_endpoint_uri');
                    
                    // Fallback: اگر endpoint با bot_id پیدا نشد، از روش قدیمی استفاده می‌کنیم
                    if (!$endpointUri) {
                        $endpointUri = BotLog::where('bot_mother_id', $botMotherId)
                            ->where(function($query) use ($botItem) {
                                if ($botItem->telegram_bot_token) {
                                    $query->where('type', 'telegram');
                                } elseif ($botItem->bale_bot_token) {
                                    $query->where('type', 'bale');
                                }
                            })
                            ->whereNotNull('webhook_endpoint_uri')
                            ->where('webhook_endpoint_uri', '!=', '')
                            ->value('webhook_endpoint_uri');
                    }
                    
                    $endpointName = $endpointUri ? (WebhookEndpointHelper::getEndpointById($endpointUri)['name'] ?? $endpointUri) : 'نامشخص';
                    
                    // استفاده از language_code از جدول bots برای نمایش
                    $languageDisplay = $languageCode ? $this->getLanguageDisplayName($languageCode) : 'نامشخص';
                    
                    $message .= "{$botNumber}. ربات #{$botItem->id}\n";
                    $message .= "   📝 نام: @{$botName}\n";
                    $message .= "   🔧 نوع: {$botType}\n";
                    $message .= "   🌍 زبان: {$languageDisplay}\n";
                    $message .= "   🔗 Endpoint: {$endpointName}\n";
                    $message .= "   📅 تاریخ ساخت: {$createdAt}\n";
                    
                    // آمار کلی بر اساس bot_id (روش جدید)
                    $baseQuery = BotLog::where('bot_id', $botItem->id)
                        ->whereNotNull('bot_id');
                    
                    // Fallback: اگر bot_id در لاگ‌ها موجود نباشد، از روش قدیمی استفاده می‌کنیم (سازگاری با لاگ‌های قدیمی)
                    $hasLogsWithBotId = (clone $baseQuery)->exists();
                    if (!$hasLogsWithBotId) {
                        $baseQuery = BotLog::where('bot_mother_id', $botMotherId)
                            ->where(function($query) use ($botItem) {
                                if ($botItem->telegram_bot_token) {
                                    $query->where('type', 'telegram');
                                } elseif ($botItem->bale_bot_token) {
                                    $query->where('type', 'bale');
                                }
                            });
                        
                        if ($endpointUri) {
                            $baseQuery->where('webhook_endpoint_uri', $endpointUri);
                        }
                        
                        // استفاده از normalized language برای فیلتر کردن (اگر language در BotLog موجود باشد)
                        if ($normalizedLanguage) {
                            $baseQuery->where(function($query) use ($normalizedLanguage, $languageCode) {
                                $query->where('language', $normalizedLanguage)
                                    ->orWhere('language', $languageCode)
                                    ->orWhere('language', 'like', $normalizedLanguage . '%');
                            });
                        }
                    }
                    
                    // تعداد کاربرانی که استارت کردند (کل)
                    $startedTotal = (clone $baseQuery)
                        ->where('text', '/start')
                        ->distinct('chat_id')
                        ->count('chat_id');
                    
                    // تعداد کاربرانی که استارت کردند (هفته گذشته)
                    $startedLastWeek = (clone $baseQuery)
                        ->where('text', '/start')
                        ->where('created_at', '>=', now()->subWeek())
                        ->distinct('chat_id')
                        ->count('chat_id');
                    
                    // تعداد کاربرانی که استارت کردند (ماه گذشته)
                    $startedLastMonth = (clone $baseQuery)
                        ->where('text', '/start')
                        ->where('created_at', '>=', now()->subMonth())
                        ->distinct('chat_id')
                        ->count('chat_id');
                    
                    // تعداد کاربران فعال (استفاده می‌کنند) - کاربرانی که در 7 روز گذشته تعامل داشته‌اند
                    $activeUsers = (clone $baseQuery)
                        ->where('created_at', '>=', now()->subWeek())
                        ->distinct('chat_id')
                        ->count('chat_id');
                    
                    // کل تعاملات
                    $totalInteractions = (clone $baseQuery)->count();
                    
                    // تعداد کاربران یونیک کل
                    $uniqueUsersTotal = (clone $baseQuery)
                        ->distinct('chat_id')
                        ->count('chat_id');
                    
                    // نرخ استفاده متوسط (تعداد تعاملات / تعداد کاربران)
                    $avgUsagePerUser = $uniqueUsersTotal > 0 
                        ? round($totalInteractions / $uniqueUsersTotal, 2) 
                        : 0;
                    
                    $message .= "   📊 آمار کلی:\n";
                    $message .= "      👥 کاربران یونیک کل: {$uniqueUsersTotal}\n";
                    $message .= "      🚀 استارت کل: {$startedTotal}\n";
                    $message .= "      📅 استارت (هفته گذشته): {$startedLastWeek}\n";
                    $message .= "      📅 استارت (ماه گذشته): {$startedLastMonth}\n";
                    $message .= "      ✅ کاربران فعال (7 روز): {$activeUsers}\n";
                    $message .= "      💬 کل تعاملات: {$totalInteractions}\n";
                    $message .= "      📈 نرخ استفاده متوسط: {$avgUsagePerUser} تعامل/کاربر\n";
                    
                    // اگر ربات قرآنی است، آمار بیشتری نمایش بده
                    if ($endpointUri == 'webhook-quran-word') {
                        // آیات خوانده شده (pattern: /sure[0-9]+ayah[0-9]+)
                        $ayahsRead = (clone $baseQuery)
                            ->where('is_command', true)
                            ->whereRaw("text REGEXP 'sure[0-9]+ayah[0-9]+'")
                            ->count();
                        
                        $message .= "   📖 آمار قرآنی:\n";
                        $message .= "      📖 آیات خوانده شده: {$ayahsRead}\n";
                    }
                    
                $message .= "\n";
            }
        }
        
        return [
            'message' => $message,
            'bots_count' => $activeBots->count(),
        ];
    }

    /**
     * Handle broadcast start command
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleBroadcastStart(Telegram $bot, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        
        // بررسی ادمین بودن کاربر
        if (!AdminHelper::isAdmin($chatId)) {
            $message = "❌ شما دسترسی به این دستور ندارید.\nاین دستور فقط برای ادمین‌ها قابل استفاده است.";
            BotHelper::sendMessage($bot, $message);
            Log::warning('Unauthorized broadcast attempt', [
                'chat_id' => $chatId,
                'type' => $type,
            ]);
            return;
        }
        
        $message = "📢 ارسال پیام همگانی\n\n";
        $message .= "🌍 لطفاً زبان را انتخاب کنید:\n\n";
        
        $languages = $this->getSupportedLanguages();
        
        // Create inline keyboard with language buttons
        $buttons = [];
        $row = [];
        foreach ($languages as $langCode => $langName) {
            $row[] = $bot->buildInlineKeyBoardButton($langName, callback_data: 'broadcast_lang_' . $langCode);
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
        
        // Set state to waiting for language
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_BROADCAST_LANGUAGE, [
            'bot_mother_id' => $botMotherId,
            'type' => $type,
        ]);
    }

    /**
     * Handle broadcast language selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleBroadcastLanguageSelection(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $selectedLanguage = $text;
        $languages = $this->getSupportedLanguages();
        
        // Check if text is a language code (from callback or direct input)
        if (str_starts_with($text, 'broadcast_lang_')) {
            $selectedLanguage = str_replace('broadcast_lang_', '', $text);
        }
        
        if (!isset($languages[$selectedLanguage])) {
            $message = "❌ زبان نامعتبر است. لطفاً زبان صحیح را انتخاب کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        $languageName = $languages[$selectedLanguage];
        $message = "✅ زبان انتخاب شد: {$languageName}\n\n";
        $message .= "📝 حالا لطفاً پیام خود را ارسال کنید:";
        
        BotHelper::sendMessage($bot, $message);
        
        // Set state to waiting for message
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_BROADCAST_MESSAGE, array_merge($stateData, [
            'language' => $selectedLanguage,
        ]));
    }

    /**
     * Handle broadcast message input
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleBroadcastMessageInput(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $language = $stateData['language'] ?? 'fa';
        
        if (empty(trim($text))) {
            $message = "❌ پیام نمی‌تواند خالی باشد. لطفاً پیام خود را ارسال کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        try {
            $message = "⏳ در حال ارسال پیام...\nلطفاً صبر کنید.";
            BotHelper::sendMessage($bot, $message);
            
            $broadcastService = new BotMessageBroadcastService();
            $result = $broadcastService->sendMessageToUsersByLanguage(
                $language,
                $text,
                $type,
                $botMotherId
            );
            
            $successMessage = "✅ ارسال پیام همگانی انجام شد!\n\n";
            $successMessage .= "📊 نتایج:\n";
            $successMessage .= "• ✅ ارسال موفق: {$result['success_count']}\n";
            $successMessage .= "• ❌ خطا: {$result['error_count']}\n";
            $successMessage .= "• 📝 گزارش کامل به ادمین‌ها ارسال شد.\n";
            
            if ($result['error_count'] > 0 && count($result['errors']) > 0) {
                $successMessage .= "\n⚠️ خطاها:\n";
                foreach (array_slice($result['errors'], 0, 5) as $error) {
                    $successMessage .= "• Chat ID {$error['chat_id']}: {$error['error']}\n";
                }
                if (count($result['errors']) > 5) {
                    $successMessage .= "... و " . (count($result['errors']) - 5) . " خطای دیگر\n";
                }
            }
            
            BotHelper::sendMessage($bot, $successMessage);
            
            // Clear state
            BotMotherStateHelper::clearState($chatId);
            
            // Log
            Log::info('Broadcast completed', [
                'chat_id' => $chatId,
                'type' => $type,
                'bot_mother_id' => $botMotherId,
                'language' => $language,
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count'],
            ]);
            
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در ارسال پیام همگانی:\n\n";
            $errorMessage .= $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error in broadcast', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'type' => $type,
                'bot_mother_id' => $botMotherId,
                'language' => $language,
            ]);
        }
    }

    /**
     * Handle help command - نمایش لیست دستورات
     * 
     * @param Telegram $bot
     * @param string $type
     * @return void
     */
    private function handleHelp(Telegram $bot, string $type): void
    {
        $this->sendHelpMessage($bot, $type);
    }

    /**
     * ارسال پیام help (برای استفاده در آخر هر عملیات)
     * 
     * @param Telegram $bot
     * @param string $type
     * @return void
     */
    private function sendHelpMessage(Telegram $bot, string $type): void
    {
        $message = "📖 راهنمای ربات مادر\n\n";
        $message .= "📋 دستورات موجود:\n\n";
        $message .= "1️⃣ /start یا 'ساختن'\n";
        $message .= "   شروع ساخت ربات جدید\n\n";
        $message .= "2️⃣ /statistics یا 'استاتستیکس'\n";
        $message .= "   مشاهده آمار کامل ربات‌های ساخته شده\n\n";
        $message .= "3️⃣ /broadcast یا 'ارسال همگانی'\n";
        $message .= "   ارسال پیام همگانی به کاربران (فقط ادمین)\n\n";
        $message .= "4️⃣ /duplicates یا 'تکراری'\n";
        $message .= "   مشاهده ربات‌های تکراری\n\n";
        $message .= "5️⃣ /help یا 'راهنما'\n";
        $message .= "   نمایش این راهنما\n\n";
        $message .= "6️⃣ /quran_bots یا 'ربات قرآن'\n";
        $message .= "   معرفی ربات‌های قرآن با پشتیبانی چندزبانه\n\n";
        $message .= "7️⃣ /clear_cache یا 'پاک کش'\n";
        $message .= "   پاک کردن cache آمار (برای محاسبه مجدد)\n\n";
        $message .= "8️⃣ /logs یا 'لاگ'\n";
        $message .= "   نمایش 50 خط آخر لاگ\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 برای مشاهده لیست endpoint ها، /start را ارسال کنید.";
        
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle Quran bots introduction command
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleQuranBotsIntroduction(Telegram $bot, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        
        // نمایش لیست زبان‌ها برای انتخاب
        $languages = $this->getSupportedLanguages();
        $message = trans('bot.quran_bots.select_language', [], 'fa');
        if ($message == 'bot.quran_bots.select_language') {
            $message = 'برای چه زبانی می‌خواهید متن معرفی ربات‌های قرآن را ببینید؟';
        }
        $message .= "\n\n";
        
        // ساخت دکمه‌های inline برای انتخاب زبان
        $buttons = [];
        $row = [];
        foreach ($languages as $langCode => $langName) {
            $row[] = $bot->buildInlineKeyBoardButton($langName, callback_data: 'quran_bots_lang_' . $langCode);
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
        
        // تنظیم state
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_QURAN_BOTS_LANGUAGE, [
            'bot_mother_id' => $botMotherId,
            'type' => $type,
        ]);
    }

    /**
     * Handle Quran bots language selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleQuranBotsLanguageSelection(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $selectedLanguage = $text; // استفاده از زبان انتخابی از callback
        
        // اگر از callback آمده، زبان را از callback data بگیر
        if (str_starts_with($text, 'quran_bots_lang_')) {
            $selectedLanguage = str_replace('quran_bots_lang_', '', $text);
        }
        
        // بررسی اینکه زبان معتبر است
        $languages = $this->getSupportedLanguages();
        if (!isset($languages[$selectedLanguage])) {
            // اگر زبان معتبر نیست، از fa استفاده کن
            $selectedLanguage = 'fa';
        }
        
        Log::info('Quran bots language selected', [
            'chat_id' => $chatId,
            'selected_language' => $selectedLanguage,
            'input_text' => $text,
        ]);
        
        // پرسیدن منبع
        $message = trans('bot.quran_bots.select_source', [], 'fa');
        if ($message == 'bot.quran_bots.select_source') {
            $message = 'از چه منبعی استفاده کنیم؟';
        }
        $message .= "\n\n";
        $messageBoth = trans('bot.quran_bots.source.both', [], 'fa');
        if ($messageBoth == 'bot.quran_bots.source.both') {
            $messageBoth = 'ترکیب کانفیگ + دیتابیس';
        }
        $message .= "1️⃣ {$messageBoth}\n";
        
        $messageDb = trans('bot.quran_bots.source.database_only', [], 'fa');
        if ($messageDb == 'bot.quran_bots.source.database_only') {
            $messageDb = 'فقط دیتابیس';
        }
        $message .= "2️⃣ {$messageDb}\n";
        $message .= "\n";
        $message .= "شماره گزینه را ارسال کنید:";
        
        BotHelper::sendMessage($bot, $message);
        
        // به‌روزرسانی state
        BotMotherStateHelper::setState($chatId, BotMotherStateHelper::STATE_WAITING_QURAN_BOTS_SOURCE, array_merge($stateData, [
            'language' => $selectedLanguage,
        ]));
    }

    /**
     * Handle Quran bots source selection
     * 
     * @param Telegram $bot
     * @param string $text
     * @param array $stateData
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleQuranBotsSourceSelection(Telegram $bot, string $text, array $stateData, string $type, int $botMotherId): void
    {
        $chatId = $bot->ChatID();
        $language = $stateData['language'] ?? 'fa';
        $source = 'both';
        
        // تشخیص منبع انتخابی
        if ($text == '1' || strtolower($text) == 'both' || strtolower($text) == 'ترکیب') {
            $source = 'both';
        } elseif ($text == '2' || strtolower($text) == 'database' || strtolower($text) == 'دیتابیس') {
            $source = 'database_only';
        } else {
            $message = "❌ گزینه نامعتبر. لطفاً 1 یا 2 را ارسال کنید.";
            BotHelper::sendMessage($bot, $message);
            return;
        }
        
        try {
            // ارسال پیام "در حال پردازش"
            $processingMessage = trans('bot.processing your request');
            if ($processingMessage == 'bot.processing your request') {
                $processingMessage = '⏳ در حال پردازش درخواست شما...';
            }
            BotHelper::sendMessage($bot, $processingMessage);
            
            // تولید متن معرفی
            $service = new \App\Services\QuranBotsIntroductionService();
            
            Log::info('Generating Quran bots introduction', [
                'chat_id' => $chatId,
                'language' => $language,
                'source' => $source,
                'bot_mother_id' => $botMotherId,
                'type' => $type,
            ]);
            
            $message = $service->generateIntroductionMessage($language, $source, $botMotherId, $type);
            
            if (empty(trim($message))) {
                $message = "❌ هیچ ربات قرآنی یافت نشد.\n\n";
                if ($source == 'database_only') {
                    $message .= "لطفاً بررسی کنید که ربات‌های قرآن در دیتابیس ثبت شده‌اند.";
                } else {
                    $message .= "لطفاً بررسی کنید که ربات‌های قرآن در کانفیگ یا دیتابیس ثبت شده‌اند.";
                }
            }
            
            BotHelper::sendMessage($bot, $message);
            
            // پاک کردن state
            BotMotherStateHelper::clearState($chatId);
            
            // لاگ
            Log::info('Quran bots introduction sent', [
                'chat_id' => $chatId,
                'language' => $language,
                'source' => $source,
                'type' => $type,
                'bot_mother_id' => $botMotherId,
            ]);
        } catch (\Exception $e) {
            $errorMessage = "❌ خطا در تولید متن معرفی:\n\n" . $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error generating Quran bots introduction', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'language' => $language,
                'source' => $source,
            ]);
            
            BotMotherStateHelper::clearState($chatId);
        }
    }

    /**
     * Handle clear cache command
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleClearCache(Telegram $bot, string $type, int $botMotherId): void
    {
        try {
            $cacheKey = "bot_mother_statistics_{$botMotherId}";
            Cache::forget($cacheKey);
            
            $message = "✅ Cache آمار پاک شد.\n\n";
            $message .= "آمار در درخواست بعدی دوباره محاسبه خواهد شد.";
            
            BotHelper::sendMessage($bot, $message);
            
            Log::info('Cache cleared', [
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'bot_mother_id' => $botMotherId,
            ]);
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در پاک کردن cache:\n\n" . $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error clearing cache', [
                'error' => $e->getMessage(),
                'chat_id' => $bot->ChatID(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Handle logs command - نمایش 50 خط آخر لاگ
     * 
     * @param Telegram $bot
     * @param string $type
     * @return void
     */
    private function handleLogs(Telegram $bot, string $type): void
    {
        try {
            $logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
            
            if (!file_exists($logFile)) {
                // اگر فایل امروز وجود نداشت، آخرین فایل لاگ را پیدا کن
                $logDir = storage_path('logs');
                $files = glob($logDir . '/laravel-*.log');
                if (empty($files)) {
                    $message = "❌ هیچ فایل لاگی یافت نشد.";
                    BotHelper::sendMessage($bot, $message);
                    return;
                }
                // آخرین فایل را انتخاب کن
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $logFile = $files[0];
            }
            
            // خواندن 50 خط آخر
            $lines = file($logFile);
            $lastLines = array_slice($lines, -50);
            $logContent = implode('', $lastLines);
            
            // اگر لاگ خیلی طولانی است، آن را تقسیم کن
            if (strlen($logContent) > 4000) {
                $logContent = substr($logContent, -4000);
                $logContent = "... (فقط 4000 کاراکتر آخر)\n\n" . $logContent;
            }
            
            $message = "📋 50 خط آخر لاگ:\n\n";
            $message .= "```\n" . $logContent . "\n```";
            
            BotHelper::sendMessage($bot, $message);
            
            Log::info('Logs requested', [
                'chat_id' => $bot->ChatID(),
                'type' => $type,
                'log_file' => basename($logFile),
            ]);
        } catch (Exception $e) {
            $errorMessage = "❌ خطا در خواندن لاگ:\n\n" . $e->getMessage();
            BotHelper::sendMessage($bot, $errorMessage);
            
            Log::error('Error reading logs', [
                'error' => $e->getMessage(),
                'chat_id' => $bot->ChatID(),
                'type' => $type,
            ]);
        }
    }

}
