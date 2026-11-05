<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class StorageCleanupCommand extends Command
{
    /**
     * پاکسازی فضای ذخیره‌سازی (دریافت quota روی هاست‌های اشتراکی):
     * - حذف ورودی‌های منقضی‌شده‌ی cache با درایو file
     * - حذف sessionهای کهنه‌ی file
     * - چرخش (rotate) laravel.log در صورت عبور از سقف حجم + حذف لاگ‌های چرخ‌خورده‌ی قدیمی
     *
     * بدون withoutOverlapping/onOneServer برنامه‌ریزی می‌شود چون آن دو از cache
     * استفاده می‌کنند و وقتی دایس پر باشد، قفل خودِ آن‌ها خطا می‌دهد.
     */
    protected $signature = 'storage:cleanup
                            {--cache-max-mb= : (فرمانی) سقف اطلاعاتی برای گزارش}
                            {--log-max-mb=50 : چرخش laravel.log هنگام عبور از این حجم (MB)}
                            {--log-backups=5 : تعداد نسخه‌های نگه‌داشته‌شده از laravel.log}
                            {--log-days=30 : حذف لاگ‌های چرخ‌خورده/روزانه قدیمی‌تر از این روزها}
                            {--session-days=2 : حذف sessionهای file قدیمی‌تر از این روزها}
                            {--dry-run : فقط گزارش دهد، چیزی پاک نکند}';

    protected $description = 'پاکسازی cache فایل، sessionهای کهنه و چرخش لاگ‌ها برای آزادسازی quota دایس';

    public function handle(Filesystem $files): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $logMaxBytes = ((int) $this->option('log-max-mb') ?: 50) * 1024 * 1024;
        $logBackups = max(1, (int) $this->option('log-backups') ?: 5);
        $logDays = max(1, (int) $this->option('log-days') ?: 30);
        $sessionDays = max(1, (int) $this->option('session-days') ?: 2);

        if ($dryRun) {
            $this->warn('Dry-run mode: no files will be modified.');
        }

        $this->pruneFileCache($files, $dryRun);
        $this->pruneFileSessions($files, $sessionDays, $dryRun);
        $this->rotateMainLog($files, $logMaxBytes, $logBackups, $logDays, $dryRun);

        $this->info('storage:cleanup finished.');

        return self::SUCCESS;
    }

    /**
     * حذف ورودی‌های منقضی‌شده از همه storeهای cache که درایو file دارند.
     */
    protected function pruneFileCache(Filesystem $files, bool $dryRun): void
    {
        foreach ((array) config('cache.stores', []) as $name => $store) {
            if (($store['driver'] ?? null) !== 'file') {
                continue;
            }

            try {
                $storeInstance = app('cache')->store($name);
            } catch (\Throwable $e) {
                $this->warn("Cache store [{$name}] unavailable: {$e->getMessage()}");
                continue;
            }

            if (! method_exists($storeInstance, 'pruneExpired')) {
                continue;
            }

            try {
                if ($dryRun) {
                    $this->info("[dry-run] Would prune expired entries in cache store [{$name}].");
                } else {
                    $storeInstance->pruneExpired();
                    $this->info("Pruned expired entries in cache store [{$name}].");
                }
            } catch (\Throwable $e) {
                // وقتی quota دایس پر باشد خودِ نوشتن cache هم ممکن است خطا بدهد؛
                // بقیه‌ی گام‌ها باید اجرا شوند.
                $this->warn("Prune failed for cache store [{$name}]: {$e->getMessage()}");
            }
        }
    }

    /**
     * حذف فایل‌های session کهنه (فقط وقتی درایو session = file است).
     */
    protected function pruneFileSessions(Filesystem $files, int $days, bool $dryRun): void
    {
        if (config('session.driver') !== 'file') {
            return;
        }

        $path = storage_path('framework/sessions');
        if (! $files->isDirectory($path)) {
            return;
        }

        $cutoff = time() - $days * 86400;
        $count = 0;

        foreach ($files->files($path) as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff) {
                $count++;
                if (! $dryRun) {
                    $files->delete($file->getPathname());
                }
            }
        }

        $this->info(($dryRun ? '[dry-run] Would delete ' : 'Deleted ') . "{$count} stale session files (older than {$days} days).");
    }

    /**
     * چرخش laravel.log + حذف لاگ‌های چرخ‌خورده‌ی قدیمی.
     *
     * از روش copytruncate استفاده می‌شود: کپی به نسخه‌ی بعدی و سپس خالی‌سازی
     * فایل اصلی (بدون rename، تا مصرف دایس در لحظه‌ی پر شدن quota دو برابر نشود
     * و فرآیندهای در حال نوشتن لاگ هم قفل inode جدیدی نداشته باشند).
     */
    protected function rotateMainLog(Filesystem $files, int $maxBytes, int $backups, int $logDays, bool $dryRun): void
    {
        $logFile = storage_path('logs/laravel.log');
        $logsDir = storage_path('logs');

        if ($files->exists($logFile) && $files->size($logFile) > $maxBytes) {
            $sizeMb = round($files->size($logFile) / 1024 / 1024, 1);
            $limitMb = round($maxBytes / 1024 / 1024, 1);

            if ($dryRun) {
                $this->info("[dry-run] laravel.log ({$sizeMb} MB) exceeds {$limitMb} MB — would rotate (keep {$backups} backups).");
            } else {
                try {
                    // شیفت نسخه‌های قبلی: .4 -> .5 ، .3 -> .4 ... .1 -> .2
                    for ($i = $backups - 1; $i >= 1; $i--) {
                        $from = "{$logFile}.{$i}";
                        $to   = "{$logFile}.{" . ($i + 1) . '}';
                        if ($files->exists($from)) {
                            $files->move($from, $to);
                        }
                    }

                    // حذف نسخه‌ی نهایی که از محدوده‌ی نگه‌داشت بیرون رفته
                    $overflowIndex = $backups + 1;
                    $overflow = "{$logFile}.{$overflowIndex}";
                    if ($files->exists($overflow)) {
                        $files->delete($overflow);
                    }

                    // copytruncate: کپی و سپس خالی‌سازی
                    if ($files->exists($logFile)) {
                        $files->copy($logFile, "{$logFile}.1");
                        $files->put($logFile, '');
                    }

                    $this->info("Rotated laravel.log (was {$sizeMb} MB).");
                } catch (\Throwable $e) {
                    $this->warn("Log rotation failed: {$e->getMessage()}");
                }
            }
        }

        // حذف لاگ‌های چرخ‌خورده/روزانه (laravel-*.log) قدیمی‌تر از logDays
        if ($files->isDirectory($logsDir)) {
            $cutoff = time() - $logDays * 86400;
            $count = 0;

            foreach ($files->files($logsDir) as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $basename = $file->getBasename();
                // فقط لاگ‌های Laravel: laravel.log.N و laravel-YYYY-MM-DD.log
                if (! preg_match('/^laravel(\.log(\.\d+)?|-\d{4}-\d{2}-\d{2}\.log)$/', $basename)) {
                    continue;
                }

                if ($file->getMTime() < $cutoff) {
                    $count++;
                    if (! $dryRun) {
                        $files->delete($file->getPathname());
                    }
                }
            }

            if ($count > 0) {
                $this->info(($dryRun ? '[dry-run] Would delete ' : 'Deleted ') . "{$count} old rotated log files (older than {$logDays} days).");
            }
        }
    }
}
