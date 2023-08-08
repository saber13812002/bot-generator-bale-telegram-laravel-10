<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\BotQuranHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Http\Requests\BotRequest;
use Exception;
use Gap\SDP\Api as GapBot;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Telegram;

class NamazController extends Controller
{

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

            $command_type = "";

            $commandTemplateRokat = "rokat";

            $arrayCommands = $this->generateArrayCommands();

            $botText = Str::lower($bot->Text());
            if ($botText == '/start') {

                $command_type = "start";
                $isStartCommandShow = 0;
                list($message, $messageCommands) = $this->getStringCommandsStartBot($type);
                $reciterCommands = BotQuranHelper::getSettingReciter();

                if ($type == 'telegram') {
                    BotHelper::sendTelegram4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $arrayCommands, true);
                } else if ($type == 'gap') {
                    BotHelper::sendGap4InlineMessage($bot, $message . $messageCommands . $reciterCommands, $arrayCommands);
                } else {
                    $inlineKeyboard = BotHelper::makeBaleKeyboard4button($arrayCommands, $arrayCommands);
                    BotHelper::messageWithKeyboard($token, $bot->ChatID(), $message, $inlineKeyboard);
                }
            } elseif (str_starts_with($botText, $commandTemplateRokat)) {

                $command_type = "namaz";

                ///

            } elseif ((substr($bot->Text(), 0, 3)) == "///") {

                $command_type = "///";
                $this->messageToAll($request);
            } elseif ((substr($bot->Text(), 0, 1)) == "/") {

                $command_type = "commands";

                $command = substr($botText, strpos($botText, "/") + Str::length("/"));

                if ($command == "listcommands" || $command == "help") {
                    $message = trans("bot.command list is") . "
: /start
: /report " . trans('bot.help.your quran readings analysis report') . "
: /listcommands " . trans('bot.help.list of this robot commands') . "

";
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



    private function generateArrayCommands()
    {
            return [
                [
                    "text" => trans("bot.namaz.rokat2"),
                    "callback_data" => "/rokat2"
                ],
                [
                    "text" => trans("bot.namaz.rokat41"),
                    "callback_data" => "/rokat41"
                ],
                [
                    "text" => trans("bot.namaz.rokat42"),
                    "callback_data" => "/rokat42"
                ],
                [
                    "text" => trans("bot.namaz.rokat3"),
                    "callback_data" => "/rokat3"
                ],
                [
                    "text" => trans("bot.namaz.rokat43"),
                    "callback_data" => "/rokat43"
                ]
            ];
    }

    private function getStringCommandsStartBot(mixed $type)
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
:/rokat2" . 1 . "
" .
                trans('bot.ayah after ayah') . "
/rokat2
" .
                trans('bot.List of 114 Surahs') . "
/rokat2
" .
                trans('bot.List of 30 Juz') . "
/rokat2";
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

    private function messageToAll(BotRequest $request)
    {
    }


}
