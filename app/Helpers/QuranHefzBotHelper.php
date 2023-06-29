<?php

namespace App\Helpers;

use App\Models\QuranAyat;
use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTransliterationEn;
use App\Models\QuranTransliterationTr;
use App\Models\QuranWord;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;
use Telegram;

class QuranHefzBotHelper
{

    /**
     * @param $sure
     * @param $aye
     * @return string
     */
    public static function getSureAye($sure, $aye): string
    {
        $quranWords = QuranWord::query()->whereSura($sure)->whereAya($aye)->get();
        $message = "";
        foreach ($quranWords as $quranWord) {
            $message .= " " . $quranWord['text'];
        }
        $quranTranslate = QuranTranslation::query()->whereTranslationId(2)->whereSura($sure)->whereAya($aye)->first();
//            dd($quranTranslate, $sure, $aye);

        if (App::getLocale() == 'fa') {
            $message .= "
:" . $quranTranslate['text'] . " : (" . $sure . ":" . $aye . ")";
        }

        $index = $quranTranslate['index'];
        $quranTransliterationTr = QuranTransliterationTr::query()->whereIndex($index)->first();

        $quranTransliterationEn = QuranTransliterationEn::query()->whereIndex($index)->first();

//        if (App::getLocale() == 'fa') {
        $message .= "
:" . $quranTransliterationTr['quran_transliteration_tr'];
//        }
//        if (App::getLocale() == 'fa') {
        $message .= "
:" . $quranTransliterationEn['quran_transliteration_en'];
//        }

        if (!$message) {
            $message = "این سوره و آیه پیدا نشد";
        }
        return $message;

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
/commandFehrest
" .
                trans('bot.List of 30 Juz') . "
/commandJoz";
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
     * @return string
     */
    public static function getResultCountText(int $resultsCount): string
    {
        if ($resultsCount == 0) {
            $resultText = "هیچ موردی به عنوان نتیجه جستجوی شما یافت نشد.";
        } else {
            $resultText = $resultsCount >= 15 ? "بیش از 15 مورد نتیجه یافت شد که در نسخه جاری 15 تای اول ارسال میشه و به زودی در نسخه های بعدی میتوانید صفحات بعدی را هم جستجو کنید " : " تعداد  " . $resultsCount . " مورد یافت شد .";
        }
        return $resultText;
    }

    /**
     * @param mixed $botText
     * @param mixed $type
     * @param Telegram $bot
     * @param mixed $token
     * @return void
     * @throws GuzzleException
     */
    public static function findResultThenSend(mixed $botText, mixed $type, Telegram $bot, mixed $token): void
    {

        $botText = IndexedRecord::normalize($botText);
        $results = self::getResultSearch($botText);

        $message = "";

        $resultText = self::getResultCountText($results->count()) . "
https://quran.inoor.ir/fa/search/?query=" . $botText . "
";
//        dd($resultText);
        $index = 0;
        foreach ($results as $item) {

            list($start, $end) = self::getHighlightMarker($type);
            $highlight = self::highlighter($botText, $item->indexed_title, $start, $end);

            $message = self::getResultMessage(++$index, $item->indexable, $highlight, $type, $resultText, $message);

            //            self::sendMessageForEveryResult($item, $type, $bot, $message, $token);
        }

        BotHelper::sendMessageParseMode($bot, $message . "
" . $resultText);

//        BotHelper::sendMessage($bot, $resultText);

        self::sendReportMessageToSuperAdmins($botText, $resultText, $bot);
    }

    /**
     * @param array|string $botText
     * @param string $resultText
     * @param Telegram $bot
     * @return void
     */
    public static function sendReportMessageToSuperAdmins(array|string $botText, string $resultText, Telegram $bot): void
    {
        $msg = "جستجوی #قرآن: " . $botText . "
" . $resultText . "
https://quran.inoor.ir/fa/search/?query=" . $botText . "
" . $bot->ChatID() . "
" . $bot->Username() . "
" . $bot->FirstName() . "
" . $bot->LastName();
        BotHelper::sendMessageToSuperAdmin($msg, 'bale');
        BotHelper::sendMessageToSuperAdmin($msg, 'telegram');
    }

    /**
     * @param mixed $botText
     * @return Collection|IndexedRecord[]
     */
    public static function getResultSearch(mixed $botText): Collection|array
    {
//        $results0 = QuranAyat::query()->where('simple', 'like', '%' . $botText . '%')->paginate();

//            $paginate = QuranAyatResource::collection($results);
//            dd($results->count());
//            dd($results->items());
        $search = new Search();
        return $search->run($botText, QuranAyat::class);
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
        $array = [["سوره شماره " . $item->suras->id . "-" . $item->suras->arabic, "/sure" . $item->sura . "ayah" . $item->aya]];
//                dd($array,$token,$message,$array);
        if ($type == 'telegram') {
            BotHelper::sendQuranSearchResult($bot, $message, $array);
        } else {
            $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
            BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
//                    BotHelper::sendMessage($bot,$message);
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

    /**
     * @param string $keyword
     * @param mixed $longText
     * @param $start
     * @param $end
     * @return string
     */
    public static function highlighter(string $keyword, string $longText, $start, $end): string
    {
        return preg_replace("/\w*?$keyword\w*/i", $start . "$0" . $end, $longText);
    }

    /**
     * @param string $type
     * @return string[]
     */
    public static function getHighlightMarker(string $type): array
    {
        $start = $type == "bale" ? " <u>" : " <i>";
        $end = $type == "bale" ? "</u> " : "</i> ";
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
        return ($i . "-
 سوره شماره :" . $item->suras->id . "
" . $item->suras->arabic . "- آیه شماره " . $item->aya . "
--------------------
" . $highlight . "
--------------------
" . self::generateLinkCommandResult($type, $item->sura, $item->aya) . "
دیدن نتیجه ☝☝☝

");
    }

    /**
     * @param int $i
     * @param mixed $item
     * @param array|string|null $highlight
     * @param mixed $type
     * @param int $count
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

}
