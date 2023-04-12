<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Http\Requests\QuranBotRequest;
use App\Http\Requests\StoreQuranWordRequest;
use App\Http\Requests\UpdateQuranWordRequest;
use App\Models\QuranSurah;
use App\Models\QuranWord;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Telegram;

class QuranWordController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     */
    public function index(QuranBotRequest $request)
    {
        if ($request->has('language')) {
            App::setLocale($request->input('language'));
        } else {
            App::setLocale("fa");
        }

        $isStartCommandShow = 1;
        $type = $request->input('origin');
//        BotHelper::sendMessageToSuperAdmin("یک پیام رسیده از طرف تلگرام" . $type, 'bale');
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("QURAN_HEFZ_BOT_TOKEN_BALE"), 'bale');
            } elseif ($request->input('origin') == 'telegram') {
                $bot = new Telegram(env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM"));
//                BotHelper::sendMessageToSuperAdmin("یک پیام رسیده از طرف تلگرام" . ":" . $bot->Text(), 'bale');
            } else {
                return 200;
            }

            $commandTemplateSure = '/sure';
            $commandTemplateAyah = 'ayah';

            $botText = $bot->Text();

            if ($botText == '/start') {
                $isStartCommandShow = 0;
                list($message, $messageCommands) = QuranHefzBotHelper::getStringCommandsStartBot($type);

                $array = [[trans('bot.word by word'), "/1"], [trans('bot.ayah after ayah'), "/sure2ayah2"], [trans('bot.List of 114 Surahs'), "/commandFehrest"], [trans('bot.List of 30 Juz'), "/commandJoz"]];
                dd($array,$message, $messageCommands);
                if ($type == 'telegram') {
                    BotHelper::sendTelegram4InlineMessage($bot, $message . $messageCommands, $array, true);
                } else {
                    $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array);
                    BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif ((integer)(substr($bot->Text(), 1, 1)) > 0) {
                $wordId = $this->getWordId($bot);
//                BotHelper::sendMessageToSuperAdmin("از طرف تلگرام" . ":" . $bot->Text() . ":" . $wordId, 'bale');
                [$message, $isEndAya] = QuranHefzBotHelper::getQuranWordById($wordId);
//                BotHelper::sendMessageToSuperAdmin("از طرف تلگرام" . ":" . $bot->Text() . ":" . $wordId . ":" . $message, 'bale');
                $next = ((integer)$wordId == 88246 ? "88246" : ((integer)$wordId + 1));
                $back = ((integer)$wordId == 1 ? "1" : ((integer)$wordId - 1));

//                $messageCommands = QuranHefzBotHelper::getStringCommandsWordByWord($next, $back);

                if ($isEndAya != 1) {
                    $isStartCommandShow = 0;
                }

                if ($type == 'telegram')
                    BotHelper::sendMessageAye($bot, $message, "/" . $next, "/" . $back);
                else {
                    $inlineKeyboard = BotHelper::makeKeyboard2button(trans('pagination.next'), "/" . $next, trans('pagination.previous'), "/" . $back);
                    BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif (str_starts_with($botText, $commandTemplateSure)) {
                if (preg_match('/sure(.*?)ayah/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $sure = (integer)$match[1];
                    if ($sure > 0) {
                        $aya = (integer)substr($botText, strpos($botText, $commandTemplateAyah) + Str::length($commandTemplateAyah));

                        if ($aya > 0) {

                            $isStartCommandShow = $aya % 10 == 0 ? 1 : 0;
                            $message = QuranHefzBotHelper::getSureAye($sure, $aya);

                            [$maxAyah, $sureName] = QuranHefzBotHelper::getLastAyeBySurehId($sure);
                            [$maxAyahSureGhabli, $sureGhabliName] = QuranHefzBotHelper::getLastAyeBySurehId($sure != 1 ? $sure - 1 : 114);

                            $message = $this->addAyeIdAndBesmella($aya, $sureName, $sure, $message);

                            $nextSure = $commandTemplateSure . ($sure != 114 ? $sure + 1 : 1) . $commandTemplateAyah . "1";
                            $firstAyaOfLastSure = $commandTemplateSure . ($sure - 1) . $commandTemplateAyah . "1";
                            $lastAyaOfLastSure = $commandTemplateSure . ($sure - 1) . $commandTemplateAyah . $maxAyahSureGhabli;

                            $nextAye = ($aya == $maxAyah) ? $nextSure : $commandTemplateSure . ($sure) . $commandTemplateAyah . $aya + 1;
                            $lastAye = ($aya == 1) ? $lastAyaOfLastSure : $commandTemplateSure . ($sure) . $commandTemplateAyah . $aya - 1;

                            if ($aya == $maxAyah || $aya == 1) {
                                $isStartCommandShow = true;
                            }
//                            $messageCommands = QuranHefzBotHelper::getStringCommandsAyaBaya($aya, $maxAyah, $nextAye, $lastAye, $sure, $nextSure, $lastSure);


                            $array = [[trans('bot.next aya'), $nextAye], [trans('bot.previous aya'), $lastAye], [trans('bot.next surah'), $nextSure], [trans('bot.previous surah'), $firstAyaOfLastSure]];
                            if ($type == 'telegram') {
                                BotHelper::sendTelegram4InlineMessage($bot, $message, $array, true);
                            } else {
                                $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array);
                                BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), $message, $inlineKeyboard);
                            }
                        }
                    }
                }
            } elseif ((substr($bot->Text(), 1, 7)) == "command") {
                $command = substr($botText, strpos($botText, "/command") + Str::length("/command"));
                if ($command == "Fehrest") {
                    if ($type == 'telegram') {
                        $this->generateTelegramFehrestThenSendIt($bot);
                    } else {
                        $this->generateBaleFehrestThenSendIt($bot);
                    }
                }
                if ($command == "Joz") {
                    if ($type == 'telegram') {
                        $this->generateJozKeyBoardThenSendItTelegram($bot);
                    } else {
                        $this->generateJozKeyBoardThenSendIt($bot);
                    }
                }
            } else {
                $message = trans('bots.bot cant recognized your command') . " /start";
                BotHelper::sendMessage($bot, $message);
            }

            if ($type != 'bale' && $isStartCommandShow) {
                $message = trans("bots.return to command list"); // . $botText . ": -< :" . $bot->Text()
                BotHelper::sendStart($bot, $message);
            }
//            return Response::HTTP_ACCEPTED;
        } else {
            return Response::HTTP_ACCEPTED;
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
    public
    function getWordId(Telegram $bot): string
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
    public
    function addAyeIdAndBesmella(int $aya, mixed $suraName, int $sure, string $message): string
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
    public
    function generateJozKeyBoardThenSendIt(Telegram $bot): void
    {
        for ($i = 0; $i < 30; $i += 2) {
            $inlineKeyboard = BotHelper::makeKeyboard2button(trans("bot.Juz") . ($i + 1), config('juz.' . ($i + 1)), trans("bot.Juz") . ($i + 2), config('juz.' . ($i + 2)));
            BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2), $inlineKeyboard);
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
     * @throws GuzzleException
     */
    public
    function generateBaleFehrestThenSendIt(Telegram $bot): void
    {
        $quranSurahs = QuranSurah::select(['id', 'ayah', 'arabic', 'sajda', 'location'])
            ->get();

        for ($i = 0; $i < 114; $i += 6) {
            for ($j = 0; $j < 6; $j++) {
                $array[$j] = [$quranSurahs[$i + $j]->id . ":" . $quranSurahs[$i + $j]->arabic . ":" . $quranSurahs[$i + $j]->ayah, "/sure" . ($i + $j + 1) . "ayah1"];
            }

            $inlineKeyboard = BotHelper::makeKeyboard6button($array);
            BotHelper::messageWithKeyboard(env("QURAN_HEFZ_BOT_TOKEN_BALE"), $bot->ChatID(), trans("bot.surah number:") . ($i + 1) . " " . trans("bot.to") . " " . ($i + 6), $inlineKeyboard);
        }
    }

    /**
     * @param Telegram $bot
     * @return void
     * @throws GuzzleException
     */
    public
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

}
