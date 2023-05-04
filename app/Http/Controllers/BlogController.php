<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use Exception;
use Telegram;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BotRequest $request)
    {
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

                $type = $request->input('origin');
                $language = $request->input('language');

                // if in blog table has success token get valid twitter phrase and save in blog
                $response = "";
                // if not we can get valid token and save it in blog table
                try {
                    // TODO: get author_id from table
                    $request->merge(["author_id" => config('blog.author_id')]);
                    $response = BlogHelper::callApiPost($bot->Text(), $request->author_id);

                } catch (Exception $e) {
//                    if (Str::before($e->getMessage(), "Client error: `POST http://localhost:8082/api/v1/posts` resulted in a `422 Unprocessable Content` response: {\"message\":\"The given data was invalid.\",\"errors\":{\"slug\":[\"slug")) {
                        $message = "به نظر میرسه توییت شما تکراری است و قبلا مشابه این نوشته شده لطفا کمی تغییربش بدهید";
                        BotHelper::sendMessage($bot, $message);
                        return "{\"error\":\"" . $e->getMessage() . "\"}";
//                    }
                }

                if ($response['data'] && $response['data']['id']) {
                    $message = config('blog.url') . "/posts/" . $response['data']['slug'];
                    BotHelper::sendMessage($bot, $message);
                }
                return $response;

            }
        }

    }
}
