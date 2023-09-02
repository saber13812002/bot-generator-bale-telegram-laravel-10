<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use App\Models\BotUsers;
use Exception;
use Gap\SDP\Api as GapBot;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Telegram;

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

//        dd($request);
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


            $arrayCommands = QuranHelper::generateArrayCommands($userSettings);

//            if ((substr($bot->Text(), 0, 2)) == "//")
//                $request->request->add(['command_type' => 'quran_search']);
//            else
//                $request->request->add(['command_type' => 'quran']);

            $command_type = "";

            $commandTemplateSure = '/sure';
            $commandTemplateAyah = 'ayah';

            $commandTemplateScan = '/scan';
            $commandTemplateHr = 'hr';

            $botText = Str::lower($bot->Text());
            if ($botText == '/start') {

                $command_type = "start";
                $isStartCommandShow = 0;
                list($message, $messageCommands) = QuranHelper::getStringCommandsStartBot($type);
                $reciterCommands = QuranHelper::getSettingReciter();
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

                $command_type = "word";
                $wordId = QuranHelper::getWordId($bot);
                [$message, $isEndAya] = QuranHelper::getQuranWordById($wordId);
//                BotHelper::sendMessageToSuperAdmin("از طرف تلگرام" . ":" . $bot->Text() . ":" . $wordId . ":" . $message, 'bale');
                $next = ((integer)$wordId == 88246 ? "88246" : ((integer)$wordId + 1));
                $back = ((integer)$wordId == 1 ? "1" : ((integer)$wordId - 1));

//                $messageCommands = QuranHelper::getStringCommandsWordByWord($next, $back);

                if ($isEndAya != 1) {
                    $isStartCommandShow = 0;
                }

                if ($type == 'telegram')
                    BotHelper::sendMessage2Button($bot, $message, "/" . $next, "/" . $back);
                else if ($type == 'gap') {
                    $inlineKeyboard = BotHelper::makeGapKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                    BotHelper::messageGapWithKeyboard($bot, $message, $inlineKeyboard);
                } else {
                    $inlineKeyboard = BotHelper::makeKeyboard2button(trans('bot.next'), "/" . $next, trans('bot.previous'), "/" . $back);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif (str_starts_with($botText, $commandTemplateScan)) {

                $command_type = "hr";

                if (preg_match('/scan(.*?)hr/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $pageNumber = (int)$match[1];
                    $page = (integer)$match[1];
                    if ($page > 0) {
                        $hr = (integer)substr($botText, strpos($botText, $commandTemplateHr) + Str::length($commandTemplateHr));

                        if ($hr > 0) {
                            if ($type == 'telegram' || $type == 'bale') {
                                QuranHelper::sendScanPage($bot, $pageNumber, $hr);
                                if ($type == 'bale') {
                                    QuranHelper::sendScanBaleButtons($pageNumber, $token, $bot);
                                }
                                QuranHelper::sendAudioMp3Page($bot, $pageNumber);
                            }
                        }
                    }
                }
            } elseif (str_starts_with($botText, $commandTemplateSure)) {

                $command_type = "ayah";

                if (preg_match('/sure(.*?)ayah/', substr($botText, 1, Str::length($botText)), $match) == 1) {
                    $sure = (integer)$match[1];
                    if ($sure > 0) {
                        $aya = (integer)substr($botText, strpos($botText, $commandTemplateAyah) + Str::length($commandTemplateAyah));

                        if ($aya > 0) {

                            $isStartCommandShow = $aya % 10 == 0 ? 1 : 0;
                            [$message, $pageNumber] = QuranHelper::getSureAye($userSettings, $sure, $aya);

                            [$maxAyah, $sureName] = QuranHelper::getLastAyeBySurehId($sure);
                            [$maxAyahSureGhabli, $sureGhabliName] = QuranHelper::getLastAyeBySurehId($sure != 1 ? $sure - 1 : 114);

                            $message = QuranHelper::addAyeIdAndBesmella($aya, $sureName, $sure, $message);

                            $nextSure = $commandTemplateSure . ($sure != 114 ? $sure + 1 : 1) . $commandTemplateAyah . "1";
                            $firstAyaOfLastSure = $commandTemplateSure . ($sure - 1) . $commandTemplateAyah . "1";
                            $lastAyaOfLastSure = $commandTemplateSure . ($sure - 1) . $commandTemplateAyah . $maxAyahSureGhabli;

                            $nextAye = ($aya == $maxAyah) ? $nextSure : $commandTemplateSure . ($sure) . $commandTemplateAyah . $aya + 1;
                            $lastAye = ($aya == 1) ? $lastAyaOfLastSure : $commandTemplateSure . ($sure) . $commandTemplateAyah . $aya - 1;

                            if ($aya == $maxAyah || $aya == 1) {
                                $isStartCommandShow = true;
                            }
//                            $messageCommands = QuranHelper::getStringCommandsAyaBaya($aya, $maxAyah, $nextAye, $lastAye, $sure, $nextSure, $lastSure);

                            $array = [[trans('bot.next aya'), $nextAye], [trans('bot.previous aya'), $lastAye], [trans('bot.next surah'), $nextSure], [trans('bot.previous surah'), $firstAyaOfLastSure]];
                            if ($type == 'telegram') {
                                BotHelper::sendTelegram4InlineMessage($bot, $message, $array, true);
                            } else if ($type == 'gap') {
                                BotHelper::sendGap4InlineMessage($bot, $message, $array);
                            } else {
                                $inlineKeyboard = BotHelper::makeBaleKeyboard4button($array, $arrayCommands);
                                BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                                QuranHelper::sendScanBaleButtons($pageNumber, $token, $bot);
                            }

                            if ($bot->BotType() != "gap") {
                                QuranHelper::sendAudioMp3Aye($aya, $sure, $bot, $userSettings);
                                if (App::getLocale()) {
                                    $postfix = config("reciter.audio." . App::getLocale(), '');
                                    if ($postfix) {
                                        QuranHelper::sendAudioMp3AyeByLocale($aya, $sure, $bot, $postfix, $userSettings);
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ((substr($bot->Text(), 0, 3)) == "///") {

                $command_type = "///";
                $this->messageToAll($request);
            } elseif ((substr($bot->Text(), 0, 2)) == "//") {

                $command_type = "//";
                $searchPhrase = substr($bot->Text(), 2, strlen($bot->Text()));
                [$searchPhrase, $pageNumber] = QuranHelper::getPageNumberFromPhrase($searchPhrase);
                QuranHelper::findResultThenSend($searchPhrase, $pageNumber, $type, $bot);
            } elseif ((substr($bot->Text(), 0, 1)) == "/") {

                $command_type = "commands";

                $command = substr($botText, strpos($botText, "/") + Str::length("/"));
                if ($command == "fehrest") {
                    if ($type == 'telegram') {
                        QuranHelper::generateTelegramFehrestThenSendIt($bot);
                    } else if ($type == 'gap') {
                        QuranHelper::generateGapFehrestThenSendIt($bot);
                    } else {
                        QuranHelper::generateBaleFehrestThenSendIt($bot, $token);
                    }
                } else if ($command == "joz") {
                    if ($type != 'bale') {
                        QuranHelper::generateJozLinksThenSendItTelegram($bot);
                    } else {
                        QuranHelper::generateJozLinksThenSendItBale($bot);
                    }
                } else if ($command == "report") {
                    $chatId = $bot->ChatID();
                    $this->quranBotUserRankingService->specificUserReport($chatId, $bot);
                    $message = trans("bot.report.this is your reports. your last 7 days activities. click on this link:") . "
                    https://bots.pardisania.ir/report?chat_id=" . $chatId . '&language=' . $request->input('language') . '&origin=' . $type;
                    BotHelper::sendMessage($bot, $message);
                    BotHelper::sendMessageToSuperAdmin($message, $type);
                } else if ($command == "reportall") {
                    if ($type == 'telegram') {
                        BotHelper::sendMessage($bot, "this command not work in telegram");
                    } else {
                        if (AdminHelper::isAdmin($bot->ChatID())) {
                            $this->quranBotUserRankingService->allUsersReportDailyWeeklyMonthly();
                        } else {
                            BotHelper::sendMessage($bot, "you are not admin");
                        }
                    }
                } else if ($command == "listcommands" || $command == "help") {
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
                } else if ($subCommand == "transtr") {

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
                } else if ($subCommand == "transen") {

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
                } else if ($subCommand == "trans") {

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
                } else if ($subCommand == "mp3reciter") {


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
                    BotHelper::send1button($bot, $array);
                    BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                } else if ($type == 'gap') {
//                    BotHelper::sendStart($bot, $array);
//                    BotHelper::sendMessage($bot, trans("bot.your ranking") . " /report");
                } else {
                    $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            }

            $request->request->add(['command_type' => $command_type]);

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }


        } else {
            return ResponseAlias::HTTP_ACCEPTED;
        }
    }


    /**
     * Show the form for creating a new resource.
     * @throws GuzzleException
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

            if (AdminHelper::isAdminCommand($bot->Text())) {
                if (AdminHelper::isAdmin($bot->ChatID())) {

                    $message = AdminHelper::getMessageAdmin($bot->Text());

                    $botBale = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_BALE'), 'bale');
                    $botTelegram = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM'), 'telegram');
                    $logs = BotLog::where('created_at', '>=', Carbon::now()->subDay(1))->whereLanguage('fa')->select('chat_id', 'type')->distinct('chat_id')->get();
                    foreach ($logs as $log) {
                        $count = $logs->count();
                        if ($log['type'] == 'bale') {
                            BotHelper::sendMessageByChatId($botBale, $log['chat_id'], $message);
                            if (QuranHelper::isContainSureAyahCommand($message)) {
                                [$command, $messageButton] = QuranHelper::getCommandByRegex($message);
                                $array = [[$messageButton, $command]];
                                $inlineKeyboard = BotHelper::makeBaleKeyboard1button($array);
                                BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                            }
                        } else {
                            BotHelper::sendMessageByChatId($botTelegram, $log['chat_id'], $message);
                            if (QuranHelper::isContainSureAyahCommand($message)) {
                                [$command, $messageButton] = QuranHelper::getCommandByRegex($message);
                                $array = [[$messageButton, $command]];
                                BotHelper::send1buttonToChatId($bot, $array, $log['chat_id']);
                            }
                        }
                    }
                }
                BotHelper::sendMessage($bot, trans("bot.sent it for :count person", ["count" => $count]));
            }
        }
        return true;
    }

}
