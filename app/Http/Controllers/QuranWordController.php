<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\StoreQuranWordRequest;
use App\Http\Requests\UpdateQuranWordRequest;
use App\Models\QuranSurah;
use App\Models\QuranWord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Telegram;

class QuranWordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->input('origin');
        $message = "";
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_TELEGRAM"), 'telegram');
            }


            $commandTemplateNextSure = '/sure';
            $commandTemplateNextAyah = 'ayah';

            $botText = $bot->Text();

            if ($botText == '/start') {
                $messageCommands = "
بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
دو جور مطالعه قرآن داریم
یکی کلمه به کلمه که از اینجا شروع کنید
بعدی:/" . 1 . "

یکی دیگه:
آیه به آیه که میتونید از اینجا شروع کنید
/sure2ayah2";

                BotHelper::sendMessage($bot, $messageCommands);
            } elseif ((integer)(substr($bot->Text(), 1, 1)) > 0) {
                $wordId = substr($bot->Text(), 1, 1);
                if ((integer)(substr($bot->Text(), 1, 2)) > 0) {
                    $wordId = substr($bot->Text(), 1, 2);
                }
                if ((integer)(substr($bot->Text(), 1, 3)) > 0) {
                    $wordId = substr($bot->Text(), 1, 3);
                }
//                dd($wordId);
                $message = $this->getQuranWordById($wordId);
                $messageCommands = "
===============
بعدی:/" . ((integer)$botText + 1) . "
قبلی:/" . ((integer)$botText - 1);

                BotHelper::sendMessage($bot, $message . $messageCommands);

            } elseif (str_starts_with($botText, $commandTemplateNextSure)) {
                if (preg_match('/sure(.*?)ayah/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $sure = $match[1];
                    if ($sure > 0) {
                        $aya = substr($botText, strpos($botText, $commandTemplateNextAyah) + Str::length($commandTemplateNextAyah));

                        if ($aya > 0) {
                            $message = $this->getSureAye($sure, $aya);

                            $maxAyah = $this->getLastAyeBySurehId($sure);
//                            dd($maxAyah);

                            $messageCommands = "
===============
" . ((((integer)$aya + 1) > $maxAyah) ? "" : "
آیه بعدی
" . $commandTemplateNextSure . ($sure) . $commandTemplateNextAyah . (integer)$aya + 1) . "
" . ((integer)$aya - 1 == 0 ? "" : "
آیه قبلی
" . $commandTemplateNextSure . ($sure) . $commandTemplateNextAyah . (integer)$aya - 1) . "
" . (($sure + 1) == 115 ? "" : "
سوره بعدی
" . $commandTemplateNextSure . ($sure + 1) . $commandTemplateNextAyah . "1
") . (($sure - 1) == 0 ? "" : "
سوره قبلی
" . $commandTemplateNextSure . ($sure - 1) . $commandTemplateNextAyah . "1
");

                            BotHelper::sendMessage($bot, $message . $messageCommands);
                        }
                    }
                }
            } else {
                $message = "دستور نا مشخص /start";
                BotHelper::sendMessage($bot, $message);
            }
        }
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
    function store(StoreQuranWordRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public
    function show(QuranWord $quranWord)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(QuranWord $quranWord)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateQuranWordRequest $request, QuranWord $quranWord)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(QuranWord $quranWord)
    {
        //
    }

    /**
     * @param $sure
     * @param $aye
     * @return string
     */
    public
    function getSureAye($sure, $aye): string
    {
        $quranWords = QuranWord::whereSura($sure)->whereAya($aye)->get();
        $message = "";
        foreach ($quranWords as $quranWord) {
            $message .= " " . $quranWord['text'];
        }
        if (!$message) {
            $message = "این سوره و آیه پیدا نشد";
        }
        return $message;

    }

    private
    function getLastAyeBySurehId(mixed $sure)
    {
        $quranSurahs = QuranSurah::select('ayah')->whereId($sure)->get()->first();
        return $quranSurahs->count() > 0 ? $quranSurahs['ayah'] : 0;
    }

    private
    function getQuranWordById(mixed $botText)
    {
        $quranWords = QuranWord::whereId($botText)->get()->first();
        return $quranWords->count() > 0 ? $quranWords['text'] : 0;
    }
}
