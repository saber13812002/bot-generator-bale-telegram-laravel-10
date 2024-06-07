<?php

namespace App\Http\Controllers;

use App\Helpers\RssHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\BotRequest;
use App\Models\RssPostItemTranslation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRssPostItemTranslationRequest;
use App\Http\Requests\UpdateRssPostItemTranslationRequest;
use App\Services\RocketChatService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;

class RssPostItemTranslationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BotRequest $request)
    {
        // filter all except /publish
        // diag command

        // diag channel

        // diag translation_id

        // send

//        dd(1);

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
//                dd(2);
                if (StringHelper::ifBotTextIsTooLong($bot, $bot->Text())) {
                    return 1;
                }

//                dd(3);
                if (str_starts_with($bot->Text(), "/")) {

                    $parts = explode(":", $bot->Text());

                    if (count($parts) == 4) {
                        $command = $parts[0];
                        $channel = $parts[1];
                        $subChannel = $parts[2];
                        $rssPostItemTranslation = $parts[3];

                        $isItemRequested = str_starts_with($rssPostItemTranslation, "_id");
                        $command_type = $isItemRequested ? "rss_translation_id" : $command;

//                        dd($command, $isItemRequested, $command_type, $channel, $subChannel, $rssPostItemTranslation);


                        if ($command == "/publish" && $channel == "rocket" && $subChannel == "test" && $isItemRequested) {
                            $rssPostItemTranslationId = substr($rssPostItemTranslation, 3);
                            if ($rssPostItemTranslationId > 0) {
                                $rssPostItemTranslationItem = RssPostItemTranslation::find($rssPostItemTranslationId);
                                $message = RssHelper::createMessage($rssPostItemTranslationItem, withCommand: false);
//                                dd($rssPostItemTranslationItem,$message,$subChannel);
                                $rocket = new RocketChatService($message, $subChannel);
                                $rocket->sendMessage();
                            }
                        }
                    }
                }
            }
        } catch
        (Exception $exception) {
            Log::info($exception->getMessage());
            return 0;
        } catch (GuzzleException $e) {
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
    function store(StoreRssPostItemTranslationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public
    function show(RssPostItemTranslation $rssPostItemTranslation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(RssPostItemTranslation $rssPostItemTranslation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateRssPostItemTranslationRequest $request, RssPostItemTranslation $rssPostItemTranslation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(RssPostItemTranslation $rssPostItemTranslation)
    {
        //
    }
}
