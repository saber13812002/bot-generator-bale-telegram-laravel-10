<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Services\NahjService;
use App\Models\Nahj;
use App\Models\SharabeBeheshtiMp3;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;


class NahjController extends Controller
{
    private NahjService $nahjService;

    public function __construct(NahjService $nahjService)
    {
        $this->nahjService = $nahjService;
    }

    public static function getMp3UrlAndTitleAndId(mixed $postLink)
    {
        $id = self::getId($postLink);
        Log::info("id:" . $id);
        if ($id < 242 && $id > 0) {

            $nahjItem = Nahj::find($id);
            // Assuming you have a relationship defined in your Nahj model
            $mp3Item = $nahjItem->uploadingMediaFile;

            // Get the media URL
            $mp3ItemMediaUrl = $mp3Item->media_url;

            Log::info("nahj->link:" . $mp3ItemMediaUrl);

            $title = "#" . $nahjItem->title . "
";

            return $mp3ItemMediaUrl;
        }


    }

    private static function getId(mixed $postLink)
    {
//        $url = "sharabebeheshti.ir/shb5?random_id=6838&id=63&utm_source=saber&utm_medium=messenger&utm_campaign=campaign_khoda&utm_term=term_zohoor&utm_content=emamzaman";
//        utm_source=saber&utm_medium=messenger&utm_campaign=campaign_khoda&utm_term=term_zohoor&utm_content=emamzaman

        // تجزیه URL و استخراج کوئری استرینگ
        $queryString = parse_url($postLink, PHP_URL_QUERY);

        // تبدیل کوئری استرینگ به آرایه
        parse_str($queryString, $params);

        // استخراج مقدار id
        return isset($params['id']) ? (string)$params['id'] : null; // اطمینان از نوع بازگشتی

    }

    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        try {

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

                if (StringHelper::ifBotTextIsTooLong($bot, $bot->Text())) {
                    return 1;
                }

                $commands = StringHelper::getNahjCommandsAsPostfixForMessages();
                if (str_starts_with($bot->Text(), "/")) {
                    $offset = strpos($bot->Text(), "/") + Str::length("/");
                    $pageIndex = strpos($bot->Text(), 'page');
                    $command = !$pageIndex ? substr($bot->Text(), $offset) : substr($bot->Text(), $offset, $pageIndex - 1);
                    $isItemRequested = substr($bot->Text(), $offset, 3) == "_id";
                    $command_type = $isItemRequested ? "_id" : $command;
//                dd($offset, $command, $isItemRequested, $command_type, $pageIndex);
                    if ($command == "start") {
                        $message = trans("nahj.in the name of God you can use /help command to start.");
                    } else if ($command == "search") {
                        $message = trans("nahj.Please send your phrase to search in all Nahj ul balagha texts.");
                    } else if ($command == "random") {
                        $page = 1;
                        $limit = 1;
                        $id = rand(1, 815);
                        $message = $this->nahjService->item($bot, $id, $page, $limit);
                        return 1;
                    } else if ($command == "help") {
                        $message = $this->nahjService->help($bot);
                        return 1;
                    } else if ($command == "fehrest") {
                        [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
//                    dd($page);
                        $this->nahjService->list($bot, "", $page, $limit);
                        return 1;
                    } else if ($isItemRequested) { // /_id3
                        $id2 = substr($bot->Text(), 4);
                        [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
//                    dd($page);
                        $this->nahjService->item($bot, $id2, $page, $limit);
                    } else {
                        $message = $this->nahjService->help($bot);
                    }
                } else if (true) {
                    [$phrase, $page, $limit] = $this->getPhraseAndPage($bot);
                    BotHelper::sendMessageToSuperAdmin("nahj:
" . $phrase, $bot->BotType());
                    BotHelper::sendMessage($bot, trans("bot.please wait") . $this->getSearchWebUrl($phrase) . "
    " . trans("bot.if there is no results please try again with non long query with less words. thank you"));
                    $message = $this->nahjService->search($phrase, $page, $limit);
                    $message .= trans("nahj.for more result click this link:") .
                        $this->getSearchWebUrl($phrase);
                }

                BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            return 0;
        }


        try {
            $request->request->add(['command_type' => $command_type]);
            LogHelper::log($request, $type, $bot);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
        }

        return 0;
    }

    private function getPhraseAndPage(Telegram $bot): array
    {
        $text = $bot->Text();
        [$searchPhrase, $pageNumber] = QuranHelper::getPageNumberFromPhrase($text);
        $limit = 5;
        return [$searchPhrase, $pageNumber, $limit];
    }


    private function getSearchWebUrl($phrase): string
    {
        return "
https://hadith.academyofislam.com/?q=" . StringHelper::normalizer($phrase);
    }

}
