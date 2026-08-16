<?php

namespace App\Console\Commands;

use App\Services\BotObservabilityService;
use Illuminate\Console\Command;

class HealthReportCommand extends Command
{
    protected $signature = 'observability:health-report
                            {--pretty : چاپ JSON با فاصله}';

    protected $description = 'گزارش JSON سلامت ربات‌ها (یوزر، اینباند، اوتباند) بدون ارسال پیام';

    public function handle(BotObservabilityService $observability): int
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($observability->healthPayload(), $flags) ?: '{}');

        return self::SUCCESS;
    }
}
