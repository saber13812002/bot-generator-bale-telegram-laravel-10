<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\HadithApiService;
use App\Models\BotHadithItem;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

            config()->set('config.bot.type', $bot->BotType());

            $command_type = "";

            if (self::ifBotTextIsTooLong($bot, $bot->Text()))
                return 1;

//            try {
//            [$lastStatus, $phrase] = LogHelper::isLastLogAvailable($request, $bot);
//            $ifStatusAndPhraseValid = $this->checkStatusAndPhrase($lastStatus, $phrase);

            $commands = StringHelper::getHadithCommandsAsPostfixForMessages();
            if (str_starts_with($bot->Text(), "/")) {///_id=82Y5Ln0BGWfjTl3qHQNp
                $offset = strpos($bot->Text(), "/") + Str::length("/");
                $command = substr($bot->Text(), $offset);
                $isHadithIdRequested = substr($bot->Text(), $offset, 3) == "_id";
                $command_type = $isHadithIdRequested ? "_id" : $command;
                if ($command == "start") {
                    $message = trans("hadith.in the name of God . you can use /help command to start.");
                } else if ($command == "search") {
                    // TODO : saveLastCommandInDb($bot); performance said we need to have lastStatus log for any bot mother
                    $message = trans("hadith.Please send your phrase to search in all shia hadith books.");
                } else if ($isHadithIdRequested) {
//                    echo 'id2';
                    $id2 = substr($bot->Text(), 5);
                    $hadith = BotHadithItem::query()->where("id2", $id2)->first();
                    if ($hadith->count() > 0)
                        BotHelper::sendMessage($bot, $this->getHadith($hadith));
                    else
                        BotHelper::sendMessage($bot, trans("bot.not found"));
                } else {
                    $message = $this->hadithApiService->help($bot);
                }
            } else if (true) {
                [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
                BotHelper::sendMessageToSuperAdmin("hadith:
" . $phrase, $bot->BotType());
                BotHelper::sendMessage($bot, trans("bot.please wait") . $this->getSearchWebUrl($phrase));
                $message = $this->hadithApiService->search($phrase, $page, $limit);
                $message .= trans("hadith.for more result click this link:") .
                    $this->getSearchWebUrl($phrase);
            }

            BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
//            } catch (Exception $e) {
//                BotHelper::sendMessage($bot, "bot.error! Sorry please try another phrase.  ");
//                BotHelper::sendMessageToSuperAdmin("error: " . substr($e->getMessage(), 1500), $bot->BotType());
//            }

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

    private static function ifBotTextIsTooLong($bot, string $botText): bool
    {
        if (Str::length($botText) > 70) {
            BotHelper::sendMessage($bot, trans("bot.command is too long for process"));
            return true;
        }
        return false;
    }

    private function getSearchWebUrl($phrase)
    {
        return "
https://hadith.academyofislam.com/?q=" . StringHelper::normalizer($phrase);
    }

    private function getHadith(Model|Builder|null $hadith)
    {
        return StringHelper::getStringHadith($hadith->book, $hadith->number, $hadith->part, $hadith->chapter, $hadith->arabic, $hadith->english, $hadith->id2, false);
    }

}
