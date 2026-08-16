<?php

namespace App\Console;

use App\Console\Commands\PostDailyVerseToChannels;
use App\Console\Commands\PostMediaQueueToChannels;
use App\Console\Commands\RssReadTranslate;
use App\Console\Commands\RssToBot;
use App\Console\Commands\ScheduleBookPublishing;
use App\Console\Commands\ScheduleContentDelivery;
use App\Console\Commands\SendDailyQuranSuggestionToAdmins;
use App\Console\Commands\SendPrayerWeeklyReports;
use App\Console\Commands\TaskReminderCommand;
use App\Console\Commands\TestScheduleDailyIntoSlack;
use App\Console\Commands\UsersRankingCommand;
use App\Console\Commands\weatherWindCommand;
use App\Jobs\CheckWeatherAlertsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
//        $schedule->command(weatherWindCommand::class, ['speed=7'])->dailyAt("6:30"); //10:00 iran
        $schedule->command(TaskReminderCommand::class)->thursdays("20:27"); //23:57 iran
        $schedule->command(UsersRankingCommand::class)->dailyAt("20:29"); //23:59 iran
        $schedule->command(RssReadTranslate::class)->everyFifteenMinutes();
//        $schedule->command(UsersRankingCommand::class)->everyFiveMinutes();
        
        // ارسال گزارش‌های هفتگی نماز قضا با اینتروال‌های مختلف
        
        // هر 10 دقیقه (باکس کوچک - 5 ایمیل)
        $schedule->command(SendPrayerWeeklyReports::class, [
            '--batch-size=5',
            '--interval=10'
        ])
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // هر 30 دقیقه (باکس متوسط - 10 ایمیل)
        $schedule->command(SendPrayerWeeklyReports::class, [
            '--batch-size=10',
            '--interval=30'
        ])
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // هر ساعت (باکس بزرگ - 10 ایمیل)
        $schedule->command(SendPrayerWeeklyReports::class, [
            '--batch-size=10',
            '--interval=60'
        ])
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
        
        if (env('TestScheduleDailyIntoSlack'))
            $schedule->command(TestScheduleDailyIntoSlack::class)->dailyAt("07:00"); //10:30 iran
        
        // چک کردن weather alerts هر یک ساعت
        $schedule->job(new CheckWeatherAlertsJob)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
        
        // انتشار صفحات کتاب (بین 7 شب تا 12 شب)
        $schedule->command(ScheduleBookPublishing::class)
            ->hourly()
            ->between('19:00', '23:59')
            ->withoutOverlapping()
            ->onOneServer();

        // پیشنهاد روزانه قرآن به ادمین‌ها (هر ۲۴ ساعت)
        $schedule->command(SendDailyQuranSuggestionToAdmins::class)
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->onOneServer();

        // ارسال آیه/حدیث/نهج/شراب بهشتی به کانال‌های ادمین (اسلات‌های ۶ ساعته؛ هر config بر اساس posts_per_day در ۱/۲/۴ اسلات ارسال می‌کند)
        $schedule->command(PostDailyVerseToChannels::class, ['--slot=1'])->dailyAt('00:00')->withoutOverlapping()->onOneServer();
        $schedule->command(PostDailyVerseToChannels::class, ['--slot=2'])->dailyAt('06:00')->withoutOverlapping()->onOneServer();
        $schedule->command(PostDailyVerseToChannels::class, ['--slot=3'])->dailyAt('12:00')->withoutOverlapping()->onOneServer();
        $schedule->command(PostDailyVerseToChannels::class, ['--slot=4'])->dailyAt('18:00')->withoutOverlapping()->onOneServer();

        // ارسال نوبتی صف رسانه به کانال‌ها (روزانه یک بار)
        $schedule->command(PostMediaQueueToChannels::class)
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('observability:prune')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer();

        // ارسال ساعتی محتوای کتابخانه (پادکست) به کاربرانی که در صف هستند
        $schedule->command(ScheduleContentDelivery::class)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
