<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\QuranScanPage;
use Exception;
use Gap\SDP\Api as GapBot;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Telegram;

class QuranWordController extends Controller
{

    private QuranBotUserRankingService $quranBotUserRankingService;
    
    /**
     * Normalize language code for database queries
     * Converts codes like ar-IQ -> ar, de-DE -> de, zh-CN -> zh
     * 
     * @param string $languageCode
     * @return string
     */
    private static function normalizeLanguageCodeForDatabase(string $languageCode): string
    {
        // اگر کد زبان شامل خط تیره است، قسمت اول را برمی‌گردانیم
        if (strpos($languageCode, '-') !== false) {
            return explode('-', $languageCode)[0];
        }
        return $languageCode;
    }

    public function __construct(QuranBotUserRankingService $quranBotUserRankingService)
    {
        $this->quranBotUserRankingService = $quranBotUserRankingService;
    }

    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function gap(Request $request)
    {

        App::setLocale("fa");
        $isStartCommandShow = 1;
        $type = "gap";

        $token = env("QURAN_HEFZ_BOT_TOKEN_GAP");
        $bot = new GapBot($token);
        $bot->sendText("+989196070718", "salam" . "gap:" . $request->chat_id . " : ");
        BotHelper::sendMessageToSuperAdmin("gap:" . $request->chat_id . " : ", "bale");
        $bot->sendText($request->chat_id, "salam" . "gap:" . $request->chat_id . " : ");

//        dd($request);
    }

    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        try {
            // لاگ برای دیباگ
            $startTime = microtime(true);
            Log::info('🔔 [QuranBot] Webhook received', [
                'origin' => $request->input('origin'),
                'bot_mother_id' => $request->input('bot_mother_id'),
                'language' => $request->input('language'),
                'bot_id' => $request->input('bot_id'),
                'has_token' => $request->has('token'),
                'timestamp' => now()->toDateTimeString()
            ]);

            // تعیین زبان: اول از query string، سپس از language_code ربات در دیتابیس، در نهایت پیش‌فرض fa
            $lang = $request->input('language') ?? $request->query('language');
            
            // اگر language در query string نبود، از language_code ربات از دیتابیس استفاده کن
            if (!$lang) {
                $botId = $request->input('bot_id') ?? $request->query('bot_id');
                if ($botId) {
                    $botModel = \App\Models\Bot::find($botId);
                    if ($botModel && $botModel->language_code) {
                        $lang = $botModel->language_code;
                        Log::info('🌐 [QuranBot] Language from database', [
                            'bot_id' => $botId,
                            'language_code' => $lang
                        ]);
                    }
                }
            }
            
            if ($lang) {
                App::setLocale($lang);
                Log::info('🌐 [QuranBot] Locale set', ['locale' => $lang]);
            } else {
                App::setLocale("fa");
                Log::info('🌐 [QuranBot] Locale set to default', ['locale' => 'fa']);
            }

            $isStartCommandShow = 1;
            $type = $request->input('origin');
            $token = "";
            if ($request->has('origin')) {
                if ($request->input('origin') == 'bale') {
                    $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_BALE");
                    $bot = new Telegram($token, 'bale');
                    Log::info('🤖 [QuranBot] Bot instance created', ['type' => 'bale', 'has_custom_token' => $request->has('token')]);
                } elseif ($request->input('origin') == 'telegram') {
                    $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
                    $bot = new Telegram($token);
                    Log::info('🤖 [QuranBot] Bot instance created', ['type' => 'telegram', 'has_custom_token' => $request->has('token')]);
                } elseif ($request->input('origin') == 'gap') {
                    $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_GAP");
                    $bot = new GapBot($token, $request);
                    $bot->sendText(env("SUPER_ADMIN_CHAT_ID_GAP"), ": " . $bot->ChatID() . " : " . $bot->Text() . " : ");
                    Log::info('🤖 [QuranBot] Bot instance created', ['type' => 'gap', 'has_custom_token' => $request->has('token')]);
                } else {
                    Log::warning('⚠️ [QuranBot] Unknown origin type', ['origin' => $request->input('origin')]);
                    return 200;
                }

                $userSettings = null;
                // استفاده از bot_mother_id از request یا مقدار پیش‌فرض 1
                $botMotherId = $request->input('bot_mother_id', 1);
                
                // بررسی callback query (برای دکمه‌های inline)
                $update = $request->json()->all() ?? $request->all();
                
                // استخراج chat_id از request به صورت امن
                $chatId = null;
                try {
                    if (isset($update['message']['chat']['id'])) {
                        $chatId = $update['message']['chat']['id'];
                    } elseif (isset($update['callback_query']['message']['chat']['id'])) {
                        $chatId = $update['callback_query']['message']['chat']['id'];
                    } elseif (method_exists($bot, 'ChatID')) {
                        $chatId = $bot->ChatID();
                    }
                } catch (Exception $e) {
                    // اگر خطا رخ داد، chat_id را null می‌گذاریم
                    Log::warning('⚠️ [QuranBot] Could not extract chat_id', [
                        'error' => $e->getMessage(),
                        'type' => $type
                    ]);
                }
                
                Log::info('🔘 [CallbackQuery] Checking for callback_query', [
                    'has_callback_query' => isset($update['callback_query']),
                    'type' => $type,
                    'chat_id' => $chatId
                ]);
                
                if (isset($update['callback_query'])) {
                    $callbackQuery = $update['callback_query'];
                    $callbackData = $callbackQuery['data'] ?? '';
                    $callbackChatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
                    $callbackQueryId = $callbackQuery['id'] ?? '';
                    
                    Log::info('🔘 [CallbackQuery] Callback query received', [
                        'callback_data' => $callbackData,
                        'callback_query_id' => $callbackQueryId,
                        'chat_id' => $callbackChatId,
                        'type' => $type,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                    
                    // پاسخ به callback query با try-catch برای جلوگیری از خطا
                    try {
                        if ($callbackQueryId) {
                            Log::info('🔘 [CallbackQuery] Answering callback query', [
                                'callback_query_id' => $callbackQueryId,
                                'type' => $type
                            ]);
                            $bot->answerCallbackQuery([
                                'callback_query_id' => $callbackQueryId,
                                'text' => '',
                            ]);
                            Log::info('✅ [CallbackQuery] Callback query answered successfully', [
                                'callback_query_id' => $callbackQueryId,
                                'type' => $type
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error('❌ [CallbackQuery] Error answering callback query', [
                            'error' => $e->getMessage(),
                            'callback_query_id' => $callbackQueryId,
                            'type' => $type,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    
                    // پردازش انتخاب زبان ترجمه از settings
                    if ($callbackData == 'settings_select_language') {
                        Log::info('🌐 [CallbackQuery] Processing settings_select_language', [
                            'callback_data' => $callbackData,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        
                        // دریافت لیست زبان‌های موجود که ترجمه دارند
                        $availableLanguages = \App\Models\QuranTranslation::query()
                            ->select('language')
                            ->distinct()
                            ->orderBy('language')
                            ->pluck('language')
                            ->toArray();
                        
                        if (empty($availableLanguages)) {
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.no translations available"));
                            return 0;
                        }
                        
                        // نام زبان‌ها
                        $languageNames = [
                            'fa' => '🇮🇷 فارسی',
                            'en' => '🇬🇧 English',
                            'ar-IQ' => '🇮🇶 العربية',
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
                        
                        $message = "🌐 " . trans("bot.select translation language") . ":\n\n";
                        
                        $buttons = [];
                        $buttonRows = [];
                        
                        foreach ($availableLanguages as $lang) {
                            $langName = $languageNames[$lang] ?? $lang;
                            $callbackDataLang = "settings_language_" . $lang;
                            
                            if ($type == 'telegram') {
                                $buttons[] = [
                                    'text' => $langName,
                                    'callback_data' => $callbackDataLang
                                ];
                            } else {
                                $buttonRows[] = [$langName, $callbackDataLang];
                            }
                        }
                        
                        // تقسیم دکمه‌ها به ردیف‌های 2 تایی برای Telegram
                        if ($type == 'telegram') {
                            $chunkedButtons = array_chunk($buttons, 2);
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $chunkedButtons);
                        } else {
                            // برای Bale
                            $inlineKeyboardArray = [];
                            foreach ($buttonRows as $row) {
                                $inlineKeyboardArray[] = [[
                                    "text" => $row[0],
                                    "callback_data" => $row[1]
                                ]];
                            }
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboardArray);
                        }
                        
                        Log::info('✅ [CallbackQuery] Language selection menu sent', [
                            'chat_id' => $callbackChatId,
                            'languages_count' => count($availableLanguages),
                            'type' => $type
                        ]);
                        return 0;
                    }
                    
                    // پردازش انتخاب زبان خاص
                    if (str_starts_with($callbackData, 'settings_language_')) {
                        $selectedLanguage = str_replace('settings_language_', '', $callbackData);
                        
                        Log::info('🌐 [CallbackQuery] Processing language selection', [
                            'selected_language' => $selectedLanguage,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        
                        // normalize کردن کد زبان برای جستجو در دیتابیس
                        $normalizedLanguage = self::normalizeLanguageCodeForDatabase($selectedLanguage);
                        
                        // نمایش لیست ترجمه‌های موجود برای این زبان (هم با کد اصلی و هم normalized)
                        $translations = \App\Models\QuranTranslation::query()
                            ->where(function($query) use ($selectedLanguage, $normalizedLanguage) {
                                $query->where('language', $selectedLanguage);
                                if ($normalizedLanguage != $selectedLanguage) {
                                    $query->orWhere('language', $normalizedLanguage);
                                }
                            })
                            ->select('translator_name', 'translate_full_name', 'language')
                            ->distinct()
                            ->orderBy('translator_name')
                            ->get();
                        
                        if ($translations->isEmpty()) {
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.no translations found for language") . ": " . $selectedLanguage);
                            return 0;
                        }
                        
                        // دریافت ترجمه پیش‌فرض فعلی کاربر
                        $currentTranslator = $userSettings ? $userSettings->setting('quran_translation_translator') : null;
                        $currentLanguage = $userSettings ? $userSettings->setting('quran_translation_language') : null;
                        
                        $message = "📖 " . trans("bot.available translations for language") . " (" . $selectedLanguage . "):\n\n";
                        
                        $buttons = [];
                        $buttonRows = [];
                        $service = new \App\Services\QuranTranslationImportService();
                        
                        foreach ($translations as $index => $translation) {
                            $translatorName = $translation->translator_name;
                            $isCurrent = ($currentLanguage == $selectedLanguage && $currentTranslator == $translatorName);
                            $prefix = $isCurrent ? "✅ " : "";
                            
                            $message .= ($index + 1) . ". " . $prefix . $translatorName;
                            if ($isCurrent) {
                                $message .= " (" . trans("bot.current default") . ")";
                            }
                            $message .= "\n";
                            
                            $callbackDataTrans = "translation_select_" . $selectedLanguage . "_" . $translatorName;
                            $buttonText = $prefix . $translatorName;
                            
                            if ($type == 'telegram') {
                                $buttons[] = [
                                    'text' => $buttonText,
                                    'callback_data' => $callbackDataTrans
                                ];
                            } else {
                                $buttonRows[] = [$buttonText, $callbackDataTrans];
                            }
                        }
                        
                        $message .= "\n" . trans("bot.select translation by clicking button");
                        
                        if ($type == 'telegram') {
                            $chunkedButtons = array_chunk($buttons, 2);
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $chunkedButtons);
                        } else {
                            $inlineKeyboardArray = [];
                            foreach ($buttonRows as $row) {
                                $inlineKeyboardArray[] = [[
                                    "text" => $row[0],
                                    "callback_data" => $row[1]
                                ]];
                            }
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboardArray);
                        }
                        
                        Log::info('✅ [CallbackQuery] Translations list sent for language', [
                            'chat_id' => $callbackChatId,
                            'language' => $selectedLanguage,
                            'translations_count' => $translations->count(),
                            'type' => $type
                        ]);
                        return 0;
                    }
                    
                    // پردازش مشاهده ترجمه‌های زبان فعلی
                    if (str_starts_with($callbackData, 'settings_view_translations_')) {
                        $language = str_replace('settings_view_translations_', '', $callbackData);
                        
                        Log::info('📖 [CallbackQuery] Processing view translations for language', [
                            'language' => $language,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        
                        // normalize کردن کد زبان برای جستجو در دیتابیس
                        $normalizedLanguage = self::normalizeLanguageCodeForDatabase($language);
                        
                        // دریافت ترجمه‌های موجود برای این زبان (هم با کد اصلی و هم normalized)
                        $translations = \App\Models\QuranTranslation::query()
                            ->where(function($query) use ($language, $normalizedLanguage) {
                                $query->where('language', $language);
                                if ($normalizedLanguage != $language) {
                                    $query->orWhere('language', $normalizedLanguage);
                                }
                            })
                            ->select('translator_name', 'translate_full_name', 'language')
                            ->distinct()
                            ->orderBy('translator_name')
                            ->get();
                        
                        if ($translations->isEmpty()) {
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.no translations found for language") . ": " . $language);
                            return 0;
                        }
                        
                        // دریافت ترجمه پیش‌فرض فعلی کاربر
                        $currentTranslator = $userSettings ? $userSettings->setting('quran_translation_translator') : null;
                        $currentLanguage = $userSettings ? $userSettings->setting('quran_translation_language') : null;
                        
                        // اگر setting وجود نداشت، از translation_id استفاده می‌کنیم
                        if (!$currentTranslator && $userSettings) {
                            $translationId = $userSettings->setting('translation_id');
                            if ($translationId > 0) {
                                $mapping = QuranHelper::mapTranslationIdToLanguageAndTranslator($translationId);
                                $currentLanguage = $mapping['language'];
                                $currentTranslator = $mapping['translator'];
                            }
                        }
                        
                        $message = "📖 " . trans("bot.available translations for language") . " (" . $language . "):\n\n";
                        
                        $buttons = [];
                        $buttonRows = [];
                        $service = new \App\Services\QuranTranslationImportService();
                        $hasIncomplete = false;
                        
                        foreach ($translations as $index => $translation) {
                            $translatorName = $translation->translator_name;
                            $fullName = $translation->translate_full_name ?? $language . '.' . $translatorName;
                            
                            // بررسی کامل بودن ترجمه
                            $completeness = $service->checkTranslationCompleteness($language, $translatorName);
                            $isComplete = $completeness['is_complete'];
                            
                            if (!$isComplete) {
                                $hasIncomplete = true;
                            }
                            
                            $isCurrent = ($currentLanguage == $language && $currentTranslator == $translatorName);
                            $prefix = $isCurrent ? "✅ " : "";
                            
                            // علامت‌گذاری ترجمه‌های ناقص
                            $incompleteMark = "";
                            if (!$isComplete) {
                                $incompleteMark = " ⚠️ (" . $completeness['percentage'] . "%)";
                            }
                            
                            $message .= ($index + 1) . ". " . $prefix . $translatorName;
                            if ($isCurrent) {
                                $message .= " (" . trans("bot.current default") . ")";
                            }
                            if (!$isComplete) {
                                $message .= $incompleteMark;
                            }
                            $message .= "\n";
                            
                            // ایجاد دکمه برای انتخاب
                            $callbackDataTrans = "translation_select_" . $language . "_" . $translatorName;
                            $buttonText = ($isCurrent ? "✅ " : "") . $translatorName;
                            if (!$isComplete) {
                                $buttonText .= " ⚠️";
                            }
                            
                            if ($type == 'telegram') {
                                $buttons[] = [
                                    'text' => $buttonText,
                                    'callback_data' => $callbackDataTrans
                                ];
                            } else {
                                $buttonRows[] = [$buttonText, $callbackDataTrans];
                            }
                        }
                        
                        if ($hasIncomplete) {
                            $message .= "\n⚠️ " . trans("bot.incomplete translations marked with warning");
                        }
                        
                        $message .= "\n" . trans("bot.select translation by clicking button");
                        
                        if ($type == 'telegram') {
                            $chunkedButtons = array_chunk($buttons, 2);
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $chunkedButtons);
                        } else {
                            $inlineKeyboardArray = [];
                            foreach ($buttonRows as $row) {
                                $inlineKeyboardArray[] = [[
                                    "text" => $row[0],
                                    "callback_data" => $row[1]
                                ]];
                            }
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboardArray);
                        }
                        
                        Log::info('✅ [CallbackQuery] Translations list sent for current language', [
                            'chat_id' => $callbackChatId,
                            'language' => $language,
                            'translations_count' => $translations->count(),
                            'type' => $type
                        ]);
                        return 0;
                    }
                    
                    // پردازش انتخاب ترجمه
                    if (str_starts_with($callbackData, 'translation_select_')) {
                        Log::info('📖 [CallbackQuery] Processing translation_select', [
                            'callback_data' => $callbackData,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        
                        $parts = explode("_", $callbackData);
                        if (count($parts) >= 4) {
                            $selectedLanguage = $parts[2];
                            $selectedTranslator = $parts[3];
                            
                            Log::info('📖 [CallbackQuery] Translation selection parsed', [
                                'language' => $selectedLanguage,
                                'translator' => $selectedTranslator,
                                'chat_id' => $callbackChatId
                            ]);
                            
                            // دریافت userSettings
                            $userSettings = BotUsers::firstOrNew($callbackChatId, $botMotherId, $type);
                            
                            // ذخیره در setting کاربر
                            $mp3Enable = $userSettings->setting('mp3_enable');
                            $mp3Reciter = $userSettings->setting('mp3_reciter');
                            $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                            $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');
                            $translationId = $userSettings->setting('translation_id');
                            
                            $arr = [
                                'mp3_reciter' => $mp3Reciter,
                                'mp3_enable' => $mp3Enable,
                                'quran_transliteration_tr' => $quranTransliterationTr,
                                'quran_transliteration_en' => $quranTransliterationEn,
                                'translation_id' => $translationId,
                                'quran_translation_language' => $selectedLanguage,
                                'quran_translation_translator' => $selectedTranslator
                            ];
                            
                            $userSettings->settings($arr);
                            
                            Log::info('✅ [CallbackQuery] Translation settings saved', [
                                'chat_id' => $callbackChatId,
                                'language' => $selectedLanguage,
                                'translator' => $selectedTranslator,
                                'type' => $type
                            ]);
                            
                            $message = "✅ " . trans("bot.translation changed to") . ": " . $selectedTranslator . " (" . $selectedLanguage . ")";
                            Log::info('📤 [CallbackQuery] Sending translation confirmation message', [
                                'chat_id' => $callbackChatId,
                                'message_preview' => substr($message, 0, 100),
                                'type' => $type
                            ]);
                            BotHelper::sendMessage($bot, $message);
                            Log::info('✅ [CallbackQuery] Translation confirmation message sent', [
                                'chat_id' => $callbackChatId,
                                'type' => $type
                            ]);
                        } else {
                            Log::warning('⚠️ [CallbackQuery] Invalid translation_select format', [
                                'callback_data' => $callbackData,
                                'parts_count' => count($parts),
                                'chat_id' => $callbackChatId
                            ]);
                        }
                        
                        Log::info('✅ [CallbackQuery] Translation selection processed successfully', [
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        return 0;
                    }
                    
                    // برای دکمه‌های دیگر (مثل next/previous)، callback_data را به عنوان کامند پردازش می‌کنیم
                    // با تنظیم update برای شبیه‌سازی پیام
                    if ($callbackData && !str_starts_with($callbackData, 'translation_select_') && !str_starts_with($callbackData, 'copy_invite_link_')) {
                        Log::info('🔄 [CallbackQuery] Converting callback_data to command', [
                            'callback_data' => $callbackData,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        
                        // تنظیم update برای شبیه‌سازی پیام با callback_data به عنوان text
                        $update['message'] = [
                            'message_id' => $callbackQuery['message']['message_id'] ?? null,
                            'from' => $callbackQuery['from'] ?? [],
                            'chat' => $callbackQuery['message']['chat'] ?? [],
                            'date' => $callbackQuery['message']['date'] ?? time(),
                            'text' => $callbackData
                        ];
                        // حذف callback_query از update برای جلوگیری از پردازش مجدد
                        unset($update['callback_query']);
                        // merge کردن update جدید به request
                        $request->merge($update);
                        // ایجاد bot جدید با update جدید
                        if ($type == 'bale') {
                            $bot = new Telegram($token, 'bale');
                        } elseif ($type == 'telegram') {
                            $bot = new Telegram($token);
                        }
                        
                        Log::info('✅ [CallbackQuery] Callback_data converted to command, continuing processing', [
                            'callback_data' => $callbackData,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        // ادامه پردازش به عنوان کامند عادی (fall through)
                    } else {
                        Log::info('⏭️ [CallbackQuery] Callback_query not processed, returning', [
                            'callback_data' => $callbackData,
                            'chat_id' => $callbackChatId,
                            'type' => $type
                        ]);
                        // برای callback_query های دیگر که پردازش نمی‌شوند، return می‌کنیم
                        return 0;
                    }
                }
                
                // لاگ برای دیباگ
                Log::info('🔍 QuranWordController - Processing request', [
                    'chat_id' => $bot->ChatID(),
                    'bot_mother_id' => $botMotherId,
                    'type' => $type,
                    'bot_text' => $bot->Text(),
                    'has_chat_id' => !empty($bot->ChatID())
                ]);
                
                if ($bot->ChatID() && $botMotherId && $type) {
                    $userSettings = BotUsers::firstOrNew($bot->ChatID(), $botMotherId, $type);
                    
                    // پردازش پارامتر دعوت در دستور /start
                    $botText = $bot->Text();
                    if (str_starts_with($botText, '/start')) {
                        // بررسی پارامتر دعوت: /start 123456 یا /start?start=123456
                        $referralCode = null;
                        
                        // روش 1: /start 123456 (مستقیم)
                        if (preg_match('/^\/start\s+(\d+)$/i', $botText, $matches)) {
                            $referralCode = $matches[1];
                        }
                        // روش 2: /start?start=123456 (از لینک)
                        elseif (str_contains($botText, '?')) {
                            [$command, $params] = BotHelper::getCommandRefferralWhenStart($botText, '?');
                            if (isset($params['start'])) {
                                $referralCode = $params['start'];
                            }
                        }
                        
                        // ذخیره invited_by اگر پارامتر وجود داشت و کاربر قبلاً invited_by نداشت
                        if ($referralCode && !$userSettings->invited_by) {
                            // بررسی اینکه referralCode با chat_id خود کاربر متفاوت باشد
                            if ($referralCode != $bot->ChatID()) {
                                $userSettings->invited_by = $referralCode;
                                $userSettings->save();
                                
                                Log::info('Referral code saved', [
                                    'chat_id' => $bot->ChatID(),
                                    'invited_by' => $referralCode,
                                    'type' => $type
                                ]);
                            }
                        }
                    }
                }


                $arrayCommands = QuranHelper::generateArrayCommands($userSettings);

//            if ((substr($bot->Text(), 0, 2)) == "//")
//                $request->request->add(['command_type' => 'quran_search']);
//            else
//                $request->request->add(['command_type' => 'quran']);

                $command_type = "";

                $botText = Str::lower($bot->Text());
                if ($botText == '/start') {
                    Log::info('📝 [Command] Processing /start command', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_mother_id' => $botMotherId,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "start";
                    $isStartCommandShow = 0;
                    list($message, $messageCommands) = QuranHelper::getStringCommandsStartBot($type);
                    $reciterCommands = QuranHelper::getSettingReciter($type);
                    $array = [[trans('bot.word by word'), "/1"], [trans('bot.ayah after ayah'), "/sure2ayah2"], [trans('bot.List of 114 Surahs'), "/fehrest"], [trans('bot.List of 30 Juz'), "/joz"]];
                    
                    Log::info('📤 [Command] Sending /start message with inline buttons', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'buttons_count' => count($array),
                        'message_length' => strlen($message . $messageCommands . $reciterCommands)
                    ]);
                    
                    if ($type == 'telegram') {
                        BotHelper::sendTelegram4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $array, true);
                        Log::info('✅ [Command] /start message sent via Telegram', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($type == 'gap') {
                        BotHelper::sendGap4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $array);
                        Log::info('✅ [Command] /start message sent via Gap', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else {
                        $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array, $arrayCommands);
                        BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message . $messageCommands, $inlineKeyboard);
                        Log::info('✅ [Command] /start message sent via Bale', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    }
                    
                    Log::info('✅ [Command] /start command processed successfully', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                } elseif ((integer)(substr($bot->Text(), 1, 1)) > 0) {
                    Log::info('📝 [Command] Processing word command', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_text' => $bot->Text(),
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "word";
                    $wordId = QuranHelper::getWordId($bot);
                    [$message, $isEndAya] = QuranHelper::getQuranWordById($wordId);
                    
                    Log::info('📖 [Command] Word data retrieved', [
                        'chat_id' => $bot->ChatID(),
                        'word_id' => $wordId,
                        'is_end_aya' => $isEndAya,
                        'message_length' => strlen($message),
                        'type' => $type
                    ]);
                    
                    $next = ((integer)$wordId == 88246 ? "88246" : ((integer)$wordId + 1));
                    $back = ((integer)$wordId == 1 ? "1" : ((integer)$wordId - 1));

                    if ($isEndAya != 1) {
                        $isStartCommandShow = 0;
                    }

                    Log::info('📤 [Command] Sending word message with next/previous buttons', [
                        'chat_id' => $bot->ChatID(),
                        'word_id' => $wordId,
                        'next' => $next,
                        'back' => $back,
                        'type' => $type
                    ]);

                    if ($type == 'telegram') {
                        BotHelper::sendMessage2Button($bot, $message, "/" . $next, "/" . $back);
                        Log::info('✅ [Command] Word message sent via Telegram', [
                            'chat_id' => $bot->ChatID(),
                            'word_id' => $wordId,
                            'type' => $type
                        ]);
                    } else if ($type == 'gap') {
                        $inlineKeyboard = BotHelper::makeGapKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                        BotHelper::messageGapWithKeyboard($bot, $message, $inlineKeyboard);
                        Log::info('✅ [Command] Word message sent via Gap', [
                            'chat_id' => $bot->ChatID(),
                            'word_id' => $wordId,
                            'type' => $type
                        ]);
                    } else {
                        $inlineKeyboard = BotHelper::makeKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                        BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                        Log::info('✅ [Command] Word message sent via Bale', [
                            'chat_id' => $bot->ChatID(),
                            'word_id' => $wordId,
                            'type' => $type
                        ]);
                    }
                    
                    Log::info('✅ [Command] Word command processed successfully', [
                        'chat_id' => $bot->ChatID(),
                        'word_id' => $wordId,
                        'type' => $type
                    ]);
                } elseif (str_starts_with($botText, StringHelper::command_template_scan)) {
                    Log::info('📝 [Command] Processing scan command', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_text' => $bot->Text(),
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "hr";

                    if (preg_match('/scan(.*?)hr/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                        $pageNumber = (int)$match[1];
                        $page = (integer)$match[1];
                        if ($page > 0) {
                            $hr = (integer)substr($botText, strpos($botText, StringHelper::command_template_hr) + Str::length(StringHelper::command_template_hr));

                            Log::info('📄 [Command] Scan parameters parsed', [
                                'chat_id' => $bot->ChatID(),
                                'page' => $page,
                                'page_number' => $pageNumber,
                                'hr' => $hr,
                                'type' => $type
                            ]);

                            if ($hr > 0) {
                                if ($type == 'telegram' || $type == 'bale') {
                                    Log::info('📄 [Command] Checking for existing scan page in database', [
                                        'chat_id' => $bot->ChatID(),
                                        'hr' => $hr,
                                        'page' => $page,
                                        'type' => $type
                                    ]);
                                    
                                    $quranScanPage = QuranScanPage::query()
                                        ->whereHr($hr)
                                        ->whereType($type)
                                        ->wherePage($page)
                                        ->whereBotId(1)
                                        ->first();

                                    if ($quranScanPage == null || $quranScanPage->count() == 0) {
                                        Log::info('📄 [Command] Scan page not found in database, sending new scan page', [
                                            'chat_id' => $bot->ChatID(),
                                            'hr' => $hr,
                                            'page' => $page,
                                            'type' => $type
                                        ]);
                                        
                                        $photoCallBack = QuranHelper::sendScanPage($bot, $pageNumber, $hr);
                                        if ($photoCallBack['ok'] || $photoCallBack['ok'] == 'true') {
                                            $quranScanPage = $this->saveToQuranScanPagesTable($hr, $page, $type, $photoCallBack['result']);
                                            Log::info('✅ [Command] Scan page saved to database', [
                                                'chat_id' => $bot->ChatID(),
                                                'hr' => $hr,
                                                'page' => $page,
                                                'type' => $type
                                            ]);
                                        } else {
                                            Log::error('❌ [Command] Error sending scan page', [
                                                'chat_id' => $bot->ChatID(),
                                                'hr' => $hr,
                                                'page' => $page,
                                                'type' => $type,
                                                'photo_callback' => $photoCallBack
                                            ]);
                                            BotHelper::sendMessage($bot, "error to find image scan quran");
                                        }
                                    } else {
                                        Log::info('📄 [Command] Scan page found in database, using cached version', [
                                            'chat_id' => $bot->ChatID(),
                                            'hr' => $hr,
                                            'page' => $page,
                                            'type' => $type,
                                            'file_id' => $quranScanPage->file_id
                                        ]);
                                        
                                        $file_id = $quranScanPage->file_id;
                                        $file = $bot->getFile($file_id);

                                        $filePath = '/home/pardisa2/bots/storage/app/public/scan/' . $hr . '/' . $page . '.png';
                                        if ($type == 'bale')
                                            $filePath = '/home/pardisa2/bots/storage/app/public/scan/' . $hr . '/' . StringHelper::get3digitNumber($page) . '.png';

                                        $bot->downloadFile($file['result']['file_path'], $filePath);
                                        $url = 'https://bots.pardisania.ir/api/scan?qsp=' . $quranScanPage->id . '&type=' . $type;

                                        Log::info('📤 [Command] Sending scan page by URL', [
                                            'chat_id' => $bot->ChatID(),
                                            'url' => $url,
                                            'type' => $type
                                        ]);
                                        
                                        $photoCallBack = QuranHelper::sendScanPageByUrl($bot, $url, $pageNumber, $hr);
                                        
                                        Log::info('✅ [Command] Scan page sent by URL', [
                                            'chat_id' => $bot->ChatID(),
                                            'type' => $type
                                        ]);
                                    }
                                } else {
                                    Log::info('📄 [Command] Processing scan for Gap', [
                                        'chat_id' => $bot->ChatID(),
                                        'hr' => $hr,
                                        'page' => $page,
                                        'type' => $type
                                    ]);

                                    $filePath = '/home/pardisa2/bots/storage/app/public/scan/' . $hr . '/' . $page . '.png';
                                    $photoCallBack = QuranHelper::sendScanPageByUrl($bot, $filePath, $pageNumber, $hr);
                                    
                                    Log::info('✅ [Command] Scan page sent for Gap', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                }
                                
                                if ($type == 'bale') {
                                    Log::info('📤 [Command] Sending scan buttons for Bale', [
                                        'chat_id' => $bot->ChatID(),
                                        'page_number' => $pageNumber,
                                        'type' => $type
                                    ]);
                                    QuranHelper::sendScanBaleButtons($pageNumber, $token, $bot, $type);
                                    Log::info('✅ [Command] Scan buttons sent for Bale', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                }
                                
                                if ($type == 'telegram' || $type == 'bale') {
                                    Log::info('🎵 [Command] Sending audio MP3 for scan page', [
                                        'chat_id' => $bot->ChatID(),
                                        'page_number' => $pageNumber,
                                        'type' => $type
                                    ]);
                                    QuranHelper::sendAudioMp3Page($bot, $pageNumber);
                                    Log::info('✅ [Command] Audio MP3 sent for scan page', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                }
                            } else {
                                Log::warning('⚠️ [Command] Invalid hr value for scan command', [
                                    'chat_id' => $bot->ChatID(),
                                    'hr' => $hr,
                                    'type' => $type
                                ]);
                            }
                        } else {
                            Log::warning('⚠️ [Command] Invalid page value for scan command', [
                                'chat_id' => $bot->ChatID(),
                                'page' => $page,
                                'type' => $type
                            ]);
                        }
                    } else {
                        Log::warning('⚠️ [Command] Scan command pattern not matched', [
                            'chat_id' => $bot->ChatID(),
                            'bot_text' => $bot->Text(),
                            'type' => $type
                        ]);
                    }
                    
                    Log::info('✅ [Command] Scan command processed', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                } elseif (str_starts_with($botText, StringHelper::command_template_sure)) {
                    Log::info('📝 [Command] Processing sure command', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_text' => $bot->Text(),
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    if (preg_match(StringHelper::regex_sure, substr($botText, 1, Str::length($botText)), $match) == 1) {
                        $sure = (integer)$match[1];
                        if ($sure > 0) {
                            $aya = (integer)substr($botText, strpos($botText, StringHelper::command_template_ayah) + Str::length(StringHelper::command_template_ayah));

                            Log::info('📖 [Command] Sure command parameters parsed', [
                                'chat_id' => $bot->ChatID(),
                                'sure' => $sure,
                                'aya' => $aya,
                                'type' => $type
                            ]);

                            if ($aya > 0) {
                                $isStartCommandShow = $aya % 10 == 0 ? 1 : 0;
                                $language = $request->input('language', App::getLocale());
                                
                                Log::info('📖 [Command] Getting sure aya data', [
                                    'chat_id' => $bot->ChatID(),
                                    'sure' => $sure,
                                    'aya' => $aya,
                                    'language' => $language,
                                    'type' => $type
                                ]);
                                
                                [$message, $pageNumber] = QuranHelper::getSureAye($userSettings, $sure, $aya, $type, $language);

                                [$maxAyah, $sureName] = QuranHelper::getLastAyeBySurehId($sure);
                                [$maxAyahSureGhabli, $sureGhabliName] = QuranHelper::getLastAyeBySurehId($sure != 1 ? $sure - 1 : 114);

                                $message = QuranHelper::addAyeIdAndBesmella($aya, $sureName, $sure, $message);

                                $nextSure = StringHelper::command_template_sure . ($sure != 114 ? $sure + 1 : 1) . StringHelper::command_template_ayah . "1";
                                $firstAyaOfLastSure = StringHelper::command_template_sure . ($sure - 1) . StringHelper::command_template_ayah . "1";
                                $lastAyaOfLastSure = StringHelper::command_template_sure . ($sure - 1) . StringHelper::command_template_ayah . $maxAyahSureGhabli;

                                $nextAye = ($aya == $maxAyah) ? $nextSure : StringHelper::command_template_sure . ($sure) . StringHelper::command_template_ayah . $aya + 1;
                                $lastAye = ($aya == 1) ? $lastAyaOfLastSure : StringHelper::command_template_sure . ($sure) . StringHelper::command_template_ayah . $aya - 1;

                                if ($aya == $maxAyah || $aya == 1) {
                                    $isStartCommandShow = true;
                                }

                                Log::info('📖 [Command] Sure aya data prepared', [
                                    'chat_id' => $bot->ChatID(),
                                    'sure' => $sure,
                                    'aya' => $aya,
                                    'max_ayah' => $maxAyah,
                                    'next_aye' => $nextAye,
                                    'last_aye' => $lastAye,
                                    'message_length' => strlen($message),
                                    'type' => $type
                                ]);

                                $array = [[trans('bot.next aya'), $nextAye], [trans('bot.previous aya'), $lastAye], [trans('bot.next surah'), $nextSure], [trans('bot.previous surah'), $firstAyaOfLastSure]];
                                
                                Log::info('📤 [Command] Sending sure aya message with inline buttons', [
                                    'chat_id' => $bot->ChatID(),
                                    'sure' => $sure,
                                    'aya' => $aya,
                                    'buttons_count' => count($array),
                                    'type' => $type
                                ]);
                                
                                if ($type == 'telegram') {
                                    BotHelper::sendTelegram4InlineMessage($bot, $message, $array, true);
                                    Log::info('✅ [Command] Sure aya message sent via Telegram', [
                                        'chat_id' => $bot->ChatID(),
                                        'sure' => $sure,
                                        'aya' => $aya,
                                        'type' => $type
                                    ]);
                                } else if ($type == 'gap') {
                                    BotHelper::sendGap4InlineMessage($bot, $message, $array);
                                    Log::info('✅ [Command] Sure aya message sent via Gap', [
                                        'chat_id' => $bot->ChatID(),
                                        'sure' => $sure,
                                        'aya' => $aya,
                                        'type' => $type
                                    ]);
                                } else {
                                    $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array, $arrayCommands);
                                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                                    Log::info('✅ [Command] Sure aya message sent via Bale', [
                                        'chat_id' => $bot->ChatID(),
                                        'sure' => $sure,
                                        'aya' => $aya,
                                        'type' => $type
                                    ]);
                                    
                                    QuranHelper::sendScanBaleButtons($pageNumber, $token, $bot, $type);
                                    Log::info('✅ [Command] Scan buttons sent for Bale', [
                                        'chat_id' => $bot->ChatID(),
                                        'page_number' => $pageNumber,
                                        'type' => $type
                                    ]);
                                }

                                if ($bot->BotType() != "gap") {
                                    Log::info('🎵 [Command] Sending audio MP3 for aya', [
                                        'chat_id' => $bot->ChatID(),
                                        'sure' => $sure,
                                        'aya' => $aya,
                                        'type' => $type
                                    ]);
                                    
                                    QuranHelper::sendAudioMp3Aye($aya, $sure, $bot, $userSettings);
                                    
                                    if (App::getLocale()) {
                                        $postfix = config("reciter.audio." . App::getLocale(), '');
                                        if ($postfix) {
                                            Log::info('🎵 [Command] Sending audio MP3 by locale', [
                                                'chat_id' => $bot->ChatID(),
                                                'sure' => $sure,
                                                'aya' => $aya,
                                                'locale' => App::getLocale(),
                                                'postfix' => $postfix,
                                                'type' => $type
                                            ]);
                                            QuranHelper::sendAudioMp3AyeByLocale($aya, $sure, $bot, $postfix, $userSettings);
                                            Log::info('✅ [Command] Audio MP3 by locale sent', [
                                                'chat_id' => $bot->ChatID(),
                                                'type' => $type
                                            ]);
                                        }
                                    }
                                    
                                    Log::info('✅ [Command] Audio MP3 sent for aya', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                }
                                
                                Log::info('✅ [Command] Sure command processed successfully', [
                                    'chat_id' => $bot->ChatID(),
                                    'sure' => $sure,
                                    'aya' => $aya,
                                    'type' => $type
                                ]);
                            } else {
                                Log::warning('⚠️ [Command] Invalid aya value for sure command', [
                                    'chat_id' => $bot->ChatID(),
                                    'sure' => $sure,
                                    'aya' => $aya,
                                    'type' => $type
                                ]);
                            }
                        } else {
                            Log::warning('⚠️ [Command] Invalid sure value', [
                                'chat_id' => $bot->ChatID(),
                                'sure' => $sure,
                                'type' => $type
                            ]);
                        }
                    } else {
                        Log::warning('⚠️ [Command] Sure command pattern not matched', [
                            'chat_id' => $bot->ChatID(),
                            'bot_text' => $bot->Text(),
                            'type' => $type
                        ]);
                    }
                } elseif ((substr($bot->Text(), 0, 4)) == "////") {
                    Log::info('📝 [Command] Processing //// command (message to all admins)', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "////";

                    $request->request->add(['to_admins' => "true"]);

                    $this->messageToAll($request);
                    
                    Log::info('✅ [Command] //// command processed', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                } elseif ((substr($bot->Text(), 0, 3)) == "///") {
                    Log::info('📝 [Command] Processing /// command (message to all users)', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "///";
                    $request->request->add(['to_admins' => "false"]);
                    $this->messageToAll($request);
                    
                    Log::info('✅ [Command] /// command processed', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                } elseif ((substr($bot->Text(), 0, 2)) == "//") {
                    Log::info('📝 [Command] Processing // command (search)', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_text' => $bot->Text(),
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "//";
                    $searchPhrase = substr($bot->Text(), 2, strlen($bot->Text()));
                    [$searchPhrase, $pageNumber] = QuranHelper::getPageNumberFromPhrase($searchPhrase);
                    
                    Log::info('🔍 [Command] Search parameters prepared', [
                        'chat_id' => $bot->ChatID(),
                        'search_phrase' => $searchPhrase,
                        'page_number' => $pageNumber,
                        'type' => $type
                    ]);
                    
                    QuranHelper::findResultThenSend($searchPhrase, $pageNumber, $type, $bot);
                    
                    Log::info('✅ [Command] Search command processed', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                } elseif ((substr($bot->Text(), 0, 1)) == "/") {
                    Log::info('📝 [Command] Processing command', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type,
                        'bot_text' => $bot->Text(),
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $command_type = "commands";

                    $command = substr($botText, strpos($botText, "/") + Str::length("/"));
                    
                    Log::info('📝 [Command] Command extracted', [
                        'chat_id' => $bot->ChatID(),
                        'command' => $command,
                        'type' => $type
                    ]);
                    
                    if ($command == "fehrest") {
                        Log::info('📝 [Command] Processing /fehrest command', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        // اطلاع به کاربر که در حال پردازش است
                        // BotHelper::sendMessage($bot, trans("bot.processing your request"));
                        
                        if ($type == 'telegram') {
                            Log::info('📤 [Command] Generating and sending fehrest via Telegram', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::generateTelegramFehrestThenSendIt($bot);
                            Log::info('✅ [Command] Fehrest sent via Telegram', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        } else if ($type == 'gap') {
                            Log::info('📤 [Command] Generating and sending fehrest via Gap', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::generateGapFehrestThenSendIt($bot);
                            Log::info('✅ [Command] Fehrest sent via Gap', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        } else {
                            Log::info('📤 [Command] Generating and sending fehrest via Bale', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::generateBaleFehrestThenSendIt($bot, $token);
                            Log::info('✅ [Command] Fehrest sent via Bale', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        // Send last activities after fehrest with common buttons
                        $lastActivitiesMessage = QuranHelper::getLastActivitiesMessage($bot->ChatID(), $type);
                        if (!empty($lastActivitiesMessage)) {
                            Log::info('📤 [Command] Sending last activities after fehrest', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::sendMessageWithCommonButtons($bot, $lastActivitiesMessage, $type, $token);
                            Log::info('✅ [Command] Last activities sent after fehrest', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        Log::info('✅ [Command] /fehrest command processed successfully', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($command == "joz") {
                        Log::info('📝 [Command] Processing /joz command', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        
                        if ($type != 'bale') {
                            Log::info('📤 [Command] Generating and sending joz via Telegram/Gap', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::generateJozLinksThenSendItTelegram($bot);
                            Log::info('✅ [Command] Joz sent via Telegram/Gap', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        } else {
                            Log::info('📤 [Command] Generating and sending joz via Bale', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::generateJozLinksThenSendItBale($bot);
                            Log::info('✅ [Command] Joz sent via Bale', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        // Send last activities after joz with common buttons
                        $lastActivitiesMessage = QuranHelper::getLastActivitiesMessage($bot->ChatID(), $type);
                        if (!empty($lastActivitiesMessage)) {
                            Log::info('📤 [Command] Sending last activities after joz', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            QuranHelper::sendMessageWithCommonButtons($bot, $lastActivitiesMessage, $type, $token);
                            Log::info('✅ [Command] Last activities sent after joz', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        Log::info('✅ [Command] /joz command processed successfully', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($command == "report") {
                        Log::info('📝 [Command] Processing /report command', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        
                        Log::info('📤 [Command] Sending processing message for report', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        BotHelper::sendMessage($bot, trans("bot.processing your request"));
                        
                        $chatId = $bot->ChatID();
                        Log::info('📊 [Command] Generating user report', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                        $this->quranBotUserRankingService->specificUserReport($chatId, $bot);
                        $message = trans("bot.report.this is your reports. your last 7 days activities. click on this link:") . "
                    https://bots.pardisania.ir/report?chat_id=" . $chatId . '&language=' . $request->input('language') . '&origin=' . $type;
                        
                        Log::info('📤 [Command] Sending report message', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                        BotHelper::sendMessage($bot, $message);
                        BotHelper::sendMessageToSuperAdmin($message, $type);
                        
                        Log::info('✅ [Command] /report command processed successfully', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                    } else if ($command == "reportall") {
                        Log::info('📝 [Command] Processing /reportall command', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        
                        if ($type == 'telegram') {
                            Log::info('⚠️ [Command] /reportall not supported in Telegram', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.this command not work in telegram"));
                        } else {
                            if (AdminHelper::isAdmin($bot->ChatID())) {
                                Log::info('📤 [Command] Sending processing message for reportall', [
                                    'chat_id' => $bot->ChatID(),
                                    'type' => $type
                                ]);
                                BotHelper::sendMessage($bot, trans("bot.processing your request"));
                                
                                Log::info('📊 [Command] Generating all users report', [
                                    'chat_id' => $bot->ChatID(),
                                    'type' => $type
                                ]);
                                $this->quranBotUserRankingService->allUsersReportDailyWeeklyMonthly($type);
                                
                                // Send last activities after reportall with common buttons
                                $lastActivitiesMessage = QuranHelper::getLastActivitiesMessage($bot->ChatID(), $type);
                                if (!empty($lastActivitiesMessage)) {
                                    Log::info('📤 [Command] Sending last activities after reportall', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                    QuranHelper::sendMessageWithCommonButtons($bot, $lastActivitiesMessage, $type, $token);
                                    Log::info('✅ [Command] Last activities sent after reportall', [
                                        'chat_id' => $bot->ChatID(),
                                        'type' => $type
                                    ]);
                                }
                                
                                Log::info('✅ [Command] /reportall command processed successfully', [
                                    'chat_id' => $bot->ChatID(),
                                    'type' => $type
                                ]);
                            } else {
                                Log::warning('⚠️ [Command] User is not admin for /reportall', [
                                    'chat_id' => $bot->ChatID(),
                                    'type' => $type
                                ]);
                                BotHelper::sendMessage($bot, "🚫 " . trans("bot.you are not admin"));
                            }
                        }
                    } else if ($command == "listcommands" || $command == "help") {
                        Log::info('📝 [Command] Processing /listcommands or /help command', [
                            'chat_id' => $bot->ChatID(),
                            'command' => $command,
                            'type' => $type
                        ]);
                        
                        $message = QuranHelper::getHelpMessage($type);
                        Log::info('📤 [Command] Sending help message', [
                            'chat_id' => $bot->ChatID(),
                            'message_length' => strlen($message),
                            'type' => $type
                        ]);
                        BotHelper::sendMessage($bot, $message);
                        Log::info('✅ [Command] Help message sent', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($command == "settings") {
                        Log::info('📝 [Command] Processing /settings command', [
                            'chat_id' => $bot->ChatID(),
                            'command' => $command,
                            'type' => $type
                        ]);
                        
                        // دریافت زبان فعلی کاربر
                        $currentLanguage = $userSettings ? $userSettings->setting('quran_translation_language') : App::getLocale();
                        if (!$currentLanguage) {
                            $currentLanguage = App::getLocale();
                        }
                        
                        $message = "⚙️ " . trans("bot.settings menu") . "\n\n";
                        $message .= "📖 " . trans("bot.current translation language") . ": " . $currentLanguage . "\n\n";
                        $message .= trans("bot.select option from menu");
                        
                        // ساخت دکمه‌های منوی تنظیمات
                        $buttons = [];
                        $buttonRows = [];
                        
                        // دکمه انتخاب زبان ترجمه
                        $selectLanguageText = "🌐 " . trans("bot.select translation language");
                        $selectLanguageCallback = "settings_select_language";
                        
                        // دکمه مشاهده ترجمه‌های زبان فعلی
                        $viewTranslationsText = "📖 " . trans("bot.view translations for current language");
                        $viewTranslationsCallback = "settings_view_translations_" . $currentLanguage;
                        
                        if ($type == 'telegram') {
                            $buttons[] = [
                                ['text' => $selectLanguageText, 'callback_data' => $selectLanguageCallback],
                                ['text' => $viewTranslationsText, 'callback_data' => $viewTranslationsCallback]
                            ];
                        } else {
                            // برای Bale
                            $buttonRows[] = [$selectLanguageText, $selectLanguageCallback];
                            $buttonRows[] = [$viewTranslationsText, $viewTranslationsCallback];
                        }
                        
                        if ($type == 'telegram') {
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $buttons);
                        } else {
                            // برای Bale
                            $inlineKeyboardArray = [];
                            foreach ($buttonRows as $row) {
                                $inlineKeyboardArray[] = [[
                                    "text" => $row[0],
                                    "callback_data" => $row[1]
                                ]];
                            }
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboardArray);
                        }
                        
                        Log::info('✅ [Command] Settings menu sent', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($command == "lastactivities" || $command == "last") {
                        Log::info('📝 [Command] Processing /lastactivities or /last command', [
                            'chat_id' => $bot->ChatID(),
                            'command' => $command,
                            'type' => $type
                        ]);
                        
                        // Send last activities with common buttons
                        $lastActivitiesMessage = QuranHelper::getLastActivitiesMessage($bot->ChatID(), $type);
                        if (empty($lastActivitiesMessage)) {
                            $lastActivitiesMessage = "📚 " . trans("bot.your last activities") . "\n\n" . trans("bot.no activities found");
                            Log::info('📚 [Command] No activities found, using default message', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        Log::info('📤 [Command] Sending last activities', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                        QuranHelper::sendMessageWithCommonButtons($bot, $lastActivitiesMessage, $type, $token);
                        Log::info('✅ [Command] Last activities sent', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($command == "invite" || $command == "دعوت") {
                        Log::info('📝 [Command] Processing /invite command', [
                            'chat_id' => $bot->ChatID(),
                            'command' => $command,
                            'type' => $type
                        ]);
                        // دستور دعوت
                        $chatId = $bot->ChatID();
                        Log::info('🔗 [Command] Generating invitation link', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                        
                        $invitationLink = QuranHelper::getInvitationLink($chatId, $type, $token);
                        
                        if (!$invitationLink) {
                            Log::error('❌ [Command] Error generating invitation link', [
                                'chat_id' => $chatId,
                                'type' => $type
                            ]);
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.error generating invitation link"));
                            return 0;
                        }
                        
                        Log::info('✅ [Command] Invitation link generated', [
                            'chat_id' => $chatId,
                            'invitation_link' => substr($invitationLink, 0, 50) . '...',
                            'type' => $type
                        ]);
                        
                        // پیام دعوت
                        $message = trans("bot.invitation message") . "\n\n";
                        $message .= "🔗 " . trans("bot.your invitation link") . ":\n";
                        $message .= $invitationLink . "\n\n";
                        $message .= "💡 " . trans("bot.how to invite others") . " 🤔\n";
                        $message .= trans("bot.just forward this message") . " ⨁👉\n\n";
                        
                        // آمار دعوت‌شدگان (اگر وجود داشته باشد)
                        $referralStats = $this->quranBotUserRankingService->getReferralStatistics($chatId);
                        if ($referralStats['total_invitees'] > 0) {
                            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                            $message .= $this->quranBotUserRankingService->getReferralStatisticsMessage($chatId) . "\n";
                            Log::info('📊 [Command] Referral statistics added to message', [
                                'chat_id' => $chatId,
                                'total_invitees' => $referralStats['total_invitees'],
                                'type' => $type
                            ]);
                        }
                        
                        // دکمه‌ها
                        $buttonText = trans('bot.copy invitation link');
                        $buttonCallback = 'copy_invite_link_' . $chatId;
                        
                        Log::info('📤 [Command] Sending invitation message with button', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                        
                        if ($type == 'telegram') {
                            $buttons = [[
                                'text' => $buttonText,
                                'callback_data' => $buttonCallback
                            ]];
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $buttons);
                            Log::info('✅ [Command] Invitation message sent via Telegram', [
                                'chat_id' => $chatId,
                                'type' => $type
                            ]);
                        } else {
                            // برای Bale
                            $buttonArray = [[$buttonText, $buttonCallback]];
                            $inlineKeyboard = $bot->buildInlineKeyBoard([[
                                $bot->buildInlineKeyBoardButton($buttonText, callback_data: $buttonCallback)
                            ]]);
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                            Log::info('✅ [Command] Invitation message sent via Bale', [
                                'chat_id' => $chatId,
                                'type' => $type
                            ]);
                        }
                        
                        Log::info('✅ [Command] /invite command processed successfully', [
                            'chat_id' => $chatId,
                            'type' => $type
                        ]);
                    } else if ($command == "translation_list" || $command == "translation") {
                        Log::info('📝 [Command] Processing /translation_list or /translation command', [
                            'chat_id' => $bot->ChatID(),
                            'command' => $command,
                            'type' => $type
                        ]);
                        
                        // نمایش لیست ترجمه‌های موجود
                        $language = $request->input('language', App::getLocale());
                        if (!$language || $language == '') {
                            $language = 'fa';
                        }
                        
                        // normalize کردن کد زبان برای جستجو در دیتابیس
                        // مثلاً ar-IQ -> ar, de-DE -> de, zh-CN -> zh
                        $normalizedLanguage = self::normalizeLanguageCodeForDatabase($language);
                        
                        // دریافت ترجمه‌های موجود برای این زبان (هم با کد اصلی و هم normalized)
                        $translations = \App\Models\QuranTranslation::query()
                            ->where(function($query) use ($language, $normalizedLanguage) {
                                $query->where('language', $language);
                                if ($normalizedLanguage != $language) {
                                    $query->orWhere('language', $normalizedLanguage);
                                }
                            })
                            ->select('translator_name', 'translate_full_name', 'language')
                            ->distinct()
                            ->orderBy('translator_name')
                            ->get();
                        
                        Log::info('📖 [Command] Getting translations for language', [
                            'chat_id' => $bot->ChatID(),
                            'language' => $language,
                            'type' => $type
                        ]);
                        
                        if ($translations->isEmpty()) {
                            Log::warning('⚠️ [Command] No translations found for language', [
                                'chat_id' => $bot->ChatID(),
                                'language' => $language,
                                'type' => $type
                            ]);
                            BotHelper::sendMessage($bot, "❌ " . trans("bot.no translations found for language") . ": " . $language);
                            return 0;
                        }
                        
                        Log::info('📖 [Command] Translations found', [
                            'chat_id' => $bot->ChatID(),
                            'language' => $language,
                            'translations_count' => $translations->count(),
                            'type' => $type
                        ]);
                        
                        // دریافت ترجمه پیش‌فرض فعلی کاربر
                        $currentTranslator = $userSettings ? $userSettings->setting('quran_translation_translator') : null;
                        $currentLanguage = $userSettings ? $userSettings->setting('quran_translation_language') : null;
                        
                        // اگر setting وجود نداشت، از translation_id استفاده می‌کنیم
                        if (!$currentTranslator && $userSettings) {
                            $translationId = $userSettings->setting('translation_id');
                            if ($translationId > 0) {
                                $mapping = QuranHelper::mapTranslationIdToLanguageAndTranslator($translationId);
                                $currentLanguage = $mapping['language'];
                                $currentTranslator = $mapping['translator'];
                            }
                        }
                        
                        $message = "📖 " . trans("bot.available translations for language") . " (" . $language . "):\n\n";
                        
                        $buttons = [];
                        $buttonRows = [];
                        $service = new \App\Services\QuranTranslationImportService();
                        $hasIncomplete = false;
                        
                        foreach ($translations as $index => $translation) {
                            $translatorName = $translation->translator_name;
                            $fullName = $translation->translate_full_name ?? $language . '.' . $translatorName;
                            
                            // بررسی کامل بودن ترجمه
                            $completeness = $service->checkTranslationCompleteness($language, $translatorName);
                            $isComplete = $completeness['is_complete'];
                            
                            if (!$isComplete) {
                                $hasIncomplete = true;
                            }
                            
                            $isCurrent = ($currentLanguage == $language && $currentTranslator == $translatorName);
                            $prefix = $isCurrent ? "✅ " : "";
                            
                            // علامت‌گذاری ترجمه‌های ناقص
                            $incompleteMark = "";
                            if (!$isComplete) {
                                $incompleteMark = " ⚠️ (" . $completeness['percentage'] . "%)";
                            }
                            
                            $message .= ($index + 1) . ". " . $prefix . $translatorName;
                            if ($isCurrent) {
                                $message .= " (" . trans("bot.current default") . ")";
                            }
                            if (!$isComplete) {
                                $message .= $incompleteMark;
                            }
                            $message .= "\n";
                            
                            // ایجاد دکمه برای انتخاب
                            $callbackData = "translation_select_" . $language . "_" . $translatorName;
                            $buttonText = ($isCurrent ? "✅ " : "") . $translatorName;
                            if (!$isComplete) {
                                $buttonText .= " ⚠️";
                            }
                            
                            if ($type == 'telegram') {
                                $buttons[] = [
                                    'text' => $buttonText,
                                    'callback_data' => $callbackData
                                ];
                            } else {
                                // برای Bale
                                $buttonRows[] = [$buttonText, $callbackData];
                            }
                        }
                        
                        if ($hasIncomplete) {
                            $message .= "\n⚠️ " . trans("bot.incomplete translations marked with warning");
                        }
                        
                        $message .= "\n" . trans("bot.select translation by clicking button");
                        
                        Log::info('📤 [Command] Sending translation list message with buttons', [
                            'chat_id' => $bot->ChatID(),
                            'language' => $language,
                            'buttons_count' => count($type == 'telegram' ? $buttons : $buttonRows),
                            'type' => $type
                        ]);
                        
                        if ($type == 'telegram') {
                            // برای Telegram، دکمه‌ها را به صورت 2 ستونی نمایش می‌دهیم
                            $chunkedButtons = array_chunk($buttons, 2);
                            BotHelper::sendTelegramInlineMessageWithButtons($bot, $message, $chunkedButtons);
                            Log::info('✅ [Command] Translation list sent via Telegram', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        } else {
                            // برای Bale - ساخت inline keyboard از آرایه
                            $inlineKeyboardArray = [];
                            foreach ($buttonRows as $row) {
                                $inlineKeyboardArray[] = [[
                                    "text" => $row[0],
                                    "callback_data" => $row[1]
                                ]];
                            }
                            $inlineKeyboard = $inlineKeyboardArray;
                            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                            Log::info('✅ [Command] Translation list sent via Bale', [
                                'chat_id' => $bot->ChatID(),
                                'type' => $type
                            ]);
                        }
                        
                        Log::info('✅ [Command] /translation_list command processed successfully', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else {
                        // پردازش دستورات با underscore (مثل /mp3_true, /trans_2)
                        if (strpos($command, "_") !== false) {
                            $subCommand = substr($command, 0, strpos($command, "_"));
                            $value = substr($command, strpos($command, "_") + 1);
                            
                            Log::info('📝 [Command] Processing underscore command', [
                                'chat_id' => $bot->ChatID(),
                                'command' => $command,
                                'sub_command' => $subCommand,
                                'value' => $value,
                                'type' => $type
                            ]);
//                dd($command, $subCommand, $value);

                            if ($subCommand == "mp3") {
                                Log::info('📝 [Command] Processing /mp3 command', [
                                    'chat_id' => $bot->ChatID(),
                                    'value' => $value,
                                    'type' => $type
                                ]);
                                
                                $mp3Reciter = $userSettings->setting('mp3_reciter');
                                $translationId = $userSettings->setting('translation_id');
                                $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                                $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');
                                
                                $arr = [
                                    'mp3_reciter' => $mp3Reciter,
                                    'mp3_enable' => $value,
                                    'quran_transliteration_tr' => $quranTransliterationTr,
                                    'quran_transliteration_en' => $quranTransliterationEn,
                                    'translation_id' => $translationId
                                ];

                                $user = $userSettings->settings($arr);
                                $mp3Enable = $user->setting('mp3_enable');

                                $message = $mp3Enable == "true" ? trans("bot.enabled") : trans("bot.disabled");
                                $pleaseEnableDisable = $mp3Enable == "true" ? trans("bot.please disable mp3 by") : trans("bot.please enable mp3 by");
                                
                                Log::info('📤 [Command] Sending mp3 status message', [
                                    'chat_id' => $bot->ChatID(),
                                    'mp3_enable' => $mp3Enable,
                                    'type' => $type
                                ]);
                                
                                BotHelper::sendMessage($bot, $message . " " . $pleaseEnableDisable . " /mp3_" . ($mp3Enable == "true" ? "false" : "true"));
                                
                                Log::info('✅ [Command] /mp3 command processed successfully', [
                                    'chat_id' => $bot->ChatID(),
                                    'mp3_enable' => $mp3Enable,
                                    'type' => $type
                                ]);
                            } else if ($subCommand == "transtr") {
                                $mp3Enable = $userSettings->setting('mp3_enable');
                                $mp3Reciter = $userSettings->setting('mp3_reciter');
//                    $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                                $translationId = $userSettings->setting('translation_id');
                                $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');

                                $arr = [
                                    'mp3_reciter' => $mp3Reciter,
                                    'mp3_enable' => $mp3Enable,
                                    'quran_transliteration_tr' => $value,
                                    'quran_transliteration_en' => $quranTransliterationEn,
                                    'translation_id' => $translationId
                                ];

                                $user = $userSettings->settings($arr);
                                $quranTransliterationTr = $user->setting('quran_transliteration_tr');

                                $message = $quranTransliterationTr == "true" ? trans("bot.enabled") : trans("bot.disabled");
                                $pleaseEnableDisable = $quranTransliterationTr == "true" ? trans("bot.please disable it by") : trans("bot.please enable it by");
                                BotHelper::sendMessage($bot, $message . " " . $pleaseEnableDisable . " /transtr_" . ($quranTransliterationTr == "true" ? "false" : "true"));
                            } else if ($subCommand == "transen") {
                                $mp3Enable = $userSettings->setting('mp3_enable');
                                $mp3Reciter = $userSettings->setting('mp3_reciter');
                                $translationId = $userSettings->setting('translation_id');
                                $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
//                    $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');

                                $arr = [
                                    'mp3_reciter' => $mp3Reciter,
                                    'mp3_enable' => $mp3Enable,
                                    'quran_transliteration_tr' => $quranTransliterationTr,
                                    'quran_transliteration_en' => $value,
                                    'translation_id' => $translationId
                                ];

                                $user = $userSettings->settings($arr);
                                $quranTransliterationEn = $user->setting('quran_transliteration_en');

                                $message = $quranTransliterationEn == "true" ? trans("bot.enabled") : trans("bot.disabled");
                                $pleaseEnableDisable = $quranTransliterationEn == "true" ? trans("bot.please disable it by") : trans("bot.please enable it by");
                                BotHelper::sendMessage($bot, $message . " " . $pleaseEnableDisable . " /transen_" . ($quranTransliterationEn == "true" ? "false" : "true"));
                            } else if ($subCommand == "trans") {
                                $mp3Enable = $userSettings->setting('mp3_enable');
                                $mp3Reciter = $userSettings->setting('mp3_reciter');
//                    $translationId = $userSettings->setting('translation_id');
                                $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                                $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');

                                $arr = [
                                    'mp3_reciter' => $mp3Reciter,
                                    'mp3_enable' => $mp3Enable,
                                    'quran_transliteration_tr' => $quranTransliterationTr,
                                    'quran_transliteration_en' => $quranTransliterationEn,
                                    'translation_id' => $value
                                ];

                                $user = $userSettings->settings($arr);
                                $translationId = $user->setting('translation_id');

                                $message = $translationId == "2" ? trans("bot.trans_2") : trans("bot.trans_3");
                                $pleaseEnableDisable = $translationId == "2" ? trans("bot.please change it to trans_3") : trans("bot.please change it to trans_2");
                                BotHelper::sendMessage($bot, $message . " " . $pleaseEnableDisable . " /trans_" . ($translationId == "2" ? "3" : "2"));
                            } else if ($subCommand == "mp3reciter") {
//                    $mp3Enable = $userSettings->setting('mp3_enable');
                                $mp3Enable = "true";
                                $translationId = $userSettings->setting('translation_id');
                                $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                                $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');

                                $arr = [
                                    'mp3_reciter' => $value,
                                    'mp3_enable' => $mp3Enable,
                                    'quran_transliteration_tr' => $quranTransliterationTr,
                                    'quran_transliteration_en' => $quranTransliterationEn,
                                    'translation_id' => $translationId
                                ];

                                $user = $userSettings->settings($arr);
                                $mp3Reciter = $user->setting('mp3_reciter');

//                    dd($userSettings->setting('mp3_reciter'));

//                    dd($mp3Enable, $value);
                                if ($mp3Enable == "true") {
                                    $message = trans('bot.this reciter :reciter selected', ['reciter' => trans('bot.' . $value)]) . "
" . "/mp3reciter_alafasy";
                                } else {
                                    $message = " " . trans('bot.please enable mp3 by') . " : /mp3_true";
                                }

                                BotHelper::sendMessage($bot, $message);
                            }
                        }
                    }
                } else {
                    Log::warning('⚠️ [Command] Command not recognized', [
                        'chat_id' => $bot->ChatID(),
                        'bot_text' => $bot->Text(),
                        'type' => $type
                    ]);
                    
                    $message = trans('bot.bot cant recognized your command') . " /start";
                    Log::info('📤 [Command] Sending unrecognized command message', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                    BotHelper::sendMessage($bot, $message);
                    Log::info('✅ [Command] Unrecognized command message sent', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                }


                if ($isStartCommandShow) {
                    Log::info('📤 [Command] Sending start command button', [
                        'chat_id' => $bot->ChatID(),
                        'type' => $type
                    ]);
                    
                    $array = [[trans("bot.return to command list"), "/start"]];
                    $message = $array[0][0];
                    if ($type == 'telegram') {
                        BotHelper::send1button($bot, $array);
                        BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                        Log::info('✅ [Command] Start command button sent via Telegram', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    } else if ($type == 'gap') {
//                    BotHelper::sendStart($bot, $array);
//                    BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                    } else {
                        $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
                        BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                        Log::info('✅ [Command] Start command button sent via Bale', [
                            'chat_id' => $bot->ChatID(),
                            'type' => $type
                        ]);
                    }
                }

                $request->request->add(['command_type' => $command_type]);

                Log::info('📝 [QuranBot] Saving bot log', [
                    'chat_id' => $bot->ChatID(),
                    'command_type' => $command_type,
                    'type' => $type
                ]);
                
                LogHelper::log($request, $type, $bot);
                
                Log::info('✅ [QuranBot] Bot log saved', [
                    'chat_id' => $bot->ChatID(),
                    'type' => $type
                ]);

                $endTime = microtime(true);
                $processingTime = round(($endTime - $startTime) * 1000, 2);
                
                Log::info('✅ [QuranBot] Request processed successfully', [
                    'chat_id' => $bot->ChatID(),
                    'command_type' => $command_type,
                    'type' => $type,
                    'processing_time_ms' => $processingTime,
                    'timestamp' => now()->toDateTimeString()
                ]);

            } else {
                Log::warning('⚠️ [QuranBot] No origin in request', [
                    'timestamp' => now()->toDateTimeString()
                ]);
                return ResponseAlias::HTTP_ACCEPTED;
            }


        } catch (Exception $exception) {
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);
            
            // بررسی اینکه آیا bot و type موجود هستند یا نه
            $chatId = null;
            $botText = null;
            
            try {
                if (isset($bot) && is_object($bot)) {
                    $chatId = method_exists($bot, 'ChatID') ? $bot->ChatID() : null;
                    $botText = method_exists($bot, 'Text') ? $bot->Text() : null;
                }
            } catch (Exception $e) {
                // اگر خطا در دسترسی به bot رخ داد، از request استفاده می‌کنیم
                $update = $request->json()->all() ?? $request->all();
                if (isset($update['message']['chat']['id'])) {
                    $chatId = $update['message']['chat']['id'];
                } elseif (isset($update['callback_query']['message']['chat']['id'])) {
                    $chatId = $update['callback_query']['message']['chat']['id'];
                }
                if (isset($update['message']['text'])) {
                    $botText = $update['message']['text'];
                }
            }
            
            Log::error('❌ [QuranBot] Exception occurred', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'chat_id' => $chatId,
                'type' => $type ?? null,
                'bot_text' => $botText,
                'processing_time_ms' => $processingTime,
                'timestamp' => now()->toDateTimeString()
            ]);
            
            return 0;
        }

        return 0;
    }


    /**
     * Show the form for creating a new resource.
     * @throws GuzzleException
     */
    public
    function messageToAll(BotRequest $request)
    {
        // تعیین زبان: اول از query string، سپس از language_code ربات در دیتابیس، در نهایت پیش‌فرض fa
        $lang = $request->input('language') ?? $request->query('language');
        
        // اگر language در query string نبود، از language_code ربات از دیتابیس استفاده کن
        if (!$lang) {
            $botId = $request->input('bot_id') ?? $request->query('bot_id');
            if ($botId) {
                $botModel = \App\Models\Bot::find($botId);
                if ($botModel && $botModel->language_code) {
                    $lang = $botModel->language_code;
                }
            }
        }
        
        if ($lang) {
            App::setLocale($lang);
        } else {
            App::setLocale("fa");
        }

        $type = $request->input('origin');
        $token = "";
        $count = 0;
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_BALE");
                $bot = new Telegram($token, 'bale');
            } elseif ($request->input('origin') == 'telegram') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
                $bot = new Telegram($token);
            } else {
                return 200;
            }


            if (AdminHelper::isAdminCommand($bot->Text())) {
                if (AdminHelper::isAdmin($bot->ChatID())) {
                    if ($type == 'telegram') {
                        BotHelper::sendMessage($bot, "لطفا از روبات پیام رسان بله، این پیام را برای همه بفرستید");
                    } else {
                        if ($request->request->get('to_admins') == "false") {
                            $message = AdminHelper::getMessageAdmin($bot->Text());
                        } else {
                            $message = AdminHelper::getMessageAdmin($bot->Text(), 4);
                        }

                        $botBale = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_BALE'), 'bale');
                        $botTelegram = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM'), 'telegram');

                        if ($request->request->get('to_admins') == "false") {
                            $logs = BotLog::where('created_at', '>=', Carbon::now()->subDay(500))
                                ->whereWebhookEndpointUri('webhook-quran-word')
                                ->whereLanguage('fa')
                                ->select('chat_id', 'type')
                                ->distinct('chat_id')
                                ->get();
                        } else {
                            $logs = BotLog::where('created_at', '>=', Carbon::now()->subDay(5))
                                ->whereWebhookEndpointUri('webhook-quran-word')
                                ->whereIn('chat_id', AdminHelper::getAdmins())
                                ->whereLanguage('fa')
                                ->select('chat_id', 'type')
                                ->distinct('chat_id')
                                ->get();
                        }

                        foreach ($logs as $log) {
                            $count = $logs->count();
                            try {

                                if ($log['type'] == 'bale') {
                                    if (QuranHelper::isContainSureAyahCommand($message)) {
                                        [$command, $messageButton] = QuranHelper::getCommandByRegex($message);
                                        $array = [[$messageButton, $command]];
                                        $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
                                        BotHelper::messageWithKeyboard($token, $log['chat_id'], $message, $inlineKeyboard);
                                    } else {
                                        BotHelper::sendMessageByChatId($botBale, $log['chat_id'], $message);
                                    }
                                } else {
                                    BotHelper::sendMessageByChatId($botTelegram, $log['chat_id'], $message);
                                    if (QuranHelper::isContainSureAyahCommand($message)) {
                                        [$command, $messageButton] = QuranHelper::getCommandByRegex($message);
                                        $array = [[$messageButton, $command]];
                                        BotHelper::send1buttonToChatId($botTelegram, $array, $log['chat_id']);
                                    }
                                }
                            } catch (\Exception $exception) {
                                Log::info($exception->getMessage());
                            }
                        }
                    }
                    BotHelper::sendMessage($bot, trans("bot.sent it for :count person", ["count" => $count]));
                }
            }
        }
        return true;
    }

    /**
     * @param int $hr
     * @param int $page
     * @param mixed $type
     * @param $result
     * @return void
     */
    public
    function saveToQuranScanPagesTable(int $hr, int $page, mixed $type, $result): QuranScanPage
    {
//                                BotHelper::sendMessageToSuperAdmin(json_encode($result), $type);

//                                BotHelper::sendMessageToSuperAdmin(json_encode($photoCallBack['ok']), $type);

//                                BotHelper::sendMessageToSuperAdmin(json_encode($photoCallBack['result']['photo'][0]['file_id']), $type);

        $quranScanPage = new QuranScanPage();
        $quranScanPage->hr = $hr;
        $quranScanPage->page = $page;
        $quranScanPage->type = $type;

        $index = 2;
        if ($type == 'bale')
            $index = 0;

        $quranScanPage->file_id = $result['photo'][$index]['file_id'];
        $quranScanPage->file_unique_id = $result['photo'][$index]['file_id'];
        $quranScanPage->width = $result['photo'][$index]['width'];
        $quranScanPage->height = $result['photo'][$index]['height'];
        $quranScanPage->file_size = $result['photo'][$index]['file_size'];
        $quranScanPage->bot_chat_id = $result['from']['id'];
        $quranScanPage->bot_id = 1;
        $quranScanPage->save();
        return $quranScanPage;
    }


}
