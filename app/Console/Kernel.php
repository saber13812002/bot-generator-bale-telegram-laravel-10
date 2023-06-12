<?php

namespace App\Console;

use App\Console\Commands\TaskReminderCommand;
use App\Console\Commands\UsersRankingCommand;
use App\Console\Commands\weatherWindCommand;
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
        $schedule->command(weatherWindCommand::class, ['speed=7'])->dailyAt("6:30"); //10:00 iran
        $schedule->command(TaskReminderCommand::class)->thursdays("20:28"); //23:58 iran
//        $schedule->command(weatherWindCommand::class, ['speed=12'])->everyFiveMinutes();

        $schedule->command(UsersRankingCommand::class)->dailyAt("20:29"); //23:59 iran

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
