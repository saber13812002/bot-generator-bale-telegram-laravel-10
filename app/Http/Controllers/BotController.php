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
    public function index()
    {
        $bot_token = TokenHelper::getMotherBotToken();
        $bale = new Telegram($bot_token, 'bale');
        $chat_id = $bale->ChatID();
        $content = ['chat_id' => $chat_id, 'text' => 'چند لحظه صبر کنید...'];
        $bale->sendMessage($content);
        //echo($bale->reply);
        BotHelper::switchCase($bale);
    }


    /**
     * Display a listing of the resource.
     */
    public function users(Request $request)
    {
        $bot_token = TokenHelper::getMotherBotToken();
        $bale = new Telegram($bot_token, 'bale');

        $chat_id = $bale->ChatID();

        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getContent())];
        $bale->sendMessage($content);
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

}
