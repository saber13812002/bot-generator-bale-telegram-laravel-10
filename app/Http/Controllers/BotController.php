<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\TokenHelper;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Models\Bot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $bale = $this->getBotByType('bale');

        $chat_id = $bale->ChatID();

        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getContent())];
        $bale->sendMessage($content);

        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getQueryString())];
        $bale->sendMessage($content);

        if ($request->has('bot_token') && $request->has('bot_user_name')) {
            $bot_token = $request->input('bot_token');
            $bot_user_name = $request->input('bot_user_name');

            $botItem = Bot::whereBaleBotName($bot_user_name)
                ->whereBaleBotToken($bot_token)
                ->get()
                ->first();

            $content = ['chat_id' => $chat_id, 'text' => $botItem->id];
            $bale->sendMessage($content);
        } else {
            $content = ['chat_id' => $chat_id, 'text' => 'حاجی توکن درست توی وب هوک ست نشده. چکنیم به ادمین خبر بده @sabertaba'];
            $bale->sendMessage($content);
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

}
