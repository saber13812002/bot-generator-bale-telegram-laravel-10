<?php

namespace App\Console\Commands;

use App\Models\AppLogEntry;
use App\Models\BotHealthEvent;
use Illuminate\Console\Command;

class PruneObservabilityCommand extends Command
{
    protected $signature = 'observability:prune
                            {--log-max= : سقف ردیف‌های app_log_entries}
                            {--health-days= : نگه‌داشت رویدادهای سلامت به روز}';

    protected $description = 'حذف لاگ‌های اضافی app_log_entries و رویدادهای سلامت قدیمی';

    public function handle(): int
    {
        $maxRows = (int) ($this->option('log-max') ?: config('observability.log_max_rows', 5000));
        $days = (int) ($this->option('health-days') ?: config('observability.health_retention_days', 30));

        $deletedLogs = AppLogEntry::pruneExcess($maxRows);
        $deletedHealth = BotHealthEvent::pruneOlderThan($days);

        $this->info("app_log_entries pruned: {$deletedLogs} (keep last {$maxRows})");
        $this->info("bot_health_events pruned: {$deletedHealth} (older than {$days} days)");

        return self::SUCCESS;
    }
}
