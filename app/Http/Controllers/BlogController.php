<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
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
        $type = $request->input('origin');
        $botMotherId = $request->input('bot_mother_id');
        if ($type == 'bale') {
            $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_BALE"), 'bale');
        } else {
            $bot = new Telegram($request->has('token') ? $request->input('token') : env("BOT_MOTHER_TOKEN_TELEGRAM"));
        }

        try {
            LogHelper::log($request, $type, $bot);
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }

        $message = trans('bot.please wait');
        BotHelper::sendMessage($bot, $message);


        $author_id = $request->input('author_id');

        // if in blog table has success token get valid twitter phrase and save in blog
        $response = "";
        // if not we can get valid token and save it in blog table
//                dd(explode('.', $bot->Text(), 178)[0]);
        if (!$request->has('author_id')) {
            [$author_id, $blog_token] = BlogHelper::getBlogInfo($type, $bot->ChatID());
        } elseif ($request->has('blog_token')) {
            $author_id = $request->input('author_id');
            $blog_token = $request->input('blog_token');
        }

        if ($bot->Text() == '/start') {
            $message = $this->ifStartCommandBlog($author_id, $bot);
        }

        // TODO: if author id in webhook ... users can sent via bot to another channels
//                if ($author_id) {
        try {
            $message = trans('bot.sending to blog api');
            BotHelper::sendMessage($bot, $message);
            $response = BlogHelper::callApiPost($bot->Text(), $author_id, $blog_token);

        } catch (Exception $e) {
            return $this->handleCallApiExceptions($e, $bot);
        }

        $this->sendResultMessageToUser($response['data'], $bot);

        $response = BlogHelper::callArtisanQueueWork($blog_token);
        return $response;

    }

    /**
     * @param mixed $author_id
     * @param Telegram $bot
     * @return string
     */
    public function ifStartCommandBlog(mixed $author_id, Telegram $bot): string
    {
        if ($author_id) {
            $message = "توییت کنید و شروع کنید.
بعد از توییت لینک برای شما ساخته میشه.
 که اگر آر اس اس شما به توییتر متصل باشه منتشر میشه. از سایت
 dlvr.it
 اقدام به اتصال آر اس اس خود به توییتر خود کنید
لینک آر اس اس شما جهت انجام تنظیمات:
https://blog.pardisania.ir/posts/feed/" . $author_id;

        } else {
            $message = "از ادمین @sabertaba بخواهید که تنظیمات شما رو انجام بده.
قبلش لطفا در سایت blog.pardisania.ir عضو بشید و پیام بدین";
        }
        BotHelper::sendMessage($bot, $message);
        return $message;
    }

    /**
     * @param Exception $e
     * @param Telegram $bot
     * @return string
     */
    public function handleCallApiExceptions(Exception $e, Telegram $bot): string
    {
        $contains = Str::contains($e->getMessage(), 'slug');
        Log::info($e->getMessage());
        if ($contains) {
            $message = "به نظر میرسه توییت شما تکراری است و یا اولین نقطه برای انتخاب عنوان خیلی طولانی شده است. لطفا کمی تغییربش بدهید و مجددا تلاش کنید
                قبلش مطمئن بشید که در
blog.pardisania.ir
منتشر نشده؟ یا در کانال های شما منتشر نشده باشد";
            BotHelper::sendMessage($bot, $message);
            return "{\"error\":\"slug\"}";
        } else {
            $message = "unknown error:";
            BotHelper::sendMessage($bot, $message);
            return "{\"error\":\"" . $e->getMessage() . "\"}";
        }
    }

    /**
     * @param $data
     * @param Telegram $bot
     * @return void
     */
    public function sendResultMessageToUser($data, Telegram $bot): void
    {
        if ($data && $data['id']) {
            $message = config('blog.url') . "/posts/" . $data['slug'];
        } else {
            $message = trans('bot.sending to blog api but nothing returned:' . $data);
        }
        BotHelper::sendMessage($bot, $message);
    }

}
