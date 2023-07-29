<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\BotQuranHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\QuranSurah;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Telegram;
use Gap\SDP\Api as GapBot;

class QuranWordController extends Controller
{

    private QuranBotUserRankingService $quranBotUserRankingService;

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

        dd($request);
    }

    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        if ($request->has('language')) {
            App::setLocale($request->input('language'));
        } else {
            App::setLocale("fa");
        }

        $isStartCommandShow = 1;
        $type = $request->input('origin');
        $token = "";
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_BALE");
                $bot = new Telegram($token, 'bale');
            } elseif ($request->input('origin') == 'telegram') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
                $bot = new Telegram($token);
            } elseif ($request->input('origin') == 'gap') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_GAP");
                $bot = new GapBot($token, $request);
                $bot->sendText(env("SUPER_ADMIN_CHAT_ID_GAP"), ": " . $bot->ChatID() . " : " . $bot->Text() . " : ");
            } else {
                return 200;
            }

            $userSettings = null;
            if ($bot->ChatID() && $request->input('bot_mother_id') && $type)
                $userSettings = BotUsers::firstOrNew($bot->ChatID(), $request->input('bot_mother_id'), $type);


            $arrayCommands = $this->generateArrayCommands($userSettings);

            if ((substr($bot->Text(), 0, 2)) == "//")
                $request->request->add(['command_type' => 'quran_search']);
            else
                $request->request->add(['command_type' => 'quran']);

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $commandTemplateSure = '/sure';
            $commandTemplateAyah = 'ayah';

            $botText = Str::lower($bot->Text());
            if ($botText == '/start') {
                $isStartCommandShow = 0;
                list($message, $messageCommands) = QuranHefzBotHelper::getStringCommandsStartBot($type);
                $reciterCommands = BotQuranHelper::getSettingReciter();
                $array = [[trans('bot.word by word'), "/1"], [trans('bot.ayah after ayah'), "/sure2ayah2"], [trans('bot.List of 114 Surahs'), "/fehrest"], [trans('bot.List of 30 Juz'), "/joz"]];
//                dd($array,$message, $messageCommands);
                if ($type == 'telegram') {
                    BotHelper::sendTelegram4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $array, true);
                } else if ($type == 'gap') {
                    BotHelper::sendGap4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $array);
                } else {
                    $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array, $arrayCommands);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif ((integer)(substr($bot->Text(), 1, 1)) > 0) {
                $wordId = $this->getWordId($bot);
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
                else if ($type == 'gap') {
                    $inlineKeyboard = BotHelper::makeGapKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                    BotHelper::messageGapWithKeyboard($bot, $message, $inlineKeyboard);
                } else {
                    $inlineKeyboard = BotHelper::makeKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif (str_starts_with($botText, $commandTemplateSure)) {
                if (preg_match('/sure(.*?)ayah/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $sure = (integer)$match[1];
                    if ($sure > 0) {
                        $aya = (integer)substr($botText, strpos($botText, $commandTemplateAyah) + Str::length($commandTemplateAyah));

                        if ($aya > 0) {

                            $isStartCommandShow = $aya % 10 == 0 ? 1 : 0;
                            $message = QuranHefzBotHelper::getSureAye($userSettings, $sure, $aya);

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
                            } else if ($type == 'gap') {
                                BotHelper::sendGap4InlineMessage($bot, $message, $array);
                            } else {
                                $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array, $arrayCommands);
                                BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                            }
                            $this->sendAudioMp3Aye($aya, $sure, $bot, $userSettings);
                        }
                    }
                }
            } elseif ((substr($bot->Text(), 0, 3)) == "///") {
                $this->messageToAll($request);
            } elseif ((substr($bot->Text(), 0, 2)) == "//") {
                $searchPhrase = substr($bot->Text(), 2, strlen($bot->Text()));
                [$searchPhrase, $pageNumber] = QuranHefzBotHelper::getPageNumberFromPhrase($searchPhrase);
                QuranHefzBotHelper::findResultThenSend($searchPhrase, $pageNumber, $type, $bot);
            } elseif ((substr($bot->Text(), 0, 1)) == "/") {
                $command = substr($botText, strpos($botText, "/") + Str::length("/"));
                if ($command == "fehrest") {
                    if ($type == 'telegram') {
                        $this->generateTelegramFehrestThenSendIt($bot);
                    } else if ($type == 'gap') {
                        $this->generateGapFehrestThenSendIt($bot);
                    } else {
                        $this->generateBaleFehrestThenSendIt($bot, $token);
                    }
                }
                if ($command == "joz") {
                    if ($type != 'bale') {
                        $this->generateJozLinksThenSendItTelegram($bot);

                    } else {
                        $this->generateJozLinksThenSendItBale($bot);
                    }
                }

                if ($command == "report") {
                    $chatId = $bot->ChatID();
                    $this->quranBotUserRankingService->specificUserReport($chatId, $bot);
                }

                if ($command == "reportall") {
                    if (BotHelper::isAdmin($bot->ChatID())) {
                        $this->quranBotUserRankingService->allUsersReportDailyWeeklyMonthly();
                    } else {
                        BotHelper::sendMessage($bot, "you are not admin");
                    }
                }

                if ($command == "listcommands" || $command == "help") {
                    $message = trans("bot.command list is") . "
: /start
: /joz " . trans('bot.help.list of Quran 30 parts') . "
: /fehrest " . trans('bot.help.list of Surahs of the Quran') . "
: /report " . trans('bot.help.your quran readings analysis report') . "
: /mp3_true " . trans('bot.help.send mp3 for selected reciter') . "
: /mp3_false " . trans('bot.help.disable sending mp3 for every ayah') . "
: /mp3Reciter_parhizgar " . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.parhizgar')]) . "
: /mp3Reciter_alafasy " . trans('bot.help.choose :reciter as reciter', ['reciter' => trans('bot.alafasy')]) . "
: /listcommands " . trans('bot.help.list of this robot commands') . "
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


                    BotHelper::sendMessage($bot, $message);
                }

                $subCommand = substr($command, 0, strpos($command, "_"));
                $value = substr($command, strpos($command, "_") + 1);
//                dd($command, $subCommand, $value);

                if ($subCommand == "mp3") {
                    $mp3Reciter = $userSettings->setting('mp3_reciter');
                    $translationId = $userSettings->setting('translation_id');
                    $quranTransliterationTr = $userSettings->setting('quran_transliteration_tr');
                    $quranTransliterationEn = $userSettings->setting('quran_transliteration_en');
//                    dd($mp3Enable, $mp3Reciter);
                    $arr = [
                        'mp3_reciter' => $mp3Reciter,
                        'mp3_enable' => $value,
                        'quran_transliteration_tr' => $quranTransliterationTr,
                        'quran_transliteration_en' => $quranTransliterationEn,
                        'translation_id' => $translationId
                    ];

                    $user = $userSettings->settings($arr);
//                    dd($userSettings->setting('mp3_enable'));
                    $mp3Enable = $user->setting('mp3_enable');
//                    dd($userSettings->setting('mp3_enable'),$mp3Enable);

                    $message = $mp3Enable == "true" ? trans("bot.enabled") : trans("bot.disabled");
                    $pleaseEnableDisable = $mp3Enable == "true" ? trans("bot.please disable mp3 by") : trans("bot.please enable mp3 by");
                    BotHelper::sendMessage($bot, $message . " " . $pleaseEnableDisable . " /mp3_" . ($mp3Enable == "true" ? "false" : "true"));
                }

                if ($subCommand == "transtr") {

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
                }

                if ($subCommand == "transen") {

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
                }

                if ($subCommand == "trans") {

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
                }

                if ($subCommand == "mp3reciter") {


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
            } else {
                $message = trans('bot.bot cant recognized your command') . " /start";
                BotHelper::sendMessage($bot, $message);
            }

            if ($isStartCommandShow) {

                $array = [[trans("bot.return to command list"), "/start"]];
                $message = $array[0][0];
                if ($type == 'telegram') {
                    BotHelper::sendStart($bot, $array);
                    BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                } else if ($type == 'gap') {
//                    BotHelper::sendStart($bot, $array);
//                    BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                } else {
                    $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            }
        } else {
            return ResponseAlias::HTTP_ACCEPTED;
        }
    }


    /**
     * Show the form for creating a new resource.
     */
    public
    function messageToAll(BotRequest $request)
    {

        if ($request->has('language')) {
            App::setLocale($request->input('language'));
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

            if (BotHelper::isAdminCommand($bot->Text())) {
                if (BotHelper::isAdmin($bot->ChatID())) {

                    $message = BotHelper::getMessageAdmin($bot->Text());

                    $botBale = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_BALE'), 'bale');
                    $botTelegram = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM'), 'telegram');
                    $logs = BotLog::where('created_at', '>=', Carbon::now()->subDay(50))->whereLanguage('fa')->select('chat_id', 'type')->distinct('chat_id')->get();
                    foreach ($logs as $log) {
                        $count = $logs->count();
                        if ($log['type'] == 'bale')
                            BotHelper::sendMessageByChatId($botBale, $log['chat_id'], $message);
                        else
                            BotHelper::sendMessageByChatId($botTelegram, $log['chat_id'], $message);
                    }
                }
                BotHelper::sendMessage($bot, "برای جند نفر ارسال شد " . $count);
            }
        }
    }

    /**
     * @param Telegram $bot
     * @return string
     */
    public
    function getWordId($bot): string
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
    public function sendAudioMp3Aye(int $aya, int $sure, $bot, BotUsers $userSettings = null): void
    {
        if ($aya == 1 && $sure != 1 && $sure != 9) {
            BotQuranHelper::sendAudio($bot, 1, 1);
        }
        BotQuranHelper::sendAudio($bot, $sure, $aya, $userSettings);
    }

    private function generateJozLinksThenSendItBale(Telegram $bot)
    {
        $message = "";
        for ($i = 0; $i < 30; $i += 2) {
            $message .= trans("bot.Juz") . ($i + 1) . " " . trans("bot.and") . " " . ($i + 2) . "
[" . trans("bot.Juz") . ($i + 1) . "](send:" . config('juz.' . ($i + 1)) . ") [" . trans("bot.Juz") . ($i + 2) . "](send:" . config('juz.' . ($i + 2)) . ")
";
        }
        BotHelper::sendMessage($bot, $message);
    }

    private function generateArrayCommands(\Illuminate\Database\Eloquent\Model|bool|BotUsers $userSettings)
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

}
