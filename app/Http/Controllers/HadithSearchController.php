<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\HadithApiService;
use App\Models\BotHadithItem;
use App\Models\BotKid;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Telegram;


class HadithSearchController extends BotController
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

        $this->setLocale($request);

        $message = "-";

        $type = $request->input('origin');

        if (!$request->has('origin'))
            throw new BadRequestHttpException('origin not specified in query string', null, 400);


        if ($request->input('origin') == 'bale') {
            $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_HADITH_TOKEN_BALE"), 'bale');
        } else {
            $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_HADITH_TOKEN_TELEGRAM"), 'telegram');
        }

        $botMe = $bot->getMe();
        //            dd($bot->getMe()['ok']);
        $botKid = BotKid::firstOrCreate(
            [
                'token' => $bot->token(),
            ],
            [
                'bot_mother_id' => $request->has('bot_mother_id') ? $request->input('bot_mother_id') : "",
                'first_chat_id' => $bot->ChatID(),
                'type' => $bot->BotType(),
                'locale' => $request->has('language') ? $request->input('language') : "",
            ]
        );



        config()->set('config.bot.type', $bot->BotType());

        $command_type = "";

        if (self::ifBotTextIsTooLong($bot, $bot->Text()))
            return 1;

        //            try {
        //            [$lastStatus, $phrase] = LogHelper::isLastLogAvailable($request, $bot);
        //            $ifStatusAndPhraseValid = $this->checkStatusAndPhrase($lastStatus, $phrase);

        $commands = StringHelper::getHadithCommandsAsPostfixForMessages();

        if (str_starts_with($bot->Text(), "/")) { ///_id=82Y5Ln0BGWfjTl3qHQNp
            $offset = strpos($bot->Text(), "/") + Str::length("/");
            $command = substr($bot->Text(), $offset);
            $isHadithIdRequested = substr($bot->Text(), $offset, 3) == "_id";
            $command_type = $isHadithIdRequested ? "_id" : $command;
            if ($command == "start") {
                $message = trans("hadith.in the name of God . you can use /help command to start.");
            } else if ($command == "search") {
                // TODO : saveLastCommandInDb($bot); performance said we need to have lastStatus log for any bot mother
                $message = trans("hadith.Please send your phrase to search in all shia hadith books.");
            } else if ($command == "random") {
                $hadithCount = BotHadithItem::count();
                $page = 1;
                $limit = 1;
                //                    dd($hadithCount);
                $id = rand(1, $hadithCount);
                //                    dd($id);
                $hadith = BotHadithItem::query()->where("id", $id)->first();
                //                    dd($hadith);

                if ($hadith) {
                    $postFix = "
link: to share in twitter or edit
https://hadith.academyofislam.com/?q=_id:" . $hadith->id2 . "
";
                    BotHelper::sendLongMessage($this->getHadith($hadith) . $postFix, $bot);
                } else {
                    BotHelper::sendMessage($bot, trans("bot.not found"));
                }
                return 1;
            } else if ($isHadithIdRequested) {
                //                    echo 'id2';
                $id2 = substr($bot->Text(), 4);
                $hadith = BotHadithItem::query()->where("id2", $id2)->first();
                //                    dd($id2);
                if ($hadith) {

                    $postFix = "
link: to share in twitter or edit
https://hadith.academyofislam.com/?q=_id:" . $hadith->id2 . "
";
                    BotHelper::sendLongMessage($this->getHadith($hadith) . $postFix, $bot);
                } else {
                    BotHelper::sendMessage($bot, trans("bot.not found"));
                }
            } else {
                $message = $this->hadithApiService->help($bot);
            }
        } else if (true) {
            [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
            BotHelper::sendMessageToSuperAdmin("hadith:
" . $phrase, $bot->BotType());
            BotHelper::sendMessage($bot, trans("bot.please wait") . $this->getSearchWebUrl($phrase) . "
    " . trans("bot.if there is no results please try again with non long query with less words. thank you"));
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

    private function getPhraseAndPage(Telegram $bot): array
    {
        $text = $bot->Text();
        [$searchPhrase, $pageNumber] = QuranHelper::getPageNumberFromPhrase($text);
        $limit = 15;
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
