<?php

namespace App\Helpers;

use App\Models\QuranSurah;
use App\Models\QuranWord;

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
        if ($quranWords['char_type']) {
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
دو جور مطالعه قرآن داریم
یکی کلمه به کلمه ";
        if ($type == 'bale') {
            $messageCommands = "که از اینجا شروع کنید
کلمه به کلمه:/" . 1 . "

یکی دیگه:
آیه به آیه که میتونید از اینجا شروع کنید
/sure2ayah2
فهرست 114 سوره
/commandFehrest
فهرست 30 جزء
/commandJoz";
        } else {
            $messageCommands = "
یکی دیگه:
آیه به آیه از دکمه دوم شروع کنید

فهرست 114 سوره دکمه سوم
فهرست 30 جزء دکمه چهارم استفاده کنید
";
        }

        return array($message, $messageCommands);
    }
}
