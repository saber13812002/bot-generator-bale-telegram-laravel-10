<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\NahjApiService;
use App\Models\Nahj;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;


class NahjController extends Controller
{
    private NahjApiService $nahjApiService;

    public function __construct(NahjApiService $nahjApiService)
    {
        $this->nahjApiService = $nahjApiService;
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
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_NAHJ_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_NAHJ_TOKEN_TELEGRAM"), 'telegram');
            }

            config()->set('config.bot.type', $bot->BotType());

            $command_type = "";

            if (self::ifBotTextIsTooLong($bot, $bot->Text())) {
                return 1;
            }

            $commands = StringHelper::getNahjCommandsAsPostfixForMessages();
            if (str_starts_with($bot->Text(), "/")) {
                $offset = strpos($bot->Text(), "/") + Str::length("/");
                $command = substr($bot->Text(), $offset);
                $isItemRequested = substr($bot->Text(), $offset, 3) == "_id";
                $command_type = $isItemRequested ? "_id" : $command;
                if ($command == "start") {
                    $message = trans("nahj.In the name of God . you can use /help command to start.");
                } else if ($command == "search") {
                    $message = trans("nahj.Please send your phrase to search in all Nahj ul balagha texts.");
                } else if ($isItemRequested) {
                    $id2 = substr($bot->Text(), 5);
                    $nahj = Nahj::query()->where("id2", $id2)->first();
                    if ($nahj->count() > 0) {
                        BotHelper::sendMessage($bot, $this->getNahj($nahj));
                    } else {
                        BotHelper::sendMessage($bot, trans("bot.not found"));
                    }
                } else {
                    $message = $this->nahjApiService->help($bot);
                }
            } else if (true) {
                [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
                BotHelper::sendMessageToSuperAdmin("nahj:
" . $phrase, $bot->BotType());
                BotHelper::sendMessage($bot, trans("bot.please wait") . $this->getSearchWebUrl($phrase) . "
    " . trans("bot.if there is no results please try again with non long query with less words. thank you"));
                $message = $this->nahjApiService->search($phrase, $page, $limit);
                $message .= trans("nahj.for more result click this link:") .
                    $this->getSearchWebUrl($phrase);
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


    private static function ifBotTextIsTooLong($bot, string $botText): bool
    {
        if (Str::length($botText) > 70) {
            BotHelper::sendMessage($bot, trans("bot.command is too long for process"));
            return true;
        }
        return false;
    }

    private function getSearchWebUrl($phrase): string
    {
        return "
https://hadith.academyofislam.com/?q=" . StringHelper::normalizer($phrase);
    }

    private function getNahj(Model|Builder|null $hadith): string
    {
        return StringHelper::getStringHadith($hadith->book, $hadith->number, $hadith->part, $hadith->chapter, $hadith->arabic, $hadith->english, $hadith->id2, false);
    }

}
