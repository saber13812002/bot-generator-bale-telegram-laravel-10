<?php

namespace App\Helpers;

use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\QuranAyat;
use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTransliterationEn;
use App\Models\QuranTransliterationTr;
use App\Models\QuranWord;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;
use Telegram;

class QuranHelper
{

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

            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
//                'parse_mode' => "html",
                // TODO:
//            'duration' => NULL,
//            'performer' => NULL,
                'title' => self::getAyeDescription($aye),
                'caption' => $caption,
//            'disable_notification' => FALSE,
//            'reply_to_message_id' => NULL,
//            'reply_markup' => NULL,
//            'parse_mode' => NULL
            ];

//        dd($mp3Enable, $caption, $audio, $mp3Reciter);

            if ($messenger->BotType() != "gap")
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

            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
                'title' => self::getAyeDescription($aye),
                'caption' => $caption
//                'parse_mode' => "html"
            ];

//        dd($mp3Enable, $caption, $audio, $mp3Reciter);

            if ($messenger->BotType() != "gap")
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
        $caption = "
" . trans("bot.disable enable reciter") . ($type == 'bale' ? " /mp3_true [/mp3_true](send:/mp3_true) /mp3_false [/mp3_false](send:/mp3_false)
" : " /mp3_true  /mp3_false
");

        $caption .= trans("bot.change reciter") . ($type == 'bale' ? " /mp3reciter_parhizgar [/mp3reciter_parhizgar](send:/mp3reciter_parhizgar) /mp3reciter_alafasy [/mp3reciter_alafasy](send:/mp3reciter_alafasy)
" : " /mp3reciter_parhizgar  /mp3reciter_alafasy
");
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
     * @param mixed $mp3Reciter
     * @return string
     */
    public static function getUrl(mixed $mp3Reciter): string
    {
        $base_url = "https://cdn.islamic.network/quran/audio/128/ar.alafasy/";
        if ($mp3Reciter == "parhizgar")
            $base_url = "https://tanzil.net/res/audio/parhizgar/";
        return $base_url;

        //https://github.com/GlobalQuran/docs/blob/a0543eb602bab509c366b02a571a4f480a7214ec/api.yaml#L1613

        // http://cdn.alquran.cloud/media/audio/ayah/fa.hedayatfarfooladvand/
        // http://cdn.alquran.cloud/media/audio/ayah/ar.parhizgar/
        //https://tanzil.net/res/audio/parhizgar/
        // http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/
        // \/\/audio.globalquran.com\/fa.hedayatfarfooladvand\/mp3\/40kbs\/
        // \/\/audio.globalquran.com\/ar.parhizgar\/mp3\/48kbs\/
        // \/\/audio.globalquran.com\/ur.khan\/mp3\/64kbs\/

        // juz

        https://www.sibtayn.com/sound/ar/quran/parhizgar/
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
                $mp3Reciter = "parhizgar";
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

        $content = [
            'chat_id' => $chat_id,
            'audio' => $audio,
            'title' => $caption,
            'caption' => $caption,
            'parse_mode' => "html"
        ];

        if ($messenger->BotType() != "gap")
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
    public static function getSureAye($userSettings, $sure, $aye, $type): array
    {
        $quranWords = QuranWord::query()->whereSura($sure)->whereAya($aye)->get();
        $message = "";
        $pageNumber = 0;
        foreach ($quranWords as $quranWord) {
            $message .= " " . $quranWord['text'];
            $pageNumber = $quranWord['page'];
        }

        $threeDigitNumber = StringHelper::get3digitNumber($pageNumber);

        $translationId = 2;

        $userTranslationId = self::getSettingsByTags($userSettings, 'translation_id');

        if ($userTranslationId > 0) {
            $translationId = $userTranslationId;
        }

        $quranTranslate = QuranTranslation::query()->whereTranslationId($translationId)->whereSura($sure)->whereAya($aye)->first();
//            dd($quranTranslate, $sure, $aye);

//        if (App::getLocale() == 'fa') {
//        }

        if (!$quranTranslate) {
            $logMessage = "quranTranslate in null with:translationId:" . $translationId . ")->whereSura(" . $sure . ")->whereAya(" . $aye;
            BotHelper::sendMessageToSuperAdmin($logMessage, 'bale');
            BotHelper::sendMessageToSuperAdmin($logMessage, 'telegram');
            Log::error($logMessage);
        }

        $showText = false;
        $randomNumber = rand(1, 11);
        if ($randomNumber % 5 == 1)
            $showText = true;

        $message .= "

" . $quranTranslate['text'] . " : (" . $sure . ":" . $aye . ")";

        $index = $quranTranslate['index'];

        $trTransliteration = self::getSettingsByTags($userSettings, 'quran_transliteration_tr');
        $enTransliteration = self::getSettingsByTags($userSettings, 'quran_transliteration_en');

        if ($trTransliteration == 'true' || $enTransliteration == 'true') {

            $quranTransliterationTr = QuranTransliterationTr::query()->whereIndex($index)->first();

            $quranTransliterationEn = QuranTransliterationEn::query()->whereIndex($index)->first();
            if ($trTransliteration == 'true') {
                $message .= "

" . $quranTransliterationTr['quran_transliteration_tr'] . "
" . trans("bot.to disable") . ($type == 'bale' ? " /transtr_false [/transtr_false](send:/transtr_false)" : " /transtr_false ");
            }

            if ($enTransliteration == 'true') {
                $message .= "

" . $quranTransliterationEn['quran_transliteration_en'] . "
" . trans("bot.to disable") . ($type == 'bale' ? " /transen_false [/transen_false](send:/transen_false)" : " /transen_false ");
            }
        }
//        else {
//            $message .= "
//" . trans("bot.to enable transliteration") . " : /transen_true /transtr_true ";
//        }

        $message .= "
" . ($showText ? trans("bot.help.to send scanned quran page") : "") . "
👇 👇 👇
/scan" . $threeDigitNumber . "hr1 " . ($type == 'bale' ? "[/scan" . $threeDigitNumber . "hr1](send:/scan" . $threeDigitNumber . "hr1)" : "");

        $message .= "
" . ($showText ? trans("bot.help.help") : "") . "
👇 👇 👇
/help " . ($type == 'bale' ? "[/help](send:/help) " : "");

        if (!$message) {
            $message = "این سوره و آیه پیدا نشد";
        }
        return [$message, $pageNumber];
    }

    public static function getLastAyeBySurehId(mixed $sure): array
    {
        $quranSurahs = QuranSurah::select('ayah', 'arabic')->whereId($sure)->get()->first();
        return [$quranSurahs->count() > 0 ? $quranSurahs['ayah'] : 0, $quranSurahs['arabic']];
    }

    public static function getQuranWordById(mixed $botText): array
    {
        $idEndAya = 0;
        $quranWords = QuranWord::query()->whereId($botText)->get()->first();
        $word = $quranWords->count() > 0 ? $quranWords['text'] ?: '(' . $quranWords['aya'] . ')' : 0;
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
            trans('bot.this bot support 2 methods') . "
" .
            trans('bot.one of them') . "
" .
            trans('bot.word by word');
        if ($type == 'bale') {
            $messageCommands = "
" .
                trans('bot.this bot support 2 methods') . "
" .
                trans('bot.word by word') . "
:/" . 1 . " [/1](send:/1)
" .
                trans('bot.ayah after ayah') . "
/sure2ayah2 [/sure2ayah2](send:/sure2ayah2)
" .
                trans('bot.List of 114 Surahs') . "
/fehrest [/fehrest](send:/fehrest)
" .
                trans('bot.List of 30 Juz') . "
/joz [/joz](send:/joz)
" . trans("bot.help.help") . "
/help [/help](send:/help)
";
        } else {
            $messageCommands = "
" .
                trans('bot.another method is') . "
" .
                trans('bot.ayah after ayah') . "
" .
                trans('bot.start from second button') . "

" .
                trans('bot.List of 114 Surahs') . "
" .
                trans('bot.third button') . "
" .
                trans('bot.List of 30 Juz') . "
" .
                trans('bot.forth button') . "
";
        }

        return array($message, $messageCommands);
    }


    /**
     * @param int $resultsCount
     * @param int $pageNumber
     * @param $searchPhrase
     * @return string
     */
    public static function getResultCountText(int $resultsCount, int $pageNumber, $searchPhrase): string
    {
        $commandNextPage = self::getCommandNextPage($searchPhrase, $pageNumber + 1);

        if ($resultsCount == 0) {
            $resultText = "هیچ موردی به عنوان نتیجه جستجوی شما یافت نشد.";
        } else {
            $messageWhenCountMoreThanLimit = "بیش از " . config('laravel-fulltext.limit-results-page') . " مورد نتیجه یافت شد که در نسخه جاری " . config('laravel-fulltext.limit-results') . " تای اول ارسال میشه و به زودی در نسخه های بعدی میتوانید صفحات بعدی را هم جستجو کنید ";
            $messageWhenCountLessThanLimit = " تعداد  " . $resultsCount . " مورد یافت شد .";

            $resultText = $resultsCount >= config('laravel-fulltext.limit-results-page') ? $messageWhenCountMoreThanLimit . $commandNextPage : $messageWhenCountLessThanLimit;
        }
        return $resultText;
    }

    /**
     * @param mixed $searchPhrase
     * @param int $pageNumber
     * @param mixed $type
     * @param $bot
     * @return void
     */
    #[NoReturn] public static function findResultThenSend(mixed $searchPhrase, int $pageNumber, mixed $type, $bot): void
    {
        $searchPhrase = IndexedRecord::normalize($searchPhrase);
        $results = self::getResultSearch($searchPhrase, $pageNumber);
//        dd($pageNumber,$results);
        $message = "";

        $resultText = self::getResultCountText($results->count(), $pageNumber, $searchPhrase) . "
https://quran.inoor.ir/fa/search/?query=" . $searchPhrase . "
";
//        dd($results);
        $index = ($pageNumber - 1) * config("laravel-fulltext.limit-results-page");
        foreach ($results as $item) {
//            dd($item);
            list($start, $end) = self::getHighlightMarker($type);
            $highlight = self::highlighter($searchPhrase, $item->indexed_title, $start, $end);
//            dd($highlight, $start, $end);
            $message = self::getResultMessage(++$index, $item->indexable, $highlight, $type, $resultText, $message);
//            if ($index == 14)
//                dd($message);
            //            self::sendMessageForEveryResult($item, $type, $bot, $message, $token);
        }

        if ($type == "bale") {
            BotHelper::sendMessage($bot, $message . "
" . $resultText);
        } else {
            BotHelper::sendMessageParseMode($bot, $message . "
" . $resultText);
        }

//        BotHelper::sendMessage($bot, $resultText);

        self::sendReportMessageToSuperAdmins($searchPhrase, $resultText, $bot);
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
     * @param int $i
     * @param mixed $item
     * @param array|string|null $highlight
     * @param mixed $type
     * @return string
     */
    public static function getResultItemMessage(int $i, mixed $item, array|string|null $highlight, mixed $type): string
    {
        return ($i . "- سوره شماره :" . $item->suras->id . "
" . $item->suras->arabic . "- آیه شماره " . $item->aya . "

--------------------

" . $highlight . "

--------------------

" . self::generateLinkCommandResult($type, $item->sura, $item->aya) . "
دیدن نتیجه ☝☝☝

");
    }

    /**
     * @param int $count
     * @param mixed $item
     * @param array|string|null $highlight
     * @param mixed $type
     * @param string $resultText
     * @param string $message
     * @return string
     */
    public static function getResultMessage(int $count, mixed $item, array|string|null $highlight, mixed $type, string $resultText, string $message): string
    {
        $messageResult = self::getResultItemMessage($count, $item, $highlight, $type);
        if ($count == 1) {
            $message .= $resultText . "
" . $messageResult;
        } else {
            $message .= $messageResult;
        }
        return $message;
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

        // Add common buttons (return to menu, last activities)
        $commonButtons = self::getCommonActionButtons($type);
        
        // Create keyboard with next/previous and common buttons
        $option = [
            array($bot->buildInlineKeyBoardButton(trans('bot.next'), callback_data: $nextCommand)),
            array($bot->buildInlineKeyBoardButton(trans('bot.previous'), callback_data: $backCommand))
        ];
        
        // Add common buttons
        foreach ($commonButtons as $button) {
            $option[] = array($bot->buildInlineKeyBoardButton($button[0], callback_data: $button[1]));
        }
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
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
        $fileName = $aye->id;
        if ($mp3Reciter == "parhizgar") {
            $fileName = StringHelper::get3digitNumber($aye->sura) . StringHelper::get3digitNumber($aye->aya);
        }
        return $fileName;
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
        if (!$userSettings) {
            return [
                [
                    "text" => trans("bot.disable enable reciter"),
                    "callback_data" => "/mp3"
                ],
                [
                    "text" => trans("bot.change reciter"),
                    "callback_data" => "/mp3reciter"
                ]
            ];
        } else {
            $mp3Reciter = $userSettings->setting('mp3_reciter');
            $mp3Enable = $userSettings->setting('mp3_enable');

            $mp3EnableArray = [
                "text" => trans("bot.enable reciter"),
                "callback_data" => "/mp3_true"
            ];

            $mp3DisableArray = [
                "text" => trans("bot.disable reciter"),
                "callback_data" => "/mp3_false"
            ];


            $mp3ReciterParhizgarArray = [
                "text" => trans("bot.reciter :reciter", ['reciter' => trans('bot.parhizgar')]),
                "callback_data" => "/mp3reciter_parhizgar"
            ];

            $mp3ReciterAlafasyArray = [
                "text" => trans("bot.reciter :reciter", ['reciter' => trans('bot.alafasy')]),
                "callback_data" => "/mp3reciter_alafasy"
            ];

            $resultArray = [];

            if ($mp3Enable == "true") {
                $resultArray[] = $mp3DisableArray;
            } else {
                $resultArray[] = $mp3EnableArray;
            }

            if ($mp3Reciter == "parhizgar") {
                $resultArray[] = $mp3ReciterAlafasyArray;
            } else {
                $resultArray[] = $mp3ReciterParhizgarArray;
            }
            return $resultArray;
        }
    }


    /**
     * @return string
     */
    public
    static function getHelpMessage($type): string
    {
        if ($type == "bale")
            return trans("bot.command list is") . "
: /start [/start](send:/start)
: /joz [" . trans('bot.help.list of Quran 30 parts') . "](send:/joz)
: /fehrest [" . trans('bot.help.list of Surahs of the Quran') . "](send:/fehrest)
: /lastactivities [" . trans('bot.last activities') . "](send:/lastactivities)
: /report [" . trans('bot.help.your quran readings analysis report') . "](send:/report)
: /mp3_true [" . trans('bot.help.send mp3 for selected reciter') . "](send:/mp3_true)
: /mp3_false [" . trans('bot.help.disable sending mp3 for every ayah') . "](send:/mp3_false)
: /mp3Reciter_parhizgar [" . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.parhizgar')]) . "](send:/mp3Reciter_parhizgar)
: /mp3Reciter_alafasy [" . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.alafasy')]) . "](send:/mp3Reciter_alafasy)
: /listcommands [" . trans('bot.help.list of this robot commands') . "](send:/listcommands)
: /transen_true :  [" . trans('bot.help.choose :language as transliteration', ['language' => trans('bot.transliterations.english')]) . "](send:/transen_true)
: /transen_false [" . trans('bot.help.dont show :language transliteration', ['language' => trans('bot.transliterations.english')]) . "](send:/transen_false)
: /transtr_true :  [" . trans('bot.help.choose :language as transliteration', ['language' => trans('bot.transliterations.turkish')]) . "](send:/transtr_true)
: /transtr_false [" . trans('bot.help.dont show :language transliteration', ['language' => trans('bot.transliterations.turkish')]) . "](send:/transtr_false)
: /trans_2 :  [" . trans('bot.help.choose :translator as translation', ['translator' => trans('bot.translators.ansarian')]) . "](send:/trans_2)
: /trans_3 :  [" . trans('bot.help.choose :translator as translation', ['translator' => trans('bot.translators.ayati')]) . "](send:/trans_3)

[" . trans("bot.for search please type your phrase after double slash. like this") . "](send://الرحمن)
//الرحمن

[" . trans("bot.for direct access to sura and ayah") . "](send:/sure1ayah1)
/sure1ayah1

[" . trans("bot.for example if you want to go sure 2 ayah 3") . "](send:/sure2ayah3)
/sure2ayah3

";
        return trans("bot.command list is") . "
: /start
: /joz " . trans('bot.help.list of Quran 30 parts') . "
: /fehrest " . trans('bot.help.list of Surahs of the Quran') . "
: /lastactivities " . trans('bot.last activities') . "
: /report " . trans('bot.help.your quran readings analysis report') . "
: /mp3_true " . trans('bot.help.send mp3 for selected reciter') . "
: /mp3_false " . trans('bot.help.disable sending mp3 for every ayah') . "
: /mp3Reciter_parhizgar " . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.parhizgar')]) . "
: /mp3Reciter_alafasy " . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.alafasy')]) . "
: /listcommands " . trans('bot.help.list of this robot commands') . "](send:/listcommands)
: /transen_true :  " . trans('bot.help.choose :language as transliteration', ['language' => trans('bot.transliterations.english')]) . "
: /transen_false " . trans('bot.help.dont show :language transliteration', ['language' => trans('bot.transliterations.english')]) . "
: /transtr_true :  " . trans('bot.help.choose :language as transliteration', ['language' => trans('bot.transliterations.turkish')]) . "
: /transtr_false " . trans('bot.help.dont show :language transliteration', ['language' => trans('bot.transliterations.turkish')]) . "
: /trans_2 :  " . trans('bot.help.choose :translator as translation', ['translator' => trans('bot.translators.ansarian')]) . "
: /trans_3 :  " . trans('bot.help.choose :translator as translation', ['translator' => trans('bot.translators.ayati')]) . "

" . trans("bot.for search please type your phrase after double slash. like this") . "
//الرحمن

" . trans("bot.for direct access to sura and ayah") . "
/sure1ayah1

" . trans("bot.for example if you want to go sure 2 ayah 3") . "
/sure2ayah3

";
    }

    /**
     * @param mixed $type
     * @param $sure
     * @param $ayah
     * @return string
     */
    private
    static function generateLinkCommandResult(mixed $type, $sure, $ayah): string
    {
        $command = "/sure" . $sure . "ayah" . $ayah;

        if ($type != 'bale')
            return $command;

        return "[" . $command . "](send:" . $command . ")";
    }

    private
    static function getCommandNextPage(string $searchPhrase, int $nextPage): string
    {
        return "
" . trans("bot.to sending request for next result page please click here") . "
[//" . $searchPhrase . "page" . $nextPage . "](send://" . $searchPhrase . "page" . $nextPage . ")";

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
        
        if ($type == 'telegram') {
            // For telegram, convert to inline keyboard format
            $array = [];
            foreach ($buttons as $button) {
                $array[] = [$button[0], $button[1]];
            }
            BotHelper::sendTelegram4InlineMessage($bot, $message, $array, true);
        } else if ($type == 'gap') {
            // For gap, convert to gap keyboard format
            $array = [];
            foreach ($buttons as $button) {
                $array[] = [$button[0], $button[1]];
            }
            BotHelper::sendGap4InlineMessage($bot, $message, $array);
        } else {
            // For bale
            $option = [];
            foreach ($buttons as $button) {
                $option[] = array($bot->buildInlineKeyBoardButton($button[0], callback_data: $button[1]));
            }
            $inlineKeyboard = $bot->buildInlineKeyBoard($option);
            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
        }
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
