<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PipelineHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pipeline:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ثبت تپش قلب scheduler: اگر این هر دقیقه اجرا نشود، pipeline:status خرابی cron/scheduler را تشخیص می‌دهد';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // TTL 10 دقیقه: تا 10 دقیقه بدون heartbeat یعنی scheduler/cron قطع است
        Cache::put('pipeline:heartbeat:ts', time(), 600);
        Cache::put('pipeline:heartbeat:at', now()->toIso8601String(), 600);

        return self::SUCCESS;
    }
}
