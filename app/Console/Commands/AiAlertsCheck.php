<?php

namespace App\Console\Commands;

use App\Services\Ai\AiAlertService;
use Illuminate\Console\Command;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — بررسی دوره‌ای آستانه‌های production (brief §7).
 *
 * هر ۵ دقیقه (schedule) آستانه‌ها را بررسی می‌کند؛ alert فعال (با dedup)
 * از مسیر امن admin channel ارسال می‌شود. --dry-run فقط detect می‌کند.
 * هرگز exit ≠ 0 نمی‌دهد (هشدار شکسته، cron را به خطر نمی‌اندازد).
 */
class AiAlertsCheck extends Command
{
    protected $signature = 'ai:alerts-check {--dry-run : فقط detect — ارسال/notify نمی‌شود}';

    protected $description = 'بررسی آستانه‌های هشدار AI (queue backlog، dead letter، error rate) با dedup';

    public function handle(): int
    {
        $alerts = AiAlertService::runWithDedup($this->option('dry-run'));

        if (empty($alerts)) {
            $this->line('✅ AI alerts: آستانه‌ای فعال نیست.');
            return 0;
        }

        foreach ($alerts as $alert) {
            $this->line($alert['message']);
        }

        $this->line(count($alerts) . ' alert فعال ارسال شد (dedup cooldown: '
            . config('ai.alerts.cooldown_seconds', 3600) . 's).');

        return 0;
    }
}
