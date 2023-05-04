<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use Exception;
use Illuminate\Support\Str;
use Telegram;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BotRequest $request)
    {
        dd($request);
        if ($request->has('origin') && $request->has('bot_mother_id')) {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            if ($type == 'bale') {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_TELEGRAM"));
            }

            if ($request->has('language')) {
                $message = trans('bot.please wait');
                BotHelper::sendMessage($bot, $message);
                //echo($bot->reply);
                $type = $request->input('origin');
                $language = $request->input('language');

                // if in blog table has success token get valid twitter phrase and save in blog

                // if not we can get valid token and save it in blog table
                try {
                    // TODO: get author_id from table
                    $request->merge(["author_id" => 1]);
                    dd($request->input('text'));
                    return BlogHelper::callApiPost($request);
                } catch (Exception $e) {
                    if (Str::before($e->getMessage(), "Client error: `POST http://localhost:8082/api/v1/posts` resulted in a `422 Unprocessable Content` response: {\"message\":\"The given data was invalid.\",\"errors\":{\"slug\":[\"slug")) {
                        return "{\"error\":\"slug\"}";
                    }
                }

            }
        }

    }
}
