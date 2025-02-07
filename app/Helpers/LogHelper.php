<?php

namespace App\Helpers;

use App\Http\Requests\BotRequest;
use App\Models\BotLog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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
        $log = new BotLog();
        $log->webhook_endpoint_uri = request()->segment(2);
        $log->bot_mother_id = $request->input('bot_mother_id') ?? 0;
        $log->language = $request->input('language');
        $log->command_type = $request->request->get('command_type');
        $log->locale = App::getLocale();
        $log->type = $type;
        $log->text = mb_convert_encoding(substr($bot->Text(), 0, 199), 'UTF-8', 'UTF-8');
        $log->is_command = str_starts_with($bot->Text(), "/");
        $log->channel_group_type = $bot->ChatID() < 0 ? $bot->ChatID() : 0;
        $log->bot_id = 1;
        $log->chat_id = $bot->ChatID();
        $log->save();
    }

    public static function isLastLogAvailable(BotRequest $request, Telegram $bot): array
    {
        $log = BotLog::query()
            ->whereWebhookEndpointUri(request()->segment(2))
            ->whereBotMotherId($request->input('bot_mother_id'))
            ->whereLanguage($request->input('language'))
            ->whereCommandType('search')
            ->whereLocale(App::getLocale())
            ->whereType($bot->BotType())
            ->whereIsCommand(1)
            ->whereChannelGroupType($bot->ChatID() < 0 ? $bot->ChatID() : 0)
            ->whereChatId($bot->ChatID())
            ->orderby('created_at', 'desc')
            ->first();

        $phrase = $bot->Text();
        $lastStatus = '';
        if ($log) {
            $lastStatus = 'search';
        }
        return [$lastStatus, $phrase];
    }
}
