<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\BotLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Telegram;

class TaskReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:task-reminder-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return 0;
        //
        $count_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())->count();
        $count_unique_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())->distinct('chat_id')->count();


        $count_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))->count();
        $count_unique_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))->distinct('chat_id')->count();


        $count_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))->count();
        $count_unique_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))->distinct('chat_id')->count();

        $postfix_local = env('APP_ENV');

        $message = "آمار کل استفاده های این روبات در تلگرام و بله امروز" . $count_daily . " آیه
یونیک یعنی کاربران یکتای امروز:" . $count_unique_daily . "
آمار آیات خوانده شده کل کاربران در هفته: " . $count_weekly . "
کاربران غیر تکراری در هفته: " . $count_unique_weekly . "
آمار آیات خوانده شده کل کاربران در یک ماه قبل: " . $count_monthly . "
کاربران غیر تکراری در سی روز قبل: " . $count_unique_monthly . "
" . ($postfix_local == "production" ? "" : ("env:" . $postfix_local)) . "

با انتشار توضیحات این روبات و معرفی آن به دیگران، کمک کنید مردم بیشتری با قرآن انس بگیرن و حداقل روزی یک آیه قرآن بخوانند و تدبر کنند. به امید جامعه ی بهتر و تعجیل در ظهور صلوات


اللهم صل علی محمد و آل محمد و عجل فرجهم


استغفر الله ربی و اتوب الیه";


//        BotHelper::sendMessageToSuperAdmin($message, 'telegram');
//        BotHelper::sendMessageToSuperAdmin($message, 'bale');

        $logs = BotLog::whereLanguage('fa')->select('chat_id', 'type')->distinct('chat_id')->get();


        $token = env("QURAN_HEFZ_BOT_TOKEN_BALE");
        $botBale = new Telegram($token, 'bale');

        $token = env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
        $botTelegram = new Telegram($token);


        foreach ($logs as $log) {
            if ($log['type'] == 'bale')
                BotHelper::sendMessageByChatId($botBale, $log['chat_id'], $message);
            else
                BotHelper::sendMessageByChatId($botTelegram, $log['chat_id'], $message);
        }

        return 0;
    }
}
