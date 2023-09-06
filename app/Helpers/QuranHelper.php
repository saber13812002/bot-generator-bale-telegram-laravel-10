<?php

namespace App\Helpers;

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
            $base_url = self::getBaseUrl($userSettings);

            $audio = $base_url . $aye->id . ".mp3";

            $caption = self::getSettingReciter();

            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
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

            $caption = self::getSettingReciter();

            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
                'title' => self::getAyeDescription($aye),
                'caption' => $caption
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
     * @return string
     */
    public static function getSettingReciter(): string
    {
        $caption = "
" . trans("bot.disable enable reciter") . " /mp3_true /mp3_false
";

        $caption .= trans("bot.change reciter") . " /mp3reciter_parhizgar /mp3reciter_alafasy
";
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
            $base_url = "http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/";
        return $base_url;

        //https://github.com/GlobalQuran/docs/blob/a0543eb602bab509c366b02a571a4f480a7214ec/api.yaml#L1613

        // http://cdn.alquran.cloud/media/audio/ayah/fa.hedayatfarfooladvand/
        // http://cdn.alquran.cloud/media/audio/ayah/ar.parhizgar/
        // http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/
        // \/\/audio.globalquran.com\/fa.hedayatfarfooladvand\/mp3\/40kbs\/
        // \/\/audio.globalquran.com\/ar.parhizgar\/mp3\/48kbs\/
        // \/\/audio.globalquran.com\/ur.khan\/mp3\/64kbs\/
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
        }
        return $mp3Reciter;
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
     * @param BotUsers|null $userSettings
     * @return string
     */
    public static function getBaseUrl(?BotUsers $userSettings): string
    {
        $mp3Reciter = self::getSettingsByTags($userSettings, 'mp3_reciter');
//dd($mp3Reciter);
        $base_url = self::getUrl($mp3Reciter);
//        dd($base_url);
        return $base_url;
    }

    public static function sendScanPage(Telegram $messenger, int $pageNumber, int $hr)
    {
        $photoUrl = self::getSScan($pageNumber, $hr, $messenger->BotType());

        $chat_id = $messenger->ChatID();
        $title = "page" . $pageNumber;

        $command = self::getCommandScan($pageNumber);
        $text = trans("bot.next quran page click here") . " : ";
        $caption = $text . $command;

        return BotHelper::sendPhoto($chat_id, $photoUrl, $title, $messenger, $caption);
    }


    public static function getSScan(string $page, int $hr, $botType)
    {
        if ($botType == 'telegram')
            return "https://cdn.jsdelivr.net/gh/tarekeldeeb/madina_images@w1024/w1024_page" . $page . ".png";

        return "https://rayed.com/Quran/img/" . $page . ".jpg";
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
            'caption' => $caption
        ];

        if ($messenger->BotType() != "gap")
            $messenger->sendAudio($content);

    }

    /**
     * @param $userSettings
     * @param $sure
     * @param $aye
     * @return array
     */
    public static function getSureAye($userSettings, $sure, $aye): array
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
" . trans("bot.to disable") . " /transtr_false ";
            }

            if ($enTransliteration == 'true') {
                $message .= "

" . $quranTransliterationEn['quran_transliteration_en'] . "
" . trans("bot.to disable") . " /transen_false ";
            }
        }
//        else {
//            $message .= "
//" . trans("bot.to enable transliteration") . " : /transen_true /transtr_true ";
//        }

        $message .= "
" . ($showText ? trans("bot.help.to send scanned quran page") : "") . "
👇 👇 👇
/scan" . $threeDigitNumber . "hr1";

        $message .= "
" . ($showText ? trans("bot.help.help") : "") . "
👇 👇 👇
/help ";

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
:/" . 1 . "
" .
                trans('bot.ayah after ayah') . "
/sure2ayah2
" .
                trans('bot.List of 114 Surahs') . "
/fehrest
" .
                trans('bot.List of 30 Juz') . "
/joz";
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
        $pageNumberPosition = strpos($searchPhrase, "page=");

        if ($pageNumberPosition > 1) {
            if (strlen($searchPhrase) > $pageNumberPosition + 5) {
                $pageNumber = substr($searchPhrase, $pageNumberPosition + 5, strlen($searchPhrase));
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
") . $message . " :(" . $sure . ":" . $aya . ")";
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
    public static function sendScanBaleButtons(int $pageNumber, mixed $token, Telegram $bot): void
    {
        $nextCommand = QuranHelper::getCommandScan($pageNumber + 1);
        $backCommand = QuranHelper::getCommandScan($pageNumber - 1);
        $message = trans("bot.for next or previous quran page click on these buttons") . " : ";

        $inlineKeyboard = BotHelper::makeKeyboard2button(trans('bot.next'), $nextCommand, trans('bot.previous'), $backCommand);
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
    public static
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
    public static
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
    public static
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
    public static
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
    public static function sendAudioMp3Aye(int $aya, int $sure, $bot, BotUsers $userSettings = null): void
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
    public static function sendAudioMp3AyeByLocale(int $aya, int $sure, $bot, $postfix, BotUsers $userSettings = null): void
    {
        if ($aya == 1 && $sure != 1 && $sure != 9) {
            QuranHelper::sendAudioByLocale($bot, 1, 1, $userSettings, $postfix);
        }
        QuranHelper::sendAudioByLocale($bot, $sure, $aya, $userSettings, $postfix);
    }

    public static function generateJozLinksThenSendItBale(Telegram $bot): void
    {
        $message = "";
        for ($i = 0; $i < 30; $i += 2) {
            $message .= trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2) . "
[" . trans("bot.Juz") . ($i + 1) . "](send:" . config('juz.' . ($i + 1)) . ") [" . trans("bot.Juz") . ($i + 2) . "](send:" . config('juz.' . ($i + 2)) . ")
";
        }
        BotHelper::sendMessage($bot, $message);
    }

    public static function generateArrayCommands(Model|bool|BotUsers $userSettings): array
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
     * @param mixed $type
     * @param $sure
     * @param $ayah
     * @return string
     */
    private static function generateLinkCommandResult(mixed $type, $sure, $ayah): string
    {
        $command = "/sure" . $sure . "ayah" . $ayah;

        if ($type != 'bale')
            return $command;

        return "[" . $command . "](send:" . $command . ")";
    }

    private static function getCommandNextPage(string $searchPhrase, int $nextPage): string
    {
        return "
برای دیدن صفحه بعدی نتایج روی لینک زیر کلیک کنید
//" . $searchPhrase . "page=" . $nextPage;

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
