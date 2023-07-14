<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\HadithHelper;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Telegram;

class QuranBotUserRankingServiceImpl implements QuranBotUserRankingService
{

    public function sendToAllUsers()
    {
        $token = env("QURAN_HEFZ_BOT_TOKEN_BALE");
        $botBale = new Telegram($token, 'bale');

        $token = env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
        $botTelegram = new Telegram($token);

        $requesterChatId = "485750575";

        $this->generateReportThenSend($requesterChatId, $botBale, $botTelegram);
    }


    /**
     * @param mixed $logs
     * @return Collection
     */
    private function calculateRanking(mixed $logs): Collection
    {
        $collection = collect();

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

    /**
     * @param $chatId
     * @param $rank
     * @return string
     */
    public function userStatisticPerDayReport($chatId, $rank): string
    {
        $count_today = BotLog::whereChatId($chatId)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        $count_yesterday = BotLog::whereChatId($chatId)
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
        return $message;
    }

    public function specificUserReport($chatId, \Telegram $bot = null)
    {
        $this->generateReportThenSend($chatId, $bot, $bot);
    }

    /**
     * @param string $requesterChatId
     * @param Telegram $botBale
     * @param Telegram $botTelegram
     * @return void
     */
    public function generateReportThenSend(string $requesterChatId, Telegram $botBale, Telegram $botTelegram): void
    {
        $logs = BotLog::whereLanguage('fa')->select('chat_id', 'type')->distinct('chat_id')->get();

        // TODO: implement by cache
        $unsortedRankings = $this->calculateRanking($logs);
        $sortedRankings = $unsortedRankings->sortBy("result_month", null, true)->forPage(1, 200);

        $rank = 1;
        foreach ($sortedRankings as $sortedRanking) {
            $rank++;
            $chatId = $sortedRanking['chatId'];

            if (!$requesterChatId || $chatId == $requesterChatId) {

                $type = $sortedRanking['type'];

                $bot = $type == 'bale' ? $botBale : $botTelegram;

                $message = $this->userStatisticPerDayReport($chatId, $rank);

                BotHelper::sendMessageByChatId($bot, $chatId, $message);

                $adminChatId = $type == 'bale' ? env("CHAT_ID_ACCOUNT_1_SABER") : env("CHAT_ID_ACCOUNT_2_SABER");
                BotHelper::sendMessageByChatId($bot, $adminChatId, $message . "
:" . $chatId);
            }
        }
    }
}
