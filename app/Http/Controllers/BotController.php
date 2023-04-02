<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\TokenHelper;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Telegram;


class BotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function bale()
    {
        $type = 'bale';
        $bale = $this->getBotByType($type);
        $message = 'چند لحظه صبر کنید...';
        BotHelper::sendMessage($bale, $message);
        //echo($bale->reply);
        BotHelper::switchCase($bale, $type);
    }

    public function telegram()
    {
        $type = 'telegram';
        $bale = $this->getBotByType($type);
        $message = 'چند لحظه صبر کنید...';
        BotHelper::sendMessage($bale, $message);
        //echo($bale->reply);
        BotHelper::switchCase($bale, $type);
    }

    /**
     * Display a listing of the resource.
     */
    public function baleUsers(Request $request)
    {
        $baleBot = $this->getBotByType('bale');

        $chat_id = $baleBot->ChatID();

        if (config('app.env') == 'local') {
            $this->sendMessageRequestContent($chat_id, $request, $baleBot);
        }

        if ($request->has('bot_token') && $request->has('bot_user_name')) {
            $bot_token = $request->input('bot_token');
            $bot_user_name = $request->input('bot_user_name');

            $botItem = Bot::whereBaleBotName($bot_user_name)
                ->whereBaleBotToken($bot_token)
                ->get()
                ->first();

            if (config('app.env') == 'local') {
                $this->sendDbIdMessage($chat_id, $botItem, $baleBot);
            }

            $bot = new Telegram($botItem->bale_bot_token, 'bale');

            $user = BotUsers::firstOrCreate([
                'chat_id' => $chat_id,
                'bot_id' => $botItem->id,
                'origin' => 'bale'
            ]);

            if ($user->status == 'suspend') {
                if (Carbon::now()->gte($user->created_at)) {
                    $message = 'وضعیت شما هنوز توسط ادمین روبات تایید نشده است';
                } else {
                    $message = 'وضعیت شما در حال بررسی است، پس از تایید مدیر روبات اطلاع داده خواهد شد';
                }
                BotHelper::sendMessage($bot, $message);

                $content = ['chat_id' => $botItem->bale_owner_chat_id, 'text' => 'لطفا روی این دکمه کلیک کنید و فلانی را تایید کنید که بتواند از روبات استفاده کند:'];
                $baleBot->sendMessage($content);
                $content = ['chat_id' => $botItem->bale_owner_chat_id, 'text' => config('bot.baleapproveurl') . ''];
                $baleBot->sendMessage($content);
            }
        } else {
            $content = ['chat_id' => $chat_id, 'text' => 'حاجی توکن درست توی وب هوک ست نشده. چکنیم به ادمین خبر بده @sabertaba'];
            $baleBot->sendMessage($content);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBotRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Bot $bot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bot $bot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBotRequest $request, Bot $bot)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bot $bot)
    {
        //
    }

    /**
     * @param string $type
     * @return Telegram
     */
    public function getBotByType(string $type): Telegram
    {
        $bot_token = TokenHelper::getMotherBotToken($type);
        $bale = new Telegram($bot_token, $type);
        return $bale;
    }

    /**
     * @param mixed $chat_id
     * @param Request $request
     * @param Telegram $bale
     * @return array
     */
    public function sendMessageRequestContent(mixed $chat_id, Request $request, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getContent())];
        $bale->sendMessage($content);

        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getQueryString())];
        $bale->sendMessage($content);
        return $content;
    }

    /**
     * @param mixed $chat_id
     * @param $botItem
     * @param Telegram $bale
     * @return array
     */
    public function sendDbIdMessage(mixed $chat_id, $botItem, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => $botItem->id];
        $bale->sendMessage($content);
        return $content;
    }

}
