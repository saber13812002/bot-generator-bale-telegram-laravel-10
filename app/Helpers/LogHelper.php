<?php

namespace App\Helpers;

use App\Http\Requests\BotRequest;
use App\Models\BotLog;
use Illuminate\Support\Facades\App;
use Telegram;

class LogHelper
{

    /**
     * @param BotRequest $request
     * @param mixed $type
     * @param Telegram $bot
     * @return void
     */
    public static function log(BotRequest $request, mixed $type, $bot): void
    {
//        dd($request);
//        dd($request->request->get('command_type'));
        $log = new BotLog();
        $log->webhook_endpoint_uri = request()->segment(2);
        $log->bot_mother_id = 0;
        $log->language = $request->input('language');
        $log->command_type = $request->request->get('command_type');
        $log->locale = App::getLocale();
        $log->type = $type;
        $log->text = substr($bot->Text(), 0, 19);
        $log->is_command = str_starts_with($bot->Text(), "/");
        $log->channel_group_type = $bot->ChatID() < 0 ? $bot->ChatID() : 0;
        $log->bot_id = 1;
        $log->chat_id = $bot->ChatID();
//        $log->message_id = 0;// $bot->MessageID();
//        $log->from_id = 0;// $bot->FromID() ?? "";
//        $log->from_chat_id = 0;// $bot->FromChatID() ?? "";

//        dd($log->attributesToArray());
        $log->save();
    }
}
