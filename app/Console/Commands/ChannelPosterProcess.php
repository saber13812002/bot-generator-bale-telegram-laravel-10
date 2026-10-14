<?php

namespace App\Console\Commands;

use App\Jobs\ProcessChannelPosterQueueJob;
use Illuminate\Console\Command;

class ChannelPosterProcess extends Command
{
    protected $signature = 'channel-poster:process';

    protected $description = 'پردازش صف channel_poster_queue (لغو آیتم‌های قدیمی + انتشار ready). جایگزین قابل‌cron برای $schedule->job(ProcessChannelPosterQueueJob)';

    public function handle(): int
    {
        // اجرا هم‌زمان (inline) در همین پروسه — بدون وابستگی به queue:work یا schedule:run
        ProcessChannelPosterQueueJob::dispatchSync();

        $this->info('channel-poster:process done.');
        return self::SUCCESS;
    }
}
