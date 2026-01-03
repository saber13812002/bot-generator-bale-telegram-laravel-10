<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\StoreBotUsersRequest;
use App\Http\Requests\UpdateBotUsersRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws \Exception
     */
    public function approve(Request $request)
    {
        $type = 'bale';
        $keys = ['origin',
            'chat_id',
            'bot_id',
            'token'];
        $checkRequest = $request->only($keys);

        if (count($checkRequest) == 4) {
            try {
                $botItem = Bot::query()->whereId($request->bot_id)
                    ->whereBaleBotToken($request->token)
                    ->get()
                    ->firstOrFail();
            } catch (Exception $e) {
                BotHelper::sendMessageToSuperAdmin(trans("bot.An error occurred when admin want to approve your request"), $type);
                Log::error($e->getMessage());
//                throw $e;
            }

            $bot = new Telegram($botItem->bale_bot_token, $type);

            try {
                $botUserItem = BotUsers::query()->whereBotId($request->bot_id)
                    ->whereChatId($request->chat_id)
                    ->whereOrigin($type)
                    ->whereStatus('suspend')
                    ->get()
                    ->firstOrFail();

            } catch (Exception $e) {
                $message = 'یا این روبات قبلا تایید شده است و الان دارد دوباره تایید میشود';
                BotHelper::sendMessageToBotAdmin($bot, $message);
                Log::error($e->getMessage());
//                throw $e;
            }

            $botUserItem->status = 'active';
            $botUserItem->save();

            BotHelper::sendMessageByChatId($bot, $request->chat_id, trans("bot.Your request accepted as well").trans("bot.You can start your activities"));
            BotHelper::sendMessageByChatId($bot, $botItem->bale_owner_chat_id, 'این کاربر تایید شد:' . $request->chat_id);
            BotHelper::sendMessageToSuperAdmin('تایید یک کاربر توسط ادمین روبات ایکس با موفقیت انجام شد', $type);

        } else {
            Log::info('message : تایید ممکن نیست لینک تایید مشکل دارد');
        }

        echo 'کاربر تایید شد بازگشت به برنامه
 <br> <a href=\'https://web.bale.ai\'>https://web.bale.ai</a>';

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
    public function store(StoreBotUsersRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BotUsers $botUsers)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BotUsers $botUsers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBotUsersRequest $request, BotUsers $botUsers)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BotUsers $botUsers)
    {
        //
    }
}
