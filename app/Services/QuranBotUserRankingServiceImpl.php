<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\HadithHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use App\Models\BotUsers;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        if ($count_today == 0 && $count_yesterday == 0) {
            // Both today and yesterday are zero
            $message .= "💬 " . trans("bot.you had no reading today and yesterday") . "\n";
        } elseif ($result_ayat > 0) {
            $message .= "📈 " . trans("bot.which compared to the previous day") . " " . $result_ayat . " " . trans("bot.ayah") . " مطالعه شما بیشتر از فعالیت دیروز است\n";
        } elseif ($result_ayat < 0) {
            $message .= "📉 " . trans("bot.which compared to the previous day") . " " . $result_ayat_if_negetive . " " . trans("bot.ayah") . " مطالعه شما کمتر از فعالیت دیروز است\n";
        } else {
            $message .= "➡️ " . trans("bot.which compared to the previous day") . " تعداد آیه‌های مطالعه شما برابر با فعالیت دیروز است\n";
        }
        
        // Special message for zero readings (only if today is zero but yesterday was not)
        if ($result_ayat < 0 && $count_today == 0) {
            $message .= "\n⚠️ " . trans("bot.your today readings is zero") . "\n";
            $message .= "👇👇👇\n";
            $message .= "https://www.imamalicenter.se/fa/20hadith_om_Koran\n";
        }
        
        // Last activities section
        $lastActivities = QuranHelper::getLastVerseActivities($chatId);
        if ($lastActivities->count() > 0) {
            $message .= "\n📚 " . trans("bot.your last activities") . ":\n\n";
            
            $emojiNumbers = ['1️⃣', '2️⃣', '3️⃣'];
            $index = 0;
            foreach ($lastActivities as $activity) {
                $formattedActivity = QuranHelper::formatActivity($activity->text);
                $message .= $emojiNumbers[$index] . " " . $formattedActivity . " (" . $activity->text . ")\n";
                $index++;
            }
        }
        
        // Separator before hadith
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Hadith section
        $message .= "📜 " . trans("bot.hadith of the day") . ":\n\n";
        $message .= HadithHelper::random_hadith();
        
        // Last verse and continue section
        // Special handling for zero activity (both today and yesterday)
        if ($count_today == 0 && $count_yesterday == 0) {
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
            if ($lastActivities->count() > 0) {
                // User has previous activities, suggest continuing from last verse
                $lastActivity = $lastActivities->first();
                [$lastSure, $lastAyah] = StringHelper::getSureAyeByRegex($lastActivity->text);
                
                if ($lastSure > 0 && $lastAyah > 0) {
                    $formattedLastActivity = QuranHelper::formatActivity($lastActivity->text);
                    $nextCommand = QuranHelper::getNextAyahCommand($lastSure, $lastAyah);
                    
                    $message .= "📖 " . trans("bot.the last verse you were reading") . ": " . $formattedLastActivity . "\n";
                    
                    if ($nextCommand) {
                        $message .= trans("bot.start from here") . ": " . $nextCommand;
                    } else {
                        $message .= "✅ " . trans("bot.you have completed the quran");
                    }
                }
            } else {
                // User has no previous activities, suggest random verse from other users
                $randomVerse = QuranHelper::getRandomVerseFromTodayActivities($chatId);
                
                if ($randomVerse) {
                    $formattedRandomVerse = QuranHelper::formatActivity($randomVerse);
                    $message .= "💡 " . trans("bot.suggested verse from other users today") . ": " . $randomVerse . "\n";
                    $message .= trans("bot.start from here") . ": " . $randomVerse;
                } else {
                    // No activities from other users, suggest first verse
                    $message .= trans("bot.start from here") . ": /sure1ayah1";
                }
            }
        } elseif ($lastActivities->count() > 0) {
            // Normal case: show last verse and continue
            $lastActivity = $lastActivities->first();
            [$lastSure, $lastAyah] = StringHelper::getSureAyeByRegex($lastActivity->text);
            
            if ($lastSure > 0 && $lastAyah > 0) {
                $formattedLastActivity = QuranHelper::formatActivity($lastActivity->text);
                $nextCommand = QuranHelper::getNextAyahCommand($lastSure, $lastAyah);
                
                $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "📖 " . trans("bot.the last verse you were reading") . ": " . $formattedLastActivity . "\n";
                
                if ($nextCommand) {
                    $message .= trans("bot.continue") . ": " . $nextCommand;
                } else {
                    $message .= "✅ " . trans("bot.you have completed the quran");
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

        $message = "📊 " . trans("bot.statistics report") . "\n\n";
        
        $message .= "📅 " . trans("bot.daily statistics") . ":\n";
        $message .= "📖 " . trans("bot.total ayah") . ": " . $count_daily . " " . trans("bot.ayah") . "\n";
        $message .= "👥 " . trans("bot.unique users") . ": " . $count_unique_daily . "\n\n";
        
        $message .= "📆 " . trans("bot.weekly statistics") . ":\n";
        $message .= "📖 " . trans("bot.total ayah") . ": " . $count_weekly . " " . trans("bot.ayah") . "\n";
        $message .= "👥 " . trans("bot.unique users") . ": " . $count_unique_weekly . "\n\n";
        
        $message .= "📆 " . trans("bot.monthly statistics") . ":\n";
        $message .= "📖 " . trans("bot.total ayah") . ": " . $count_monthly . " " . trans("bot.ayah") . "\n";
        $message .= "👥 " . trans("bot.unique users") . ": " . $count_unique_monthly . "\n\n";
        
        $message .= "📆 " . trans("bot.yearly statistics") . ":\n";
        $message .= "📖 " . trans("bot.total ayah") . ": " . $count_yearly . " " . trans("bot.ayah") . "\n";
        $message .= "👥 " . trans("bot.unique users") . ": " . $count_unique_yearly . "\n\n";
        
        if ($postfix_local != "production") {
            $message .= "🔧 env: " . $postfix_local . "\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "💬 " . trans("bot.please help us to promote this bot to other people") . "\n\n";
        $message .= "اللهم صل علی محمد و آل محمد و عجل فرجهم\n\n";
        $message .= "استغفر الله ربی و اتوب الیه\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📝 " . trans("bot.to send your daily activity report please try it with this command") . "\n\n";
        $message .= "👇 👇 👇 👇 👇\n";
        $message .= ($type == 'bale' ? "/report [/report](send:/report)\n" : "/report\n");


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

    /**
     * محاسبه آمار روزانه (روز گذشته)
     * 
     * @return array
     */
    public function getDailyStatistics(): array
    {
        $cacheKey = 'daily_quran_stats_' . Carbon::yesterday()->format('Y-m-d');
        
        return Cache::remember($cacheKey, Carbon::now()->addHours(24), function () {
            $yesterday = Carbon::yesterday()->startOfDay();
            $today = Carbon::today()->startOfDay();
            
            $totalAyahs = BotLog::where('created_at', '>=', $yesterday)
                ->where('created_at', '<', $today)
                ->whereWebhookEndpointUri('webhook-quran-word')
                ->where('is_command', true)
                ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
                ->count();
            
            $uniqueUsers = BotLog::where('created_at', '>=', $yesterday)
                ->where('created_at', '<', $today)
                ->whereWebhookEndpointUri('webhook-quran-word')
                ->where('is_command', true)
                ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
                ->distinct('chat_id')
                ->count('chat_id');
            
            $completeRounds = floor($totalAyahs / 6236);
            
            return [
                'total_ayahs' => $totalAyahs,
                'unique_users' => $uniqueUsers,
                'complete_rounds' => $completeRounds,
            ];
        });
    }

    /**
     * محاسبه آمار دعوت‌شدگان یک کاربر
     * 
     * @param string $chatId
     * @return array
     */
    public function getReferralStatistics(string $chatId): array
    {
        $invitees = BotUsers::where('invited_by', $chatId)->pluck('chat_id');
        
        $totalInvitees = $invitees->count();
        
        // دعوت‌شدگان فعال در 7 روز گذشته
        $activeInviteesLast7Days = BotLog::whereIn('chat_id', $invitees)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('is_command', true)
            ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
            ->distinct('chat_id')
            ->count('chat_id');
        
        // تعداد آیات خوانده شده توسط دعوت‌شدگان در 7 روز گذشته
        $inviteesAyahsLast7Days = BotLog::whereIn('chat_id', $invitees)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('is_command', true)
            ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
            ->count();
        
        // تعداد آیات خوانده شده توسط خود کاربر در 7 روز گذشته
        $userAyahsLast7Days = BotLog::where('chat_id', $chatId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('is_command', true)
            ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
            ->count();
        
        return [
            'total_invitees' => $totalInvitees,
            'active_invitees_last_7_days' => $activeInviteesLast7Days,
            'invitees_ayahs_last_7_days' => $inviteesAyahsLast7Days,
            'user_ayahs_last_7_days' => $userAyahsLast7Days,
            'total_ayahs_last_7_days' => $inviteesAyahsLast7Days + $userAyahsLast7Days,
        ];
    }

    /**
     * ساخت پیام آماری روز گذشته
     * 
     * @return string
     */
    public function getDailyStatisticsMessage(): string
    {
        $stats = $this->getDailyStatistics();
        
        $message = trans("bot.yesterday with users you read rounds", [
            'users_count' => $stats['unique_users'],
            'rounds' => $stats['complete_rounds']
        ]);
        
        return $message;
    }

    /**
     * ساخت پیام آمار دعوت‌شدگان
     * 
     * @param string $chatId
     * @return string
     */
    public function getReferralStatisticsMessage(string $chatId): string
    {
        $stats = $this->getReferralStatistics($chatId);
        
        $message = trans("bot.referral statistics message", [
            'invitees_count' => $stats['active_invitees_last_7_days'],
            'total_ayahs' => $stats['total_ayahs_last_7_days']
        ]);
        
        return $message;
    }
}
