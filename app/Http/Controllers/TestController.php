<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use Illuminate\Support\Facades\App;
use Telegram;

class TestController extends Controller
{

    public function testReferral(BotRequest $request)
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
            }
//            elseif ($request->input('origin') == 'gap') {
//                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_GAP");
//                $bot = new GapBot($token, $request);
//                $bot->sendText(env("SUPER_ADMIN_CHAT_ID_GAP"), ": " . $bot->ChatID() . " : " . $bot->Text() . " : ");
//            } else {
//                return 200;
//            }

            $text = $bot->getData();
            BotHelper::sendMessage($bot, json_encode($text, true));


            // Get the chat ID of the user
            $chat_id = $bot->ChatID();

            // Generate a new invite link for the chat
            $chatInviteLink = BotHelper::createChatInviteLink("berimbasketbot", "id", $chat_id, $type);
            //
            //// Send the invite link to the user
            BotHelper::sendMessage($bot, trans('bot.here is your referral link') . ' : ' . $chatInviteLink);

            // Get the referral code from the start command
            [$start_command, $params] = BotHelper::getCommandRefferralWhenStart($bot->Text(), '?');
            if ($start_command === 'start' && isset($params)) {
                $referralCode = $params["id"];
                BotHelper::sendMessage($bot, trans('bot.referral code') . ' : ' . $referralCode);
            }
        }
    }
}
