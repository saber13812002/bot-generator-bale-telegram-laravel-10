<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\BotHelperVoice;
use App\Helpers\LogHelper;
use App\Helpers\TokenHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;
use Gap\SDP\Api as GapBot;


class BotVoiceOfIslamController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function botWebhook(BotRequest $request)
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
                //echo($bot->reply);
                $type = $request->input('origin');
                $language = $request->input('language');
                BotHelperVoice::handleRequestBotVoice($bot, $type, $language, $botMotherId);
            }
        }
    }
}
