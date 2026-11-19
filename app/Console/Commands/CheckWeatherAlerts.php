<?php

namespace App\Console\Commands;

use App\Jobs\CheckWeatherAlertsJob;
use Illuminate\Console\Command;

class CheckWeatherAlerts extends Command
{
    protected $signature = 'weather:check-alerts';

    protected $description = 'بررسی هشدارهای هواشناسی (جایگزین قابل‌cron برای $schedule->job(CheckWeatherAlertsJob))';

    public function handle(): int
    {
        // اجرا هم‌زمان (inline) در همین پروسه — بدون وابستگی به queue:work یا schedule:run
        CheckWeatherAlertsJob::dispatchSync();

        $this->info('weather:check-alerts done.');
        return self::SUCCESS;
    }
}
