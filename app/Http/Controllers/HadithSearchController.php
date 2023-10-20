<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\HadithApiService;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;


class HadithSearchController extends Controller
{
    private HadithApiService $hadithApiService;

    public function __construct(HadithApiService $hadithApiService)
    {
        $this->hadithApiService = $hadithApiService;
    }

    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function index(BotRequest $request)
    {

        if ($request->has('language')) {
            App::setLocale($request->input('language'));
        } else {
            App::setLocale("fa");
        }

        $type = $request->input('origin');
        $message = "-";
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_HADITH_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_HADITH_TOKEN_TELEGRAM"), 'telegram');
            }

            $command_type = "";

//            [$lastStatus, $phrase] = LogHelper::isLastLogAvailable($request, $bot);
//            $ifStatusAndPhraseValid = $this->checkStatusAndPhrase($lastStatus, $phrase);

            $commands = StringHelper::getHadithCommandsAsPostfixForMessages();
            if (str_starts_with($bot->Text(), "/")) {
                $command = substr($bot->Text(), strpos($bot->Text(), "/") + Str::length("/"));
                $command_type = $command;
                if ($command == "start") {
                    $message = trans("hadith.in the name of God . you can use /help command to start.");
                } else if ($command == "search") {
                    // TODO : saveLastCommandInDb($bot); performance said we need to have lastStatus log for any bot mother
                    $message = trans("hadith.Please send your phrase to search in all shia hadith books.");
                } else {
                    $message = $this->hadithApiService->help($bot);
                }
            } else if (true) {
                [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
                BotHelper::sendMessageToSuperAdmin("hadith: " . $phrase, $bot->BotType());
                BotHelper::sendMessage($bot, trans("bot.please wait"));
                $message = $this->hadithApiService->search($phrase, $page, $limit);
            }

            BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);

            try {
                $request->request->add(['command_type' => $command_type]);
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }

    private function getPhraseAndPage(Telegram $bot): array
    {
        $text = $bot->Text();
        [$searchPhrase, $pageNumber] = QuranHelper::getPageNumberFromPhrase($text);
        $limit = 5;
        return [$searchPhrase, $pageNumber, $limit];
    }

    private function checkStatusAndPhrase(mixed $lastStatus, mixed $phrase)
    {
        return true;
    }

}
