<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\HadithHelper;
use App\Models\BotLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Telegram;

class UsersRankingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:users-ranking-command';

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
        $logs = BotLog::whereLanguage('fa')->select('chat_id', 'type')->distinct('chat_id')->get();

        $token = env("QURAN_HEFZ_BOT_TOKEN_BALE");
        $botBale = new Telegram($token, 'bale');

        $token = env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
        $botTelegram = new Telegram($token);

        $rankings = $this->calculateRanking($logs);
        $paginate = $rankings->sortBy("result_month", null, true)->forPage(1, 200);
//        dd($paginate);
        $rank = 1;
        foreach ($paginate as $log) {
//            $ranking = $rankings->where("chatId", $log["chat_id"])->first();
            $this->userStatisticPerDayReport($log, $botBale, $botTelegram, ++$rank);
        }
    }

    /**
     * @param mixed $log
     * @param Telegram $botBale
     * @param Telegram $botTelegram
     * @param $rank
     * @return void
     */
    public function userStatisticPerDayReport(mixed $log, Telegram $botBale, Telegram $botTelegram, $rank): void
    {
//        if ($log['chatId'] == "485750575") {
        $count_today = BotLog::whereChatId($log['chatId'])
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        $count_yesterday = BotLog::whereChatId($log['chatId'])
            ->where('created_at', '<', Carbon::now()->subDay())
            ->where('created_at', '>=', Carbon::now()->subDay(2))
            ->count();

        $result_ayat = $count_today - $count_yesterday;
        $result_ayat_if_negetive = $count_yesterday - $count_today;

        $postfix_hadith = "";
        if ($result_ayat == 0 && $count_today == 0) {
            $postfix_hadith = "
به شما توصیه میکنیم بخاطر تعداد صفر آیه مطالعه در دو روز گذشته احادیث زیر را یک مطالعه بفرمایید.

https://www.imamalicenter.se/fa/20hadith_om_Koran
";
        }

        $postfix = $result_ayat > 0 ? $result_ayat . " پیشرفت داشته اید " : $result_ayat_if_negetive . " آیه کمتر مطالعه کردید ";

        $message = "رتبه شما در سی روز گذشته " . $rank . "
            آمار کل استفاده های شما از این روبات در تلگرام و بله امروز
:" . $count_today . "آیه
که در مقایسه با روز قبل " . $count_yesterday . "
" . $postfix . $postfix_hadith . HadithHelper::random_hadith();

        if ($log['type'] == 'bale') {
            BotHelper::sendMessageByChatId($botBale, $log['chatId'], $message);
            BotHelper::sendMessageByChatId($botBale, env("CHAT_ID_ACCOUNT_1_SABER"), $message . "
:" . $log['chatId']);
        } else {
            BotHelper::sendMessageByChatId($botTelegram, $log['chatId'], $message);
            BotHelper::sendMessageByChatId($botTelegram, env("CHAT_ID_ACCOUNT_2_SABER"), $message . "
:" . $log['chatId']);
        }
//        }
    }


    /**
     * @param mixed $logs
     * @return Collection
     */
    public function calculateRanking(mixed $logs): Collection
    {
        $collection = collect();

        $rankings[] = array();

        foreach ($logs as $log) {
            $count_month = BotLog::whereChatId($log['chat_id'])->where('created_at', '>=', Carbon::now()->subDay(30))
                ->count();

            $count_last_month = BotLog::whereChatId($log['chat_id'])
                ->where('created_at', '<', Carbon::now()->subDay(30))
                ->where('created_at', '>=', Carbon::now()->subDay(60))
                ->count();

            $newItem = array(
                "chatId" => $log['chat_id'],
                "type" => $log['type'],
                "result_month" => $count_month,
                "result_last_month" => $count_last_month
            );

            $collection->add($newItem);
        }

        return $collection;
    }
}
