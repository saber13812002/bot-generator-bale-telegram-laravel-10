<?php

namespace App\Helpers;

use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\QuranAyat;
use App\Models\QuranSearchSuggestion;
use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTransliterationEn;
use App\Models\QuranTransliterationTr;
use App\Models\QuranWord;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;
use Telegram;
use App\Helpers\FileUploadHelper;

class QuranHelper
{
    /**
     * Supported languages list for special handling in PlaceQuran
     * We will use 'ar,en' variant for these languages, otherwise fallback to 'ar'
     */
    private const PLACEQURAN_SUPPORTED_LANGUAGES = ['ar', 'en', 'ms', 'id', 'tr', 'ur', 'hi'];

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

    /**
     * @param $messenger
     * @param $suraId
     * @param $ayaId
     * @param BotUsers|null $userSettings
     * @return void
     */
    public static function sendAudio($messenger, $suraId, $ayaId, BotUsers $userSettings = null): void
    {
        // TODO: cache
        //
        $aye = QuranAyat::query()
            ->whereSura($suraId)
            ->whereAya($ayaId)
            ->first();
//        dd($aye->id);

        $chat_id = $messenger->ChatID();

        $mp3Enable = self::getBooleanSettingsByTags($userSettings, 'mp3_enable');

        if ($mp3Enable == "true") {
            $mp3Reciter = self::getSettingsByTags($userSettings, 'mp3_reciter');
            $audio = self::getAudioUrl($mp3Reciter, $aye);

            $caption = self::getSettingReciter($messenger->BotType());
            $title = self::getAyeDescription($aye);

            $botType = $messenger->BotType();

            // استفاده از FileUploadHelper برای چک کردن file_id
            if ($botType != "gap") {
                $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('audio_recitation', [
                    'reciter' => $mp3Reciter,
                    'sura' => $suraId,
                    'aya' => $ayaId
                ], $botType);

                $fileInfo = FileUploadHelper::getOrUploadFile(
                    $messenger,
                    $fileUniqueKey,
                    $audio,
                    'audio_recitation',
                    [
                        'reciter' => $mp3Reciter,
                        'sura' => $suraId,
                        'aya' => $ayaId,
                        'title' => $title,
                        'caption' => $caption
                    ]
                );

                // اگر file_id موجود است، از آن استفاده می‌کنیم
                if ($fileInfo && isset($fileInfo['file_id']) && $fileInfo['is_cached']) {
                    $content = [
                        'chat_id' => $chat_id,
                        'audio' => $fileInfo['file_id'],
                        'title' => $title,
                        'caption' => $caption
                    ];
                    $messenger->sendAudio($content);
                    return;
                }
            }

            // اگر file_id موجود نبود یا gap است، از روش قبلی استفاده می‌کنیم
            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
                'title' => $title,
                'caption' => $caption,
            ];

            if ($botType != "gap")
                $messenger->sendAudio($content);
            else {
                // if not exist download then upload then deleted then save to db

                // if exist and uploaded
                $message_id = $messenger->sendAudio($chat_id, $audio, $caption, null, null, null);
            }
        }
    }

    /**
     * @param $messenger
     * @param $suraId
     * @param $ayaId
     * @param BotUsers|null $userSettings
     * @param $postfix
     * @return void
     */
    public static function sendAudioByLocale($messenger, $suraId, $ayaId, BotUsers $userSettings = null, $postfix): void
    {
        // TODO: cache
        $aye = QuranAyat::query()
            ->whereSura($suraId)
            ->whereAya($ayaId)
            ->first();

        $chat_id = $messenger->ChatID();

        $mp3Enable = self::getBooleanSettingsByTags($userSettings, 'mp3_enable');

        if ($mp3Enable == "true") {
            $base_url = "https://tanzil.ir/res/audio/" . $postfix . "/";
            //            https://tanzil.ir/res/audio/fa.makarem/001003.mp3
            $audio = $base_url . StringHelper::get3digitNumber($suraId) . StringHelper::get3digitNumber($ayaId) . ".mp3";


            // https://tanzil.ir/res/audio/fa.makarem/001003.mp3

            $caption = self::getSettingReciter($messenger->BotType());
            $title = self::getAyeDescription($aye);
            $botType = $messenger->BotType();

            // استفاده از FileUploadHelper برای چک کردن file_id
            if ($botType != "gap") {
                $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('audio_translation', [
                    'locale' => $postfix,
                    'sura' => $suraId,
                    'aya' => $ayaId
                ], $botType);

                $fileInfo = FileUploadHelper::getOrUploadFile(
                    $messenger,
                    $fileUniqueKey,
                    $audio,
                    'audio_translation',
                    [
                        'locale' => $postfix,
                        'sura' => $suraId,
                        'aya' => $ayaId,
                        'title' => $title,
                        'caption' => $caption
                    ]
                );

                // اگر file_id موجود است، از آن استفاده می‌کنیم
                if ($fileInfo && isset($fileInfo['file_id']) && $fileInfo['is_cached']) {
                    $content = [
                        'chat_id' => $chat_id,
                        'audio' => $fileInfo['file_id'],
                        'title' => $title,
                        'caption' => $caption
                    ];
                    $messenger->sendAudio($content);
                    return;
                }
            }

            // اگر file_id موجود نبود یا gap است، از روش قبلی استفاده می‌کنیم
            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
                'title' => $title,
                'caption' => $caption
            ];

            if ($botType != "gap")
                $messenger->sendAudio($content);
            else {
                $message_id = $messenger->sendAudio($chat_id, $audio, $caption, null, null, null);
            }
        }
    }

    /**
     * @param string $type
     * @return string
     */
    public static function getSettingReciter(string $type = 'bale'): string
    {
        $current = self::getDefaultReciter();
        $caption = trans("bot.current reciter :reciter", ['reciter' => self::getReciterName($current)]);
        $caption .= "\n" . trans("bot.change reciter") . " : /mp3reciter";
        $caption .= "\n" . trans("bot.disable enable reciter") . " : /mp3_true /mp3_false";
        return $caption;
    }

    /**
     * @param $aye
     * @return string
     */
    public static function getAyeDescription($aye): string
    {
        return " سوره شماره ی " . $aye->sura . "
آیه شماره ی  " . $aye->aya . "
جز " . $aye->juz . "
حزب " . $aye->hezb . "
صفحه " . $aye->page;
    }

    /**
     * Full reciter registry from config/quran_reciters.php
     *
     * @return array<string, array{name: array<string, string>, url: string, file: string}>
     */
    public static function getReciterRegistry(): array
    {
        return (array) config('quran_reciters.reciters', []);
    }

    /**
     * Default reciter key (config/quran_reciters.php => default)
     */
    public static function getDefaultReciter(): string
    {
        return (string) config('quran_reciters.default', 'parhizgar');
    }

    /**
     * Whether a reciter key exists in the registry
     */
    public static function hasReciter(mixed $mp3Reciter): bool
    {
        return array_key_exists((string) $mp3Reciter, self::getReciterRegistry());
    }

    /**
     * Resolve a reciter key to a known registry key, falling back to the default
     */
    public static function resolveReciter(mixed $mp3Reciter): string
    {
        return self::hasReciter($mp3Reciter) ? (string) $mp3Reciter : self::getDefaultReciter();
    }

    /**
     * Localized display name for a reciter (locale -> fa -> en -> first defined)
     */
    public static function getReciterName(mixed $mp3Reciter, ?string $locale = null): string
    {
        $key = self::resolveReciter($mp3Reciter);
        $names = (array) (self::getReciterRegistry()[$key]['name'] ?? []);
        if (empty($names)) {
            return $key;
        }
        $locale = $locale ?: App::getLocale();
        return $names[$locale] ?? $names['fa'] ?? $names['en'] ?? reset($names);
    }

    /**
     * Convert western digits to Arabic-Indic digits (0-9 to ۰-۹)
     */
    public static function toArabicDigits(int|string $number): string
    {
        return strtr((string) $number, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /**
     * Base audio URL for a reciter, from the registry
     *
     * @param mixed $mp3Reciter
     * @return string
     */
    public static function getUrl(mixed $mp3Reciter): string
    {
        $key = self::resolveReciter($mp3Reciter);
        $url = (string) (self::getReciterRegistry()[$key]['url'] ?? '');
        return rtrim($url, '/') . '/';
    }

    /**
     * @param BotUsers|null $userSettings
     * @param $tag
     * @return mixed|null
     */
    public static function getSettingsByTags(?BotUsers $userSettings, $tag): mixed
    {
//        dd($userSettings);
        if ($userSettings != null) {
            $mp3Reciter = $userSettings->setting($tag);
//            dd($mp3Reciter);
            if (!$mp3Reciter && $tag == "mp3_reciter")
                $mp3Reciter = self::getDefaultReciter();
            return $mp3Reciter;
        }
        return "";
    }


    /**
     * @param BotUsers|null $userSettings
     * @param $tag
     * @return mixed|null
     */
    public static function getBooleanSettingsByTags(?BotUsers $userSettings, $tag): mixed
    {
        $mp3Enable = "false";
//        dd($userSettings);
        if ($userSettings != null) {
            $mp3Enable = $userSettings->setting($tag) == "true" ? "true" : "false";
        }
        return $mp3Enable;
    }

    /**
     * @param string $mp3Reciter
     * @return string
     */
    public static function getAudioBaseUrl(string $mp3Reciter): string
    {
//dd($mp3Reciter);
        $base_url = self::getUrl($mp3Reciter);
//        dd($base_url);
        return $base_url;
    }

    public static function sendScanPage(Telegram $messenger, int $pageNumber, int $hr)
    {
        $photoUrl = self::getScanFullUrl($pageNumber, $hr, $messenger->BotType());

        return self::createTitleCaptionSendScan($messenger, $pageNumber, $hr, $photoUrl);
    }


    public static function sendScanPageByUrl($messenger, string $photoUrl, int $pageNumber, int $hr)
    {
        // استفاده از FileUploadHelper برای چک کردن file_id
        $botType = $messenger->BotType();
        if ($botType != 'gap') {
            $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('scan_page', [
                'hr' => $hr,
                'page' => $pageNumber
            ], $botType);

            $fileInfo = FileUploadHelper::getOrUploadFile(
                $messenger,
                $fileUniqueKey,
                $photoUrl,
                'scan_page',
                [
                    'hr' => $hr,
                    'page' => $pageNumber,
                    'bot_type' => $botType
                ]
            );

            // اگر file_id موجود است، از آن استفاده می‌کنیم
            if ($fileInfo && isset($fileInfo['file_id']) && $fileInfo['is_cached']) {
                $chat_id = $messenger->ChatID();
                $title = "#" . trans("bot.page") . "_" . $pageNumber;
                $caption = $title;
                if ($messenger->BotType() != 'bale') {
                    $caption = self::getCaptionTelegram($pageNumber, $hr, $messenger->BotType());
                }

                $content = [
                    'chat_id' => $chat_id,
                    'photo' => $fileInfo['file_id'],
                    'caption' => $caption,
                    'parse_mode' => "HTML"
                ];

                return $messenger->sendPhoto($content);
            }
        }

        // اگر file_id موجود نبود یا gap است، از روش قبلی استفاده می‌کنیم
        return self::createTitleCaptionSendScan($messenger, $pageNumber, $hr, $photoUrl);
    }


    public static function getScanFullUrl(string $pageNumber, int $hr, $botType)
    {
        if ($hr == 1)
            return self::getBaseUrlScan($hr, $botType) . StringHelper::get3digitNumber($pageNumber) . ".jpg";
        return self::getBaseUrlScan($hr, $botType) . StringHelper::get3digitNumber($pageNumber) . ".png";
//        if ($botType == 'telegram')
    }

    public static function getBaseUrlScan(int $hr, $botType)
    {
        if ($hr == 1)
            return "https://rayed.com/Quran/img/";
        else if ($hr == 2)
            return "https://ia802709.us.archive.org/6/items/ALQURANPERPAGEFORMATPNG/page";
        else if ($hr == 3)
            return "https://raw.githubusercontent.com/tarekeldeeb/madina_images/w1024/w1024_page";
        else if ($hr == 4)
            return "https://cdn.jsdelivr.net/gh/tarekeldeeb/madina_images@w1024/w1024_page";
        //https://cdn.jsdelivr.net/gh/tarekeldeeb/madina_images@w1024/w1024_page001.png
        //https://raw.githubusercontent.com/tarekeldeeb/madina_images/w1024/w1024_page001.png
        // https://archive.org/details/ALQURANPERPAGEFORMATPNG/page002.png
        // https://ia802709.us.archive.org/6/items/ALQURANPERPAGEFORMATPNG/page003.png
    }

    public static function sendAudioMp3Page($messenger, string $pageNumber)
    {

        $chat_id = $messenger->ChatID();

        $base_url = "https://ia800304.us.archive.org/32/items/quran-by--maher-alm3eaqli---128-kb----604-part-full-quran-604-page--safahat-mp3/Page";

        $audio = $base_url . $pageNumber . ".mp3";

        $caption = $pageNumber . "-" . ".mp3";
        $botType = $messenger->BotType();

        // استفاده از FileUploadHelper برای چک کردن file_id
        if ($botType != "gap") {
            $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('audio_page', [
                'page' => $pageNumber
            ], $botType);

            $fileInfo = FileUploadHelper::getOrUploadFile(
                $messenger,
                $fileUniqueKey,
                $audio,
                'audio_page',
                [
                    'page' => $pageNumber,
                    'title' => $caption,
                    'caption' => $caption
                ]
            );

            // اگر file_id موجود است، از آن استفاده می‌کنیم
            if ($fileInfo && isset($fileInfo['file_id']) && $fileInfo['is_cached']) {
                $content = [
                    'chat_id' => $chat_id,
                    'audio' => $fileInfo['file_id'],
                    'title' => $caption,
                    'caption' => $caption,
                    'parse_mode' => "html"
                ];
                return $messenger->sendAudio($content);
            }
        }

        // اگر file_id موجود نبود یا gap است، از روش قبلی استفاده می‌کنیم
        $content = [
            'chat_id' => $chat_id,
            'audio' => $audio,
            'title' => $caption,
            'caption' => $caption,
            'parse_mode' => "html"
        ];

        if ($botType != "gap")
            return $messenger->sendAudio($content);

    }

    /**
     * @param $userSettings
     * @param $sure
     * @param $aye
     * @param $type
     * @return array
     * @throws \Exception
     */
    public static function getSureAye($userSettings, $sure, $aye, $type, $language = null): array
    {
        $quranWords = QuranWord::query()->whereSura($sure)->whereAya($aye)->get();
        $message = "";
        $pageNumber = 0;
        foreach ($quranWords as $quranWord) {
            $message .= " " . $quranWord['text'];
            $pageNumber = $quranWord['page'];
        }

        // شماره آیه به ارقام عربی در انتهای متن عربی
        $message .= " ﴿" . self::toArabicDigits((int) $aye) . "﴾";

        // دریافت language از پارامتر یا از App::getLocale() یا از setting کاربر
        if (!$language) {
            $language = \Illuminate\Support\Facades\App::getLocale();
        }
        
        // اگر language هنوز null است، از پیش‌فرض استفاده می‌کنیم
        if (!$language || $language == '') {
            $language = 'fa';
        }
        
        // دریافت translator از setting کاربر
        $translator = self::getSettingsByTags($userSettings, 'quran_translation_translator');
        
        // اگر translator وجود نداشت، از translation_id قدیمی استفاده می‌کنیم (برای سازگاری)
        if (!$translator) {
            $translationId = self::getSettingsByTags($userSettings, 'translation_id');
            if ($translationId > 0) {
                $mapping = self::mapTranslationIdToLanguageAndTranslator($translationId);
                $language = $mapping['language'];
                $translator = $mapping['translator'];
            }
        }

        // استفاده از متد جدید برای دریافت ترجمه
        $quranTranslate = self::getQuranTranslation($language, $translator, $sure, $aye, $userSettings);

        if (!$quranTranslate) {
            $logMessage = "quranTranslate is null with: language:" . $language . ", translator:" . ($translator ?? 'null') . ", sura:" . $sure . ", aya:" . $aye;
            BotHelper::sendMessageToSuperAdmin($logMessage, 'bale');
            BotHelper::sendMessageToSuperAdmin($logMessage, 'telegram');
            Log::error($logMessage);
        }

        if ($quranTranslate) {
            $message .= "

" . $quranTranslate['text'] . " : (" . $sure . ":" . $aye . ")";

            $index = $quranTranslate['index'];
        } else {
            $index = null;
        }

        $trTransliteration = self::getSettingsByTags($userSettings, 'quran_transliteration_tr');
        $enTransliteration = self::getSettingsByTags($userSettings, 'quran_transliteration_en');

        if ($index && ($trTransliteration == 'true' || $enTransliteration == 'true')) {

            $quranTransliterationTr = QuranTransliterationTr::query()->whereIndex($index)->first();

            $quranTransliterationEn = QuranTransliterationEn::query()->whereIndex($index)->first();
            if ($trTransliteration == 'true') {
                $message .= "

" . $quranTransliterationTr['quran_transliteration_tr'] . "
" . trans("bot.to disable") . " : /transtr_false";
            }

            if ($enTransliteration == 'true') {
                $message .= "

" . $quranTransliterationEn['quran_transliteration_en'] . "
" . trans("bot.to disable") . " : /transen_false";
            }
        }
//        else {
//            $message .= "
//" . trans("bot.to enable transliteration") . " : /transen_true /transtr_true ";
//        }

        if (trim($message) === "") {
            $message = trans("bot.not found") . " (" . $sure . ":" . $aye . ")";
        }
        return [$message, $pageNumber];
    }

    /**
     * دریافت ترجمه قرآن از جدول quran_translations
     * 
     * @param string $language زبان ترجمه
     * @param string|null $translator نام مترجم (اختیاری)
     * @param int $sura شماره سوره
     * @param int $aya شماره آیه
     * @param BotUsers|null $userSettings تنظیمات کاربر (برای fallback به translation_id)
     * @return QuranTranslation|null
     */
    public static function getQuranTranslation(string $language, ?string $translator, int $sura, int $aya, ?BotUsers $userSettings = null): ?QuranTranslation
    {
        // normalize کردن کد زبان برای جستجو (مثلاً ar-IQ -> ar)
        $normalizedLanguage = self::normalizeLanguageCodeForDatabase($language);
        
        // اگر translator مشخص شده باشد، از آن استفاده می‌کنیم
        if ($translator) {
            // ابتدا با کد اصلی جستجو می‌کنیم
            $quranTranslate = QuranTranslation::query()
                ->where('language', $language)
                ->where('translator_name', $translator)
                ->where('sura', $sura)
                ->where('aya', $aya)
                ->first();
            
            if ($quranTranslate) {
                return $quranTranslate;
            }
            
            // اگر با کد اصلی پیدا نشد و normalized متفاوت است، با normalized جستجو می‌کنیم
            if ($normalizedLanguage != $language) {
                $quranTranslate = QuranTranslation::query()
                    ->where('language', $normalizedLanguage)
                    ->where('translator_name', $translator)
                    ->where('sura', $sura)
                    ->where('aya', $aya)
                    ->first();
                
                if ($quranTranslate) {
                    return $quranTranslate;
                }
            }
        }
        
        // اگر translator مشخص نشده یا پیدا نشد، اولین ترجمه موجود برای آن زبان را برمی‌گردانیم
        // ابتدا با کد اصلی جستجو می‌کنیم
        $quranTranslate = QuranTranslation::query()
            ->where('language', $language)
            ->where('sura', $sura)
            ->where('aya', $aya)
            ->orderBy('id')
            ->first();
        
        if ($quranTranslate) {
            return $quranTranslate;
        }
        
        // اگر با کد اصلی پیدا نشد و normalized متفاوت است، با normalized جستجو می‌کنیم
        if ($normalizedLanguage != $language) {
            $quranTranslate = QuranTranslation::query()
                ->where('language', $normalizedLanguage)
                ->where('sura', $sura)
                ->where('aya', $aya)
                ->orderBy('id')
                ->first();
            
            if ($quranTranslate) {
                return $quranTranslate;
            }
        }
        
        // اگر هنوز پیدا نشد، با دو حرف اول زبان جستجو می‌کنیم (fallback)
        // این برای حالتی است که مثلاً ar-IQ ترجمه نداشته باشد، ar را جستجو می‌کند
        $languagePrefix = substr($normalizedLanguage, 0, 2);
        if (strlen($languagePrefix) == 2) {
            // جستجو با دو حرف اول (مثلاً ar برای ar-IQ)
            $quranTranslate = QuranTranslation::query()
                ->where('language', 'like', $languagePrefix . '%')
                ->where('sura', $sura)
                ->where('aya', $aya)
                ->orderBy('id')
                ->first();
            
            if ($quranTranslate) {
                return $quranTranslate;
            }
        }
        
        // Fallback به translation_id برای سازگاری با داده‌های قدیمی
        if ($userSettings) {
            $translationId = self::getSettingsByTags($userSettings, 'translation_id');
            if ($translationId > 0) {
                $quranTranslate = QuranTranslation::query()
                    ->where('translation_id', $translationId)
                    ->where('sura', $sura)
                    ->where('aya', $aya)
                    ->first();
                
                if ($quranTranslate) {
                    return $quranTranslate;
                }
            }
        }
        
        // Fallback به translation_id پیش‌فرض (2 = fa.ansarian)
        $quranTranslate = QuranTranslation::query()
            ->where('translation_id', 2)
            ->where('sura', $sura)
            ->where('aya', $aya)
            ->first();
        
        return $quranTranslate;
    }

    /**
     * تبدیل translation_id قدیمی به language و translator_name
     * 
     * @param int $translationId
     * @return array{language: string, translator: string}
     */
    public static function mapTranslationIdToLanguageAndTranslator(int $translationId): array
    {
        $mapping = [
            1 => ['language' => 'am', 'translator' => 'sadiq'],
            2 => ['language' => 'fa', 'translator' => 'ansarian'],
            3 => ['language' => 'fa', 'translator' => 'ayati'],
        ];
        
        return $mapping[$translationId] ?? ['language' => 'fa', 'translator' => 'ansarian'];
    }

    public static function getLastAyeBySurehId(mixed $sure): array
    {
        $quranSurahs = QuranSurah::select('ayah', 'arabic')->whereId($sure)->get()->first();
        return [$quranSurahs->count() > 0 ? $quranSurahs['ayah'] : 0, $quranSurahs['arabic']];
    }

    public static function getQuranWordById(mixed $botText): array
    {
        $idEndAya = 0;
        $quranWords = QuranWord::query()->whereId($botText)->first();
        if (!$quranWords) {
            return [0, 0];
        }
        $word = $quranWords['text'] ?: '(' . $quranWords['aya'] . ')';
        if ($quranWords['char_type'] == "end") {
            $idEndAya = 1;
        }
        return [$word, $idEndAya];
    }

    /**
     * @param int $aya
     * @param mixed $maxAyah
     * @param string $nextAye
     * @param string $lastAye
     * @param int $sure
     * @param string $nextSure
     * @param string $lastSure
     * @return string
     */
    public static function getStringCommandsAyaBaya(int $aya, mixed $maxAyah, string $nextAye, string $lastAye, int $sure, string $nextSure, string $lastSure): string
    {
        return "
===============
" . ((($aya + 1) > $maxAyah) ? "" : "
آیه بعدی
" . $nextAye) . "
" . ($aya - 1 == 0 ? "" : "
آیه قبلی
" . $lastAye) . "
" . (($sure + 1) == 115 ? "" : "
سوره بعدی
" . $nextSure . "
") . (($sure - 1) == 0 ? "" : "
سوره قبلی
" . $lastSure . "
");
    }

    /**
     * @param int|string $next
     * @param int|string $back
     * @return string
     */
    public static function getStringCommandsWordByWord(int|string $next, int|string $back): string
    {
        return "
===============
بعدی:/" . $next . "
قبلی:/" . $back;
    }

    /**
     * @return string[]
     */
    public static function getStringCommandsStartBot($type): array
    {
        $message = "
بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
" .
            trans('bot.this bot support 2 methods') . " : " .
            trans('bot.word by word') . " و " .
            trans('bot.ayah after ayah') . " .\n" .
            trans('bot.List of 114 Surahs') . " و " .
            trans('bot.List of 30 Juz') . " نیز از منوی پایین در دسترس است.\n\n" .
            trans('bot.search') . " : " .
            trans('bot.search prompt');
        return array($message, "");
    }


    /**
     * @param int $resultsCount
     * @param int $pageNumber
     * @param $searchPhrase
     * @return string
     */
    public static function getResultCountText(int $resultsCount, int $pageNumber, $searchPhrase): string
    {
        if ($resultsCount == 0) {
            return trans("bot.no results found");
        }
        return trans("bot.n results found", ['count' => $resultsCount]);
    }

    /**
     * @param mixed $searchPhrase
     * @param int $pageNumber
     * @param mixed $type
     * @param $bot
     * @return void
     */
    public static function findResultThenSend(mixed $searchPhrase, int $pageNumber, mixed $type, $bot, string $token = ''): void
    {
        $searchPhrase = IndexedRecord::normalize($searchPhrase);
        $results = self::getResultSearch($searchPhrase, $pageNumber);
        $resultsCount = $results->count();

        if ($resultsCount === 1) {
            $first = $results->first();
            $indexable = $first->indexable ?? null;
            if ($indexable && isset($indexable->sura, $indexable->aya)) {
                QuranSearchSuggestion::create([
                    'search_phrase' => mb_substr($searchPhrase, 0, 200),
                    'result_count' => 1,
                    'sura' => $indexable->sura,
                    'aya' => $indexable->aya,
                    'chat_id' => $bot->ChatID(),
                    'type' => $type,
                    'source' => QuranSearchSuggestion::SOURCE_SINGLE_RESULT,
                ]);
            }
        }

        // لیست تمیز و شماره‌دار نتایج + یک دکمه برای هر نتیجه
        $message = "🔍 " . $searchPhrase . "\n\n" . self::getResultCountText($resultsCount, $pageNumber, $searchPhrase) . "\n";
        $buttons = [];
        $index = ($pageNumber - 1) * config("laravel-fulltext.limit-results-page");

        foreach ($results as $item) {
            $indexable = $item->indexable;
            if (!$indexable) {
                continue;
            }
            $index++;
            $highlight = self::highlighter($searchPhrase, (string) $item->indexed_title, '<b>', '</b>');
            $message .= $index . ". " . $highlight . "\n";
            $buttons[] = [
                'text' => trans('bot.surah number :sura', ['sura' => $indexable->suras->arabic . ' (' . $indexable->sura . ')']) . ' — ' . trans('bot.ayah number :aya', ['aya' => $indexable->aya]),
                'callback_data' => '/sure' . $indexable->sura . 'ayah' . $indexable->aya,
            ];
        }

        // دکمه صفحه بعدی در صورت وجود نتایج بیشتر
        if ($resultsCount >= config('laravel-fulltext.limit-results-page')) {
            $buttons[] = ['text' => '⬅️ ' . trans('bot.next page'), 'callback_data' => '//' . $searchPhrase . 'page' . ($pageNumber + 1)];
        }

        // دکمه‌های مشترک (بازگشت به منو، آخرین فعالیت‌ها، جستجو)
        foreach (self::getCommonActionButtons($type) as $commonButton) {
            $buttons[] = ['text' => $commonButton[0], 'callback_data' => $commonButton[1]];
        }

        BotHelper::sendButtonGridMessage($bot, $message, $buttons, $type, $token, 1);

        self::sendReportMessageToSuperAdmins($searchPhrase, self::getResultCountText($resultsCount, $pageNumber, $searchPhrase), $bot);
    }

    /**
     * @param array|string $botText
     * @param string $resultText
     * @param $bot
     * @return void
     * @throws \Exception
     */
    public static function sendReportMessageToSuperAdmins(array|string $botText, string $resultText, $bot): void
    {
        $msg = "جستجوی #قرآن: " . $botText . "
" . $resultText . "
" . $bot->ChatID() . "
" . $bot->Username() . "
" . $bot->FirstName() . "
" . $bot->LastName();
        BotHelper::sendMessageToSuperAdmin($msg, 'bale');
        BotHelper::sendMessageToSuperAdmin($msg, 'telegram');
//        BotHelper::sendMessageToSuperAdmin($msg, 'gap');
    }

    /**
     * @param string $searchPhrase
     * @param $pageNumber
     * @return Collection|IndexedRecord
     */
    public static function getResultSearch(string $searchPhrase, $pageNumber): Collection|array
    {
        $search = new Search();
        return $search->runForClass($searchPhrase, QuranAyat::class)->forPage($pageNumber, 10);
//        $results0 = QuranAyat::query()->where('simple', 'like', '%' . $botText . '%')->paginate();

//        $paginate = QuranAyatResource::collection($results);
//        dd($results->count());
//        dd($results->items());
    }

    /**
     * @param mixed $item
     * @param mixed $type
     * @param Telegram $bot
     * @param string $message
     * @param mixed $token
     * @return void
     * @throws GuzzleException
     */
    public static function sendMessageForEveryResult(mixed $item, mixed $type, Telegram $bot, string $message, mixed $token): void
    {
        $array = [[trans("bot.surah number:") . $item->suras->id . "-" . $item->suras->arabic, StringHelper::command_template_sure . $item->sura . StringHelper::command_template_ayah . $item->aya]];
//                dd($array,$token,$message,$array);
        if ($type == 'telegram') {
            BotHelper::send1buttonWithMessage($bot, $message, $array);
        } else {
            $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
        }
    }

    /**
     * @param string $keyword
     * @param mixed $longText
     * @param $start
     * @param $end
     * @return string
     */
    public static function highlighter(string $keyword, string $longText, $start, $end): string
    {
        $highlight = preg_replace("/\w*?$keyword\w*/i", $start . "$0" . $end, $longText);
        if (strlen($longText) > 200) {
            $position = strpos($longText, $keyword);

            $numStr = preg_replace("/\w*?$keyword\w*/i", '____', $longText);
            $sum = array_sum(explode('____', $numStr));
            if ($sum < 2) {
                if ($position < 70) {
                    $highlight = Str::substr($highlight, 0, 100) . "...";
                } elseif ($position < 140) {
                    $highlight = Str::substr($highlight, 60, 170) . "...";
                } elseif ($position < 200) {
                    $highlight = Str::substr($highlight, 120, 200) . "...";
                } else {
                    $highlight = Str::substr($highlight, 180, -1) . "...";
                }
            }
        }
        return $highlight;
    }


    /**
     * @param string $type
     * @return string[]
     */
    public static function getHighlightMarker(string $type): array
    {
        $htmlStart = array("*", "<b>", "<i>", "<u>", "<s>", "<code>", "<pre>", "<tg-spoiler>");
        $htmlEnd = array("*", "</b>", "</i>", "</u>", "</s>", "</code>", "</pre>", "</tg-spoiler>");
        $index = rand(0, 6);
        $start = $type == "bale" ? $htmlStart[0] : $htmlStart[1];
        $end = $type == "bale" ? $htmlEnd[0] : $htmlEnd[1];
        return array($start, $end);
    }


    /**
     * @param string $searchPhrase
     * @return array
     */
    public static function getPageNumberFromPhrase(string $searchPhrase): array
    {
        $page = "page";
        $pageNumberPosition = strpos($searchPhrase, $page);

        $offset = strlen($page);

        if ($pageNumberPosition > 1) {
            if (strlen($searchPhrase) > $pageNumberPosition + $offset) {
                $pageNumber = substr($searchPhrase, $pageNumberPosition + $offset, strlen($searchPhrase));
                $searchPhrase = substr($searchPhrase, 0, $pageNumberPosition);
                return [$searchPhrase, $pageNumber];
            } else {
                $searchPhrase = substr($searchPhrase, 0, $pageNumberPosition);
                return [$searchPhrase, 1];
            }
        }
        return [$searchPhrase, 1];
    }

    /**
     * @param Telegram $bot
     * @return string
     */
    public static function getWordId($bot): string
    {
        $wordId = substr($bot->Text(), 1, 1);
        if ((integer)(substr($bot->Text(), 1, 2)) > 0) {
            $wordId = substr($bot->Text(), 1, 2);
        }
        if ((integer)(substr($bot->Text(), 1, 3)) > 0) {
            $wordId = substr($bot->Text(), 1, 3);
        }
        if ((integer)(substr($bot->Text(), 1, 4)) > 0) {
            $wordId = substr($bot->Text(), 1, 4);
        }
        if ((integer)(substr($bot->Text(), 1, 5)) > 0) {
            $wordId = substr($bot->Text(), 1, 5);
        }
        return $wordId;
    }

    /**
     * @param int $aya
     * @param mixed $suraName
     * @param int $sure
     * @param string $message
     * @return string
     */
    public static
    function addAyeIdAndBesmella(int $aya, mixed $suraName, int $sure, string $message): string
    {
        if ($aya == 1) {
            $message = $suraName . (($sure == 1 || $sure == 9) ? "
" : "
بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
") . $message . " : (" . $sure . " : " . $aya . " ) ";
        }
//        else {
//            $message .= "(" . $aya . ")";
//        }
        return $message;
    }

    public static function getCommandScan(int $pageNumber): string
    {
        $threeDigitNumber = StringHelper::get3digitNumber($pageNumber);
        if ($pageNumber == 0) {
            return "/scan604hr1";
        }
        return $pageNumber < 604 ? "/scan" . ($threeDigitNumber) . "hr1" : "/scan001hr1";
    }

    /**
     * Build PlaceQuran image URL based on language preferences
     */
    public static function buildPlaceQuranImageUrl(int $sura, int $aya, ?string $languageCode): string
    {
        $normalized = $languageCode ? self::normalizeLanguageCodeForDatabase($languageCode) : 'fa';
        $langs = in_array($normalized, self::PLACEQURAN_SUPPORTED_LANGUAGES, true) ? 'ar,en' : 'ar';
        return "https://placequran.com/s/" . $sura . "/" . $aya . "/" . $langs;
    }

    /**
     * Send PlaceQuran image to user based on settings and platform
     */
    public static function sendPlaceQuranImage($messenger, int $sura, int $aya, ?string $languageCode = null): void
    {
        try {
            $chat_id = $messenger->ChatID();
            $photoUrl = self::buildPlaceQuranImageUrl($sura, $aya, $languageCode);
            $title = "#placequran_" . $sura . "_" . $aya;
            $caption = "";

            if ($messenger->BotType() != 'gap') {
                BotHelper::sendPhoto($chat_id, $photoUrl, $title, $messenger, $caption);
            } else {
                BotHelper::sendPhotoGap($chat_id, $photoUrl, $messenger, $caption);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send PlaceQuran image', [
                'error' => $e->getMessage(),
                'sura' => $sura,
                'aya' => $aya
            ]);
        }
    }


    /**
     * @param int $pageNumber
     * @param mixed $token
     * @param Telegram $bot
     * @return void
     * @throws GuzzleException
     */
    public static function sendScanBaleButtons(int $pageNumber, mixed $token, Telegram $bot, string $type = 'bale'): void
    {
        $nextCommand = QuranHelper::getCommandScan($pageNumber + 1);
        $backCommand = QuranHelper::getCommandScan($pageNumber - 1);
        $message = trans("bot.for next or previous quran page click on these buttons") . " : ";

        $buttons = [
            ['text' => trans('bot.next'), 'callback_data' => $nextCommand],
            ['text' => trans('bot.previous'), 'callback_data' => $backCommand],
        ];
        foreach (self::getCommonActionButtons($type) as $commonButton) {
            $buttons[] = ['text' => $commonButton[0], 'callback_data' => $commonButton[1]];
        }

        BotHelper::sendButtonGridMessage($bot, $message, $buttons, $type, $token, 2);
    }


    public static function isContainSureAyahCommand($message): bool
    {
        return StringHelper::isContainRegex($message);
    }


    public static function getCommandByRegex(string $message): array
    {
        [$sure, $aya] = StringHelper::getSureAyeByRegex($message);

        $command = StringHelper::command_template_sure . $sure . StringHelper::command_template_ayah . $aya;

        $message = $sure . ":" . $aya;
        if (!env("APP_ENV") == 'testing')
            $message = trans("bot.surah number:") . $sure . ":" . trans("bot.ayah") . " : " . $aya;

        return [$command, $message];
    }

    /**
     * @param $mp3Reciter
     * @param $aye
     * @return string
     */
    public static function getAudioUrl($mp3Reciter, $aye): string
    {
        $base_url = self::getAudioBaseUrl($mp3Reciter);
        $fileName = self::getAudioFileName($mp3Reciter, $aye);

        $audio = $base_url . $fileName . ".mp3";

        return $audio;
    }

    public static function getAudioFileName(mixed $mp3Reciter, $aye)
    {
        $key = self::resolveReciter($mp3Reciter);
        $pattern = (string) (self::getReciterRegistry()[$key]['file'] ?? '{ayah_id}');
        return str_replace(
            ['{sura_3}', '{aya_3}', '{ayah_id}'],
            [
                StringHelper::get3digitNumber((int) $aye->sura),
                StringHelper::get3digitNumber((int) $aye->aya),
                (string) $aye->id,
            ],
            $pattern
        );
    }

    /**
     * @param int $pageNumber
     * @return string
     */
    public static function getCaptionTelegram(int $pageNumber, int $hr, $botType): string
    {
        $commandNext = self::getCommandScan($pageNumber + 1);
        $textNext = trans("bot.next quran page click here") . " : ";
        $commandPrevious = self::getCommandScan($pageNumber - 1);
        $textPrevious = trans("bot.previous quran page click here") . " : ";

        $fullUrl = self::getScanFullUrl($pageNumber, $hr, $botType);
//        BotHelper::sendMessageToSuperAdmin($fullUrl, 'bale');
        $caption = $textNext . $commandNext . " " . $textPrevious . $commandPrevious . "
<a href='" . $fullUrl . "'>hr1</a>" . "
<a href='" . self::getScanFullUrl($pageNumber, 2, $botType) . "'>hr2</a>" . "
<a href='" . self::getScanFullUrl($pageNumber, 3, $botType) . "'>hr3</a>" . "
<a href='" . self::getScanFullUrl($pageNumber, 4, $botType) . "'>hr4</a>";
        return $caption;
    }

    /**
     * @param $messenger
     * @param int $pageNumber
     * @param int $hr
     * @param string $photoUrl
     * @return mixed
     */
    public static function createTitleCaptionSendScan($messenger, int $pageNumber, int $hr, string $photoUrl): mixed
    {
        $chat_id = $messenger->ChatID();
        $title = "#" . trans("bot.page") . "_" . $pageNumber;

        $caption = $title;
        if ($messenger->BotType() != 'bale') {
            $caption = self::getCaptionTelegram($pageNumber, $hr, $messenger->BotType());
        }
        if ($messenger->BotType() != 'gap') {
            return BotHelper::sendPhoto($chat_id, $photoUrl, $title, $messenger, $caption);
        }
        return BotHelper::sendPhotoGap($chat_id, $photoUrl, $messenger, $caption);
    }

    /**
     * @param Telegram $bot
     * @param $token
     * @return void
     * @throws GuzzleException
     */
    public
    function generateJozKeyBoardThenSendIt(Telegram $bot, $token): void
    {
        for ($i = 0; $i < 30; $i += 2) {
            $inlineKeyboard = BotHelper::makeKeyboard2button(trans("bot.Juz") . ($i + 1), config('juz.' . ($i + 1)), trans("bot.Juz") . ($i + 2), config('juz.' . ($i + 2)));
            BotHelper::messageWithKeyboard($token, $bot->ChatID(), trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2), $inlineKeyboard);
        }
    }

    /**
     * @param Telegram $bot
     * @return void
     */
    public
    function generateJozKeyBoardThenSendItTelegram(Telegram $bot): void
    {
        for ($i = 0; $i < 30; $i += 2) {
            $message = trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2);
            $array = [[trans("bot.Juz") . ($i + 1), config('juz.' . ($i + 1))], [trans("bot.Juz") . ($i + 2), config('juz.' . ($i + 2))]];
            BotHelper::sendTelegram2InlineMessage($bot, $message, $array, true);
        }
    }

    /**
     * @param Telegram $bot
     * @return void
     */
    public
    static
    function generateJozLinksThenSendItTelegram($bot): void
    {
        $message = "";
        for ($i = 1; $i <= 30; $i++) {
            $message .= trans("bot.Juz") . $i . "

" . config('juz.' . $i) . "

";
//            <a href=\"" . config('juz.' . $i) . "\">" . trans("bot.Juz") . $i . "</a>
        }
        BotHelper::sendMessageParseMode($bot, $message);
    }

    /**
     * @param Telegram $bot
     * @param $token
     * @return void
     * @throws GuzzleException
     */
    public
    static
    function generateBaleFehrestThenSendIt(Telegram $bot, $token): void
    {
        $quranSurahs = QuranSurah::select(['id', 'ayah', 'arabic', 'sajda', 'location'])
            ->get();

        for ($i = 0; $i < 114; $i += 6) {
            for ($j = 0; $j < 6; $j++) {
                $array[$j] = [$quranSurahs[$i + $j]->id . ":" . $quranSurahs[$i + $j]->arabic . ":" . $quranSurahs[$i + $j]->ayah, "/sure" . ($i + $j + 1) . "ayah1"];
            }

            $inlineKeyboard = BotHelper::makeKeyboard6button($array);
            BotHelper::messageWithKeyboard($token, $bot->ChatID(), trans("bot.surah number:") . ($i + 1) . " " . trans("bot.to") . " " . ($i + 6), $inlineKeyboard);
        }
    }

    /**
     * @param Telegram $bot
     * @return void
     */
    public
    static
    function generateTelegramFehrestThenSendIt(Telegram $bot): void
    {
        $quranSurahs = QuranSurah::select('id', 'ayah', 'arabic', 'sajda', 'location')
            ->get();

        for ($i = 0; $i < 114; $i += 6) {
            for ($j = 0; $j < 6; $j++) {
                $array[$j] = [$quranSurahs[$i + $j]->id . ":" . $quranSurahs[$i + $j]->arabic . ":" . $quranSurahs[$i + $j]->ayah, "/sure" . ($i + $j + 1) . "ayah1"];
            }
            $message = trans("bot.surah number:") . ($i + 1) . " " . trans("bot.to") . " " . ($i + 6);
            BotHelper::sendTelegram6InlineMessage($bot, $message, $array, true);
        }
    }

    /**
     * @param $bot
     * @return void
     */
    public
    static
    function generateGapFehrestThenSendIt($bot): void
    {
        $quranSurahs = QuranSurah::select('id', 'ayah', 'arabic', 'sajda', 'location')
            ->get();
        $message = "";
        for ($i = 0; $i < 114; $i++) {
            $message .= $quranSurahs[$i]->id . ":" . $quranSurahs[$i]->arabic . ":" . $quranSurahs[$i]->ayah . ":

             /sure" . ($i + 1) . "ayah1

            ";
        }
        BotHelper::sendMessage($bot, $message);
    }

    /**
     * @param int $aya
     * @param int $sure
     * @param Telegram $bot
     * @param BotUsers|null $userSettings
     * @return void
     */
    public
    static function sendAudioMp3Aye(int $aya, int $sure, $bot, BotUsers $userSettings = null): void
    {
        if ($aya == 1 && $sure != 1 && $sure != 9) {
            QuranHelper::sendAudio($bot, 1, 1, $userSettings);
        }
        QuranHelper::sendAudio($bot, $sure, $aya, $userSettings);
    }

    /**
     * @param int $aya
     * @param int $sure
     * @param Telegram $bot
     * @param $postfix
     * @param BotUsers|null $userSettings
     * @return void
     */
    public
    static function sendAudioMp3AyeByLocale(int $aya, int $sure, $bot, $postfix, BotUsers $userSettings = null): void
    {
        if ($aya == 1 && $sure != 1 && $sure != 9) {
            QuranHelper::sendAudioByLocale($bot, 1, 1, $userSettings, $postfix);
        }
        QuranHelper::sendAudioByLocale($bot, $sure, $aya, $userSettings, $postfix);
    }

    public
    static function generateJozLinksThenSendItBale(Telegram $bot): void
    {
        $message = "";
        for ($i = 0; $i < 30; $i += 2) {
            $message .= trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2) . "
[" . trans("bot.Juz") . ($i + 1) . "](send:" . config('juz.' . ($i + 1)) . ") [" . trans("bot.Juz") . ($i + 2) . "](send:" . config('juz.' . ($i + 2)) . ")
";
        }
        BotHelper::sendMessage($bot, $message);
    }

    public
    static function generateArrayCommands(Model|bool|BotUsers $userSettings): array
    {
        $mp3Reciter = $userSettings ? $userSettings->setting('mp3_reciter') : null;
        $mp3Enable = $userSettings ? $userSettings->setting('mp3_enable') : null;

        $resultArray = [];

        if ($mp3Enable == "true") {
            $resultArray[] = [
                "text" => trans("bot.disable reciter"),
                "callback_data" => "/mp3_false"
            ];
        } else {
            $resultArray[] = [
                "text" => trans("bot.enable reciter"),
                "callback_data" => "/mp3_true"
            ];
        }

        $resultArray[] = [
            "text" => trans("bot.change reciter") . " (" . self::getReciterName($mp3Reciter ?: self::getDefaultReciter()) . ")",
            "callback_data" => "settings_select_reciter"
        ];

        return $resultArray;
    }


    /**
     * @return string
     */
    public
    static function getHelpMessage($type): string
    {
        $message = trans("bot.command list is") . "\n";
        $message .= "/start\n";
        $message .= "/joz " . trans('bot.List of 30 Juz') . "\n";
        $message .= "/fehrest " . trans('bot.List of 114 Surahs') . "\n";
        $message .= "/search " . trans('bot.search') . "\n";
        $message .= "/lastactivities " . trans('bot.last activities') . "\n";
        $message .= "/report " . trans('bot.your quran readings analysis report') . "\n";
        $message .= "/mp3_true " . trans('bot.send mp3 for selected reciter') . "\n";
        $message .= "/mp3_false " . trans('bot.disable sending mp3 for every ayah') . "\n";
        $message .= "/mp3reciter_<reciter> " . trans('bot.change reciter') . "\n";
        $message .= "/translation " . trans('bot.view available translations') . "\n";
        $message .= "/settings " . trans('bot.settings menu') . "\n\n";

        $message .= trans("bot.for search please type your phrase after double slash. like this") . "\n";
        $message .= "//الرحمن\n\n";

        $message .= trans("bot.for direct access to sura and ayah") . "\n";
        $message .= "/sure1ayah1\n\n";

        $message .= trans("bot.for example if you want to go sure 2 ayah 3") . "\n";
        $message .= "/sure2ayah3\n";

        return $message;
    }

    /**
     * Get last 3 verse activities for a user (with caching)
     * 
     * @param string $chatId
     * @param int $cacheMinutes Cache duration in minutes (default: 5)
     * @return SupportCollection
     */
    public static function getLastVerseActivities(string $chatId, int $cacheMinutes = 5): SupportCollection
    {
        $cacheKey = 'last_verse_activities_' . $chatId;
        
        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($chatId) {
            try {
                // Get all command logs first, then filter in PHP for better compatibility
                $allCommands = BotLog::whereChatId($chatId)
                    ->whereWebhookEndpointUri('webhook-quran-word')
                    ->where('is_command', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(50) // Get more to filter
                    ->get(['text', 'created_at']);

                // Filter by regex pattern
                $lastActivities = $allCommands->filter(function ($log) {
                    return preg_match('/\/sure[0-9]+ayah[0-9]+/', $log->text);
                })->take(3);

                return $lastActivities;
            } catch (\Exception $e) {
                Log::error('Error getting last verse activities', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage()
                ]);
                return collect();
            }
        });
    }

    /**
     * Clear cache for last verse activities
     * 
     * @param string $chatId
     * @return void
     */
    public static function clearLastVerseActivitiesCache(string $chatId): void
    {
        $cacheKey = 'last_verse_activities_' . $chatId;
        Cache::forget($cacheKey);
    }

    /**
     * Format activity text to readable format
     * 
     * @param string $text
     * @return string
     */
    public static function formatActivity(string $text): string
    {
        try {
            [$sure, $ayah] = StringHelper::getSureAyeByRegex($text);
            
            if ($sure > 0 && $ayah > 0) {
                return trans("bot.surah number:") . $sure . "، " . trans("bot.ayah") . " " . $ayah;
            }
            
            return $text;
        } catch (\Exception $e) {
            Log::error('Error formatting activity', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            return $text;
        }
    }

    /**
     * Get next ayah command
     * 
     * @param int $sure
     * @param int $ayah
     * @return string|null
     */
    public static function getNextAyahCommand(int $sure, int $ayah): ?string
    {
        try {
            [$maxAyah, $arabic] = self::getLastAyeBySurehId($sure);
            
            // Check if surah exists and has valid max ayah
            if (!$maxAyah || $maxAyah == 0) {
                // If surah not found, just increment ayah (fallback)
                $nextAyah = $ayah + 1;
                $nextSure = $sure;
                
                // If we're at surah 114, wrap to first surah
                if ($nextSure > 114) {
                    $nextSure = 1;
                }
                
                return StringHelper::command_template_sure . $nextSure . StringHelper::command_template_ayah . $nextAyah;
            }
            
            $nextAyah = $ayah + 1;
            $nextSure = $sure;
            
            // If current ayah is the last in surah, go to next surah
            if ($ayah >= $maxAyah) {
                $nextSure = $sure + 1;
                $nextAyah = 1;
                
                // If we're at the last surah (114), wrap to first surah
                if ($nextSure > 114) {
                    $nextSure = 1;
                }
            }
            
            return StringHelper::command_template_sure . $nextSure . StringHelper::command_template_ayah . $nextAyah;
        } catch (\Exception $e) {
            Log::error('Error getting next ayah command', [
                'sure' => $sure,
                'ayah' => $ayah,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get formatted message for last activities
     * 
     * @param string $chatId
     * @param string $type
     * @return string
     */
    public static function getLastActivitiesMessage(string $chatId, string $type = 'bale'): string
    {
        $lastActivities = self::getLastVerseActivities($chatId);
        
        if ($lastActivities->count() == 0) {
            return "";
        }
        
        $message = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📚 " . trans("bot.your last activities") . ":\n\n";
        
        $emojiNumbers = ['1️⃣', '2️⃣', '3️⃣'];
        $index = 0;
        foreach ($lastActivities as $activity) {
            $formattedActivity = self::formatActivity($activity->text);
            $commandLink = $type == 'bale' 
                ? "[" . $activity->text . "](send:" . $activity->text . ")" 
                : $activity->text;
            $message .= $emojiNumbers[$index] . " " . $formattedActivity . " (" . $commandLink . ")\n";
            $index++;
        }
        
        // Add last verse and continue section
        $lastActivity = $lastActivities->first();
        [$lastSure, $lastAyah] = StringHelper::getSureAyeByRegex($lastActivity->text);
        
        if ($lastSure > 0 && $lastAyah > 0) {
            $formattedLastActivity = self::formatActivity($lastActivity->text);
            $nextCommand = self::getNextAyahCommand($lastSure, $lastAyah);
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📖 " . trans("bot.the last verse you were reading") . ": " . $formattedLastActivity . "\n";
            
            if ($nextCommand) {
                $continueLink = $type == 'bale' 
                    ? "[" . $nextCommand . "](send:" . $nextCommand . ")" 
                    : $nextCommand;
                $message .= trans("bot.continue") . ": " . $continueLink;
            } else {
                $message .= "✅ " . trans("bot.you have completed the quran");
            }
        }
        
        return $message;
    }

    /**
     * Get a random verse from today's activities of other users
     * 
     * @param string $excludeChatId The chat ID to exclude from results
     * @return string|null Returns a command like "/sure2ayah3" or null if no activities found
     */
    public static function getRandomVerseFromTodayActivities(string $excludeChatId): ?string
    {
        try {
            // Get all command logs from today, excluding the current user
            $todayActivities = BotLog::where('created_at', '>=', \Carbon\Carbon::now()->subDay())
                ->whereWebhookEndpointUri('webhook-quran-word')
                ->where('is_command', true)
                ->where('chat_id', '!=', $excludeChatId)
                ->orderBy('created_at', 'desc')
                ->limit(100) // Get more to filter
                ->get(['text', 'chat_id']);

            // Filter by regex pattern to get only verse commands
            $verseActivities = $todayActivities->filter(function ($log) {
                return preg_match('/\/sure[0-9]+ayah[0-9]+/', $log->text);
            });

            if ($verseActivities->count() > 0) {
                // Get unique verses (to avoid duplicates)
                $uniqueVerses = $verseActivities->pluck('text')->unique();
                
                // Return a random verse
                return $uniqueVerses->random();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting random verse from today activities', [
                'exclude_chat_id' => $excludeChatId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get common action buttons (return to menu, last activities, etc.)
     * 
     * @param string $type Bot type (bale, telegram, gap)
     * @param array $additionalButtons Additional buttons to add (format: [['text', 'command'], ...])
     * @return array Array of buttons ready for keyboard
     */
    public static function getCommonActionButtons(string $type = 'bale', array $additionalButtons = []): array
    {
        $buttons = [];

        // Add return to menu button
        $buttons[] = [trans("bot.return to menu"), "/start"];

        // Add last activities button
        $buttons[] = [trans("bot.last activities"), "/lastactivities"];

        // Add search button
        $buttons[] = [trans("bot.search"), "/search"];

        // Add additional buttons if provided
        foreach ($additionalButtons as $button) {
            if (is_array($button) && count($button) >= 2) {
                $buttons[] = [$button[0], $button[1]];
            }
        }

        return $buttons;
    }

    /**
     * Send message with common action buttons
     * 
     * @param Telegram $bot
     * @param string $message
     * @param string $type Bot type
     * @param string $token Bot token (for bale)
     * @param array $additionalButtons Additional buttons to add
     * @return void
     */
    public static function sendMessageWithCommonButtons($bot, string $message, string $type, string $token = '', array $additionalButtons = []): void
    {
        $buttons = self::getCommonActionButtons($type, $additionalButtons);
        BotHelper::sendButtonGridMessage($bot, $message, $buttons, $type, $token, 2);
    }

    /**
     * ساخت لینک دعوت اختصاصی برای کاربر
     * 
     * @param string $chatId
     * @param string $type (bale یا telegram)
     * @param string|null $token (اختیاری - برای getMe API)
     * @return string|null
     */
    public static function getInvitationLink(string $chatId, string $type, ?string $token = null): ?string
    {
        $botUsername = null;
        
        // روش 1: دریافت از دیتابیس
        if ($type == 'bale') {
            $token = $token ?? env("QURAN_HEFZ_BOT_TOKEN_BALE");
            $bot = Bot::where('bale_bot_token', $token)->first();
            if ($bot && $bot->bale_bot_name) {
                $botUsername = $bot->bale_bot_name;
            }
        } elseif ($type == 'telegram') {
            $token = $token ?? env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
            $bot = Bot::where('telegram_bot_token', $token)->first();
            if ($bot && $bot->telegram_bot_name) {
                $botUsername = $bot->telegram_bot_name;
            }
        }
        
        // روش 2: دریافت از getMe API (اگر در دیتابیس نبود)
        if (!$botUsername && $token) {
            $cacheKey = 'bot_username_' . $type . '_' . substr($token, 0, 10);
            $botUsername = Cache::remember($cacheKey, now()->addHours(24), function () use ($token, $type) {
                try {
                    $telegramBot = new Telegram($token, $type == 'bale' ? 'bale' : null);
                    $getMe = $telegramBot->getMe();
                    if ($getMe && isset($getMe['ok']) && $getMe['ok'] && isset($getMe['result']['username'])) {
                        return $getMe['result']['username'];
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not get bot username from getMe API', [
                        'type' => $type,
                        'error' => $e->getMessage()
                    ]);
                }
                return null;
            });
        }
        
        if (!$botUsername) {
            Log::warning('Bot username not found for invitation link', [
                'chat_id' => $chatId,
                'type' => $type
            ]);
            return null;
        }
        
        // ساخت لینک دعوت
        if ($type == 'bale') {
            return "https://ble.ir/{$botUsername}?start={$chatId}";
        } elseif ($type == 'telegram') {
            return "https://t.me/{$botUsername}?start={$chatId}";
        }
        
        return null;
    }
}



// https://qurano.com/en/1-al-fatiha/
// https://static.qurano.com/dist/audio/001002.mp3

// https://quranwbw.com/1
// https://words.audios.quranwbw.com/1/001_001_001.mp3
// https://words.audios.quranwbw.com/1/001_007_009.mp3

// https://quran.com/1
// https://audio.qurancdn.com/wbw/001_002_004.mp3
// https://quran.com/3:71/tafsirs/en-tafisr-ibn-kathir

// http://audio.recitequran.com/wbw/arabic/wisam_sharieff/

// https://cors-proxy.elfsight.com/
// http://wbwcradio.bw.edu:8000/

// http://verses.quran.com/wbw/

// https://server7.mp3quran.net/download/basit/Almusshaf-Al-Mojawwad/001.mp3
// https://quranwbw.github.io/audio-words-new/001_002_001.mp3
// https://quranwbw.github.io/audio-ayah-english/001_002_001.mp3
// https://quranwbw.github.io/audio-ayah-arabic
// https://github.com/marwan/quranwbw.com/blob/9f916b35f591f854c53ef0c8922fe3fcc18efa91/assets/js/main.js#L25

// http://www.houseofquran.com/qsys/quranteacher1.html
// http://3cba.houseofquran.com/01/1F_1_2.mp3
// http://3cba.houseofquran.com/01/1S_2_3.mp3
// http://3cba.houseofquran.com/01/1S_2_4.mp3

// ar.abdulazizazzahrani
// ar.abdulbariaththubaity
// ar.abdulbarimohammed
// ar.abdulbasitmujawwad
// ar.abdulbasitmurattal
// ar.abdulkareemalhazmi
// ar.abdullahalmatrood
// ar.abdullahawadaljuhani
// ar.abdullahbasfar

// https://bonyana.com/535/%D8%AF%D8%A7%D9%86%D9%84%D9%88%D8%AF-%D9%82%D8%B1%D8%A2%D9%86-%D8%B5%D9%88%D8%AA%DB%8C-%D8%A8%D8%A7-%D8%AA%D8%B1%D8%AC%D9%85%D9%87-%D9%81%D8%A7%D8%B1%D8%B3%DB%8C-%D8%A2%DB%8C%D9%87-%D8%A8%D9%87-%D8%A2/
// http://www.yasinmedia.com/audio/quran/download-quran-audio-translation-makarem-fooladvand
// https://p30download.ir/fa/entry/42534/%D9%82%D8%B1%D8%A7%D9%86-%D8%B5%D9%88%D8%AA%DB%8C-%D8%A8%D9%87-%D9%87%D9%85%D8%B1%D8%A7%D9%87-%D8%AA%D8%B1%D8%AC%D9%85%D9%87-%D9%81%D8%A7%D8%B1%D8%B3%DB%8C-%D8%A2%DB%8C%D9%87-%D8%A8%D9%87-%D8%A2%DB%8C%D9%87

// https://everyayah.com/data/AbdulSamad_64kbps_QuranExplorer.Com/001001.mp3    https://www.versebyversequran.com/
// https://everyayah.com/data/images_png/1_1.png
// https://ia804504.us.archive.org/21/items/588083/003-002.mp3

// https://ia800304.us.archive.org/32/items/quran-by--maher-alm3eaqli---128-kb----604-part-full-quran-604-page--safahat-mp3/Page593.mp3
// https://quran.com/page/604
// https://download.quranicaudio.com/qdc/mishari_al_afasy/murattal/112.mp3
