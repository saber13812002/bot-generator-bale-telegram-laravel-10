<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\TokenHelper;
use App\Http\Requests\BotRequest;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;


class BotController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function botMotherWebhook(BotRequest $request)
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
                //echo($bale->reply);
                $type = $request->input('origin');
                $language = $request->input('language');
                BotHelper::switchCase($bot, $type, $language, $botMotherId);
            }
        }
    }


    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function getId(BotRequest $request)
    {
        if ($request->has('origin') && $request->has('bot_mother_id') && $request->has('token')) {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            if ($type == 'bale') {
                $bot = new Telegram($request->input('token'), 'bale');
            } else {
                $bot = new Telegram($request->input('token'));
            }


            if ($request->has('language')) {
                $message = trans('bot.your chat id') . "
: " . $bot->ChatID();
                BotHelper::sendMessage($bot, $message);
            }
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function childrenWebhook(Request $request)
    {
        $type = 'bale';
        $baleMotherBot = $this->getMotherBotByType($type);
//        dd(json_decode($request->getContent()), $baleMotherBot);
        $chat_id = $baleMotherBot->ChatID();
        $text = $baleMotherBot->Text();
        $firstName = $baleMotherBot->FirstName();
        $lastName = $baleMotherBot->LastName();
//        dd($firstName);
        if (config('app.env') == 'local') {
            $this->sendMessageRequestContent($chat_id, $request, $baleMotherBot);
        }

        if ($request->has('bot_token') && $request->has('bot_user_name')) {
            $bot_token = $request->input('bot_token');
            $bot_user_name = $request->input('bot_user_name');

            try {
                $botItem = Bot::query()->whereBaleBotName($bot_user_name)
                    ->whereBaleBotToken($bot_token)
                    ->get()
                    ->firstOrFail();
            } catch (Exception $e) {
                BotHelper::sendMessageToSuperAdmin('وب هوک ارسالی به سرور برای روبات بله قادر به تشخیص توکن و یوزرنیم روبات نیست', $type);
                Log::warning($e->getMessage());
//                throw $e;
            }
            // TODO: count check
            if (config('app.env') == 'local') {
                $this->sendDbIdMessage($chat_id, $botItem, $baleMotherBot);
            }

            $bot = new Telegram($botItem->bale_bot_token, $type);

            $user = BotUsers::firstOrCreate([
                'chat_id' => $chat_id,
                'bot_id' => $botItem->id,
                'origin' => $type
            ]);

            if ($user->updated_at == $user->created_at) {
                $message = 'وضعیت شما هنوز توسط ادمین روبات تایید نشده است.';
                $message .= 'وضعیت شما در حال بررسی است، پس از تایید مدیر روبات اطلاع داده خواهد شد';
                BotHelper::sendMessage($bot, $message);
                $bale_owner_chat_id = $botItem->bale_owner_chat_id;
                $content = ['chat_id' => $bale_owner_chat_id, 'text' => 'لطفا روی این دکمه کلیک کنید و فلانی را تایید کنید که بتواند از روبات استفاده کند:'];
                $baleMotherBot->sendMessage($content);
                $content = ['chat_id' => $bale_owner_chat_id, 'text' => config('bot.childbotapproveurl') . '?origin=' . $type . '&chat_id=' . $chat_id . '&bot_id=' . $botItem->id . '&token=' . $botItem->bale_bot_token];
                $baleMotherBot->sendMessage($content);
            } else {
                if ($user->status == 'active' && !str_starts_with($text, "/")) {
                    // TODO: send telegram who's telegram
                    $users = BotUsers::query()->select('chat_id')->whereBotId($botItem->id)
                        ->whereOrigin($type)
                        ->whereStatus('active')
                        ->get();

                    $chatIds = $users->pluck('chat_id')->toArray();
                    $pos = array_search($chat_id . '', $chatIds);
                    unset($chatIds[$pos]);
//                    $array_without_strawberries = array_diff($userList, array($chat_id . ''));


                    foreach ($chatIds as $chatId) {
                        $content = ['chat_id' => $chatId, 'text' => $text . "
متن بالا
از طرف:
" . $firstName . "
" . $lastName
                        ];
                        $bot->sendMessage($content);
                    }


                    BotHelper::sendMessage($bot, 'شما کاربر فعال هستید پیام شما برای همه اعضا بغیر از خودتان ارسال شد');
                }
            }
        } else {
            $content = ['chat_id' => $chat_id, 'text' => 'حاجی توکن درست توی وب هوک ست نشده. چکنیم به ادمین خبر بده @sabertaba'];
            $baleMotherBot->sendMessage($content);
        }

    }

    /**
     * Show the form for creating a new resource.
     */
    public
    function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public
    function store(StoreBotRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public
    function show(Bot $bot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(Bot $bot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateBotRequest $request, Bot $bot)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(Bot $bot)
    {
        //
    }

    /**
     * @param string $type
     * @return Telegram
     */
    public
    function getMotherBotByType(string $type): Telegram
    {
        $bot_token = TokenHelper::getMotherBotToken($type);
        return new Telegram($bot_token, $type);
    }

    /**
     * @param mixed $chat_id
     * @param Request $request
     * @param Telegram $bale
     * @return array
     */
    public
    function sendMessageRequestContent(mixed $chat_id, Request $request, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => json_encode($request->getContent())];
        $bale->sendMessage($content);
        //TODO: strange things here
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
    public
    function sendDbIdMessage(mixed $chat_id, $botItem, Telegram $bale): array
    {
        $content = ['chat_id' => $chat_id, 'text' => $botItem->id];
        $bale->sendMessage($content);
        return $content;
    }

}
