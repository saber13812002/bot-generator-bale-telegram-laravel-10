<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Http\Requests\StoreQuranWordRequest;
use App\Http\Requests\UpdateQuranWordRequest;
use App\Models\QuranSurah;
use App\Models\QuranWord;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Telegram;

class QuranWordController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     */
    public function index(Request $request)
    {
        $type = $request->input('origin');
        BotHelper::sendMessageToSuperAdmin("یک پیام رسیده از طرف تلگرام" . $type, 'bale');
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("QURAN_HEFZ_BOT_TOKEN_BALE"), 'bale');
            } elseif ($request->input('origin') == 'telegram') {
                $bot = new Telegram(env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM"));
                BotHelper::sendMessageToSuperAdmin("یک پیام رسیده از طرف تلگرام" . ":" . $bot->Text(), 'bale');
            } else {
                return 200;
            }

            $commandTemplateNextSure = '/sure';
            $commandTemplateNextAyah = 'ayah';

            $botText = $bot->Text();

            if ($botText == '/start') {
                list($message, $messageCommands) = QuranHefzBotHelper::getStringCommandsStartBot();

                $array = [["کلمه به کلمه", "/1"], ["آیه به آیه", "/sure2ayah2"], ["قهرست 114 سوره", "/commandFehrest"], ["فهرست 30 جز", "/commandJoz"]];

                if ($type == 'telegram') {
                    BotHelper::sendStartMessage($bot, $message . $messageCommands, $array, true);
                } else {
                    $inlineKeyboard = BotHelper::makeKeyboard4button($array);
                    BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif ((integer)(substr($bot->Text(), 1, 1)) > 0) {
                $wordId = $this->getWordId($bot);

                $message = QuranHefzBotHelper::getQuranWordById($wordId);

                $next = ((integer)$wordId == 88246 ? "88246" : ((integer)$wordId + 1));
                $back = ((integer)$wordId == 1 ? "1" : ((integer)$wordId - 1));

                $messageCommands = QuranHefzBotHelper::getStringCommandsWordByWord($next, $back);

                if ($type == 'telegram')
                    BotHelper::sendMessageAye($bot, $message . $messageCommands);
                else {
                    $inlineKeyboard = BotHelper::makeKeyboard2button("بعدی", "/" . $next, "قبلی", "/" . $back);
                    BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif (str_starts_with($botText, $commandTemplateNextSure)) {
                if (preg_match('/sure(.*?)ayah/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $sure = (integer)$match[1];
                    if ($sure > 0) {
                        $aya = (integer)substr($botText, strpos($botText, $commandTemplateNextAyah) + Str::length($commandTemplateNextAyah));

                        if ($aya > 0) {

                            $message = QuranHefzBotHelper::getSureAye($sure, $aya);

                            [$maxAyah, $suraName] = QuranHefzBotHelper::getLastAyeBySurehId($sure);

                            $message = $this->addAyeIdAndBesmella($aya, $suraName, $sure, $message);

                            $nextAye = $commandTemplateNextSure . ($sure) . $commandTemplateNextAyah . $aya + 1;
                            $lastAye = $commandTemplateNextSure . ($sure) . $commandTemplateNextAyah . $aya - 1;

                            $nextSure = $commandTemplateNextSure . ($sure + 1) . $commandTemplateNextAyah . "1";
                            $lastSure = $commandTemplateNextSure . ($sure - 1) . $commandTemplateNextAyah . "1";

                            $messageCommands = QuranHefzBotHelper::getStringCommandsAyaBaya($aya, $maxAyah, $nextAye, $lastAye, $sure, $nextSure, $lastSure);

                            $array = [["آیه بعدی", $nextAye], ["آیه قبلی", $lastAye], ["سوره بعدی", $nextSure], ["سوره قبلی", $lastSure]];
                            if ($type == 'telegram') {
                                BotHelper::sendStartMessage($bot, $message . $messageCommands, $array, true);
                            } else {
                                $inlineKeyboard = BotHelper::makeKeyboard4button($array);
                                BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                            }
                        }
                    }
                }
            } elseif ((substr($bot->Text(), 1, 7)) == "command") {
                $command = substr($botText, strpos($botText, "/command") + Str::length("/command"));
                if ($command == "Fehrest") {
                    $this->generateFehrestThenSendIt($bot);
                }
                if ($command == "Joz") {
                    $this->generateJozKeyBoardThenSendIt($bot);
                }
            } else {
                $message = "دستور نا مشخص /start";
                BotHelper::sendMessage($bot, $message);
            }

            $message = "/start :" . $botText . ": -< :" . $bot->Text();
            BotHelper::sendStart($bot, $message);
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
     * @param Telegram $bot
     * @return string
     */
    public function getWordId(Telegram $bot): string
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
    public function addAyeIdAndBesmella(int $aya, mixed $suraName, int $sure, string $message): string
    {
        if ($aya == 1) {
            $message = $suraName . ($sure != 1 ? "
بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
" : "
") . $message . "(" . $aya . ")";
        } else {
            $message .= "(" . $aya . ")";
        }
        return $message;
    }

    /**
     * @param Telegram $bot
     * @return void
     * @throws GuzzleException
     */
    public function generateJozKeyBoardThenSendIt(Telegram $bot): void
    {
        for ($i = 0; $i < 30; $i += 2) {
            $inlineKeyboard = BotHelper::makeKeyboard2button("جزء" . ($i + 1), config('juz.' . ($i + 1)), "جزء" . ($i + 2), config('juz.' . ($i + 2)));
            BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), "جزء" . ($i + 1) . " و " . ($i + 2), $inlineKeyboard);
        }
    }

    /**
     * @param Telegram $bot
     * @return void
     * @throws GuzzleException
     */
    public function generateFehrestThenSendIt(Telegram $bot): void
    {
        $quranSurahs = QuranSurah::select('id', 'ayah', 'arabic', 'sajda', 'location')
            ->get();

        for ($i = 0; $i < 112; $i += 4) {
            $array = [[$quranSurahs[$i]->arabic, "/sure" . ($i + 1) . "ayah1"], [$quranSurahs[$i + 1]->arabic, "/sure" . ($i + 2) . "ayah1"], [$quranSurahs[$i + 2]->arabic, "/sure" . ($i + 3) . "ayah1"], [$quranSurahs[$i + 3]->arabic, "/sure" . ($i + 4) . "ayah1"]];
            $inlineKeyboard = BotHelper::makeKeyboard4button($array);
            BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), "سوره شماره " . ($i + 1) . " تا " . ($i + 4), $inlineKeyboard);
        }
        $inlineKeyboard = BotHelper::makeKeyboard2button($quranSurahs[112]->arabic, "/sure113ayah1", $quranSurahs[113]->arabic, "/sure114ayah1");
        BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), "سوره شماره 113 و 114", $inlineKeyboard);
    }

}
