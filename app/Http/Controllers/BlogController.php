<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use App\Helpers\BotHelper;
use App\Http\Requests\BotRequest;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
//                dd(explode('.', $bot->Text(), 178)[0]);
                [$author_id, $blog_token] = BlogHelper::getBlogInfo($type, $bot->ChatID());
                if ($bot->Text() == '/start') {
                    if ($author_id) {
                        $message = "توییت کنید و شروع کنید.
بعد از توییت لینک برای شما ساخته میشه.
 که اگر آر اس اس شما به توییتر متصل باشه منتشر میشه. از سایت
 dlvr.it
 اقدام به اتصال آر اس اس خود به توییتر خود کنید
لینک آر اس اس شما جهت انجام تنظیمات:
https://blog.pardisania.ir/posts/feed/" . $author_id;

                        BotHelper::sendMessage($bot, $message);
                    } else {
                        $message = "از ادمین @sabertaba بخواهید که تنظیمات شما رو انجام بده.
قبلش لطفا در سایت blog.pardisania.ir عضو بشید و پیام بدین";
                        BotHelper::sendMessage($bot, $message);
                    }
                }

//                if ($author_id) {
                try {

                    $message = trans('bot.sending to blog api');
                    BotHelper::sendMessage($bot, $message);
                    $response = BlogHelper::callApiPost($bot->Text(), $author_id, $blog_token);

                } catch (Exception $e) {
                    $contains = Str::contains($e->getMessage(), 'slug');
                    Log::info($e->getMessage());
                    if ($contains) {
                        $message = "به نظر میرسه توییت شما تکراری است و قبلا مشابه این نوشته شده لطفا کمی تغییربش بدهید";
                        BotHelper::sendMessage($bot, $message);
                        return "{\"error\":\"slug\"}";
                    } else {
                        return "{\"error\":\"" . $e->getMessage() . "\"}";
                    }
                }

                if ($response['data'] && $response['data']['id']) {
                    $message = config('blog.url') . "/posts/" . $response['data']['slug'];
                    BotHelper::sendMessage($bot, $message);
                } else {
                    $message = trans('bot.sending to blog api but nothing returned');
                    BotHelper::sendMessage($bot, $message);
                }
                return $response;
            }
        }

    }

}
