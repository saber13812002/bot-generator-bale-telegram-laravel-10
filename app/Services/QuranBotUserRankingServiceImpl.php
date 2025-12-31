<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\HadithHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
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
                ->whereWebhookEndpointUri('webhook-quran-word')
                ->count();

            $count_last_month = BotLog::whereChatId($log['chat_id'])
                ->whereWebhookEndpointUri('webhook-quran-word')
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
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        $count_yesterday = BotLog::whereChatId($chatId)
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('created_at', '<', Carbon::now()->subDay())
            ->where('created_at', '>=', Carbon::now()->subDay(2))
            ->count();

        $result_ayat = $count_today - $count_yesterday;
        $result_ayat_if_negetive = $count_yesterday - $count_today;

        // Build the main report message
        $message = "📊 گزارش فعالیت شما\n\n";
        
        // Ranking
        $message .= "🏆 " . trans("bot.your ranking in last 30 days is") . ": " . $rank . "\n\n";
        
        // Today's usage
        $message .= "📖 " . trans("bot.your todays usage of this bot") . ": " . $count_today . " " . trans("bot.ayah") . "\n";
        
        // Comparison result with complete sentence
        if ($result_ayat > 0) {
            $message .= "📈 " . trans("bot.which compared to the previous day") . " " . $result_ayat . " " . trans("bot.ayah") . " مطالعه شما بیشتر از فعالیت دیروز است\n";
        } elseif ($result_ayat < 0) {
            $message .= "📉 " . trans("bot.which compared to the previous day") . " " . $result_ayat_if_negetive . " " . trans("bot.ayah") . " مطالعه شما کمتر از فعالیت دیروز است\n";
        } else {
            $message .= "➡️ " . trans("bot.which compared to the previous day") . " تعداد آیه‌های مطالعه شما برابر با فعالیت دیروز است\n";
        }
        
        // Special message for zero readings
        if ($result_ayat == 0 && $count_today == 0) {
            $message .= "\n⚠️ " . trans("bot.your today readings is zero") . "\n";
            $message .= "👇👇👇\n";
            $message .= "https://www.imamalicenter.se/fa/20hadith_om_Koran\n";
        }
        
        // Last activities section
        $lastActivities = $this->getLastVerseActivities($chatId);
        if ($lastActivities->count() > 0) {
            $message .= "\n📚 آخرین فعالیت‌های شما:\n\n";
            
            $emojiNumbers = ['1️⃣', '2️⃣', '3️⃣'];
            $index = 0;
            foreach ($lastActivities as $activity) {
                $formattedActivity = $this->formatActivity($activity->text);
                $message .= $emojiNumbers[$index] . " " . $formattedActivity . " (" . $activity->text . ")\n";
                $index++;
            }
        }
        
        // Separator before hadith
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Hadith section
        $message .= "📜 حدیث روز:\n\n";
        $message .= HadithHelper::random_hadith();
        
        // Last verse and continue section
        if ($lastActivities->count() > 0) {
            $lastActivity = $lastActivities->first();
            [$lastSure, $lastAyah] = StringHelper::getSureAyeByRegex($lastActivity->text);
            
            if ($lastSure > 0 && $lastAyah > 0) {
                $formattedLastActivity = $this->formatActivity($lastActivity->text);
                $nextCommand = $this->getNextAyahCommand($lastSure, $lastAyah);
                
                $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "📖 آخرین آیه‌ای که می‌خوندی: " . $formattedLastActivity . "\n";
                
                if ($nextCommand) {
                    $message .= "ادامه بده: " . $nextCommand;
                } else {
                    $message .= "✅ شما قرآن را به پایان رساندید!";
                }
            }
        }
        
        return $message;
    }

    /**
     * Get last 3 verse activities for a user
     * 
     * @param string $chatId
     * @return Collection
     */
    private function getLastVerseActivities(string $chatId): Collection
    {
        try {
            // Get all command logs first, then filter in PHP for better compatibility
            $allCommands = BotLog::whereChatId($chatId)
                ->whereWebhookEndpointUri('webhook-quran-word')
                ->where('is_command', true)
                ->orderBy('created_at', 'desc')
                ->limit(50) // Get more to filter
                ->get(['text', 'created_at']);

            // Filter by regex pattern
            $lastActivities = $allCommands->filter(function ($log) {
                return preg_match('/\/sure[0-9]+ayah[0-9]+/', $log->text);
            })->take(3);

            return $lastActivities;
        } catch (\Exception $e) {
            \Log::error('Error getting last verse activities', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Format activity text to readable format
     * 
     * @param string $text
     * @return string
     */
    private function formatActivity(string $text): string
    {
        try {
            [$sure, $ayah] = StringHelper::getSureAyeByRegex($text);
            
            if ($sure > 0 && $ayah > 0) {
                return trans("bot.surah number:") . $sure . "، " . trans("bot.ayah") . " " . $ayah;
            }
            
            return $text;
        } catch (\Exception $e) {
            \Log::error('Error formatting activity', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            return $text;
        }
    }

    /**
     * Get next ayah command
     * 
     * @param int $sure
     * @param int $ayah
     * @return string|null
     */
    private function getNextAyahCommand(int $sure, int $ayah): ?string
    {
        try {
            [$maxAyah, $arabic] = QuranHelper::getLastAyeBySurehId($sure);
            
            // Check if surah exists and has valid max ayah
            if (!$maxAyah || $maxAyah == 0) {
                // If surah not found, just increment ayah (fallback)
                $nextAyah = $ayah + 1;
                $nextSure = $sure;
                
                // If we're at surah 114, wrap to first surah
                if ($nextSure > 114) {
                    $nextSure = 1;
                }
                
                return StringHelper::command_template_sure . $nextSure . StringHelper::command_template_ayah . $nextAyah;
            }
            
            $nextAyah = $ayah + 1;
            $nextSure = $sure;
            
            // If current ayah is the last in surah, go to next surah
            if ($ayah >= $maxAyah) {
                $nextSure = $sure + 1;
                $nextAyah = 1;
                
                // If we're at the last surah (114), wrap to first surah
                if ($nextSure > 114) {
                    $nextSure = 1;
                }
            }
            
            return StringHelper::command_template_sure . $nextSure . StringHelper::command_template_ayah . $nextAyah;
        } catch (\Exception $e) {
            \Log::error('Error getting next ayah command', [
                'sure' => $sure,
                'ayah' => $ayah,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function specificUserReport($chatId, $bot = null)
    {
        $this->generateReportThenSend($chatId, $bot, $bot);
    }

    /**
     * @param string $requesterChatId
     * @param $botBale
     * @param $botTelegram
     * @return void
     */
    public function generateReportThenSend(string $requesterChatId, $botBale, $botTelegram): void
    {
        $logs = BotLog::whereLanguage('fa')
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->select('chat_id', 'type')
            ->distinct('chat_id')->get();

        // TODO: implement by cache
        $unsortedRankings = $this->calculateRanking($logs);
        $sortedRankings = $unsortedRankings
            ->sortBy("result_month", null, true)
            ->forPage(1, 200);

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

    public function allUsersReportDailyWeeklyMonthly($type = null)
    {
//        return 0;
        //
        $count_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->count();

        $count_unique_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->distinct('chat_id')
            ->count();


        $count_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->count();

        $count_unique_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->distinct('chat_id')
            ->count();


        $count_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->count();

        $count_unique_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->distinct('chat_id')
            ->count();


        $count_yearly = BotLog::where('created_at', '>=', Carbon::now()->subDay(366))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->count();

        $count_unique_yearly = BotLog::where('created_at', '>=', Carbon::now()->subDay(366))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->distinct('chat_id')
            ->count();

        $postfix_local = env('APP_ENV');

        $message = trans("bot.today usage of this bot") . $count_daily . trans("bot.ayah") . "
" . trans("bot.unique users of todays statistics") . ":" . $count_unique_daily . "

" . trans("bot.number of ayah in last week by all users") . ":" . $count_weekly . "
" . trans("bot.unique users in last week") . ":" . $count_unique_weekly . "

" . trans("bot.number of ayah in last month by all users") . ":" . $count_monthly . "
" . trans("bot.unique users in last month") . ":" . $count_unique_monthly . "

" . trans("bot.number of ayah in last year by all users") . ":" . $count_yearly . "
" . trans("bot.unique users in last year") . ":" . $count_unique_yearly . "

" . ($postfix_local == "production" ? "" : ("env:" . $postfix_local)) . "

" . trans("bot.please help us to promote this bot to other people") . "

اللهم صل علی محمد و آل محمد و عجل فرجهم

استغفر الله ربی و اتوب الیه

" . trans("bot.to send your daily activity report please try it with this command") . "

👇 👇 👇 👇 👇
" . ($type == 'bale' ? "/report [/report](send:/report)
" : "/report
");


//        BotHelper::sendMessageToSuperAdmin($message, 'telegram');
//        BotHelper::sendMessageToSuperAdmin($message, 'bale');

        $logs = BotLog::whereLanguage('fa')
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->select('chat_id', 'type')
            ->distinct('chat_id')
            ->get();


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
