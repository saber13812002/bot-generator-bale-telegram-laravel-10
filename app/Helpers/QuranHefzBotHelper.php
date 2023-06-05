<?php

namespace App\Helpers;

use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTransliterationEn;
use App\Models\QuranTransliterationTr;
use App\Models\QuranWord;
use Illuminate\Support\Facades\App;

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
            $message .= " :" . $quranTranslate['text'];
        }

        $index = $quranTranslate['index'];
        $quranTransliterationTr = QuranTransliterationTr::query()->whereIndex($index)->first();

        $quranTransliterationEn = QuranTransliterationEn::query()->whereIndex($index)->first();

//        if (App::getLocale() == 'fa') {
        $message .= "
:(" . $sure . ":" . $aye . ")" . $quranTransliterationTr['quran_transliteration_tr'];
//        }
//        if (App::getLocale() == 'fa') {
        $message .= "
:(" . $sure . ":" . $aye . ")" . $quranTransliterationEn['quran_transliteration_en'];
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
}
