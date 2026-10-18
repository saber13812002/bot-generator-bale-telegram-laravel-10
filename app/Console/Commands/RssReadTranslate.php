<?php

namespace App\Console\Commands;

use App\Services\RssItemService;
use App\Services\RssPostItemTranslationToMessengerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RssReadTranslate extends Command
{
    /**
     * کلید قفل برای جلوگیری از اجرای همزمان (cron مستقل 19 دقیقه‌ای قدیمی
     * + entry 15 دقیقه‌ای scheduler می‌توانست همزمان اجرا شوند).
     * TTL 900 ثانیه به‌عنوان fallback در صورت crash فرایند.
     */
    private const LOCK_KEY = 'rss_read_translate_lock';

    private const LOCK_TTL_SECONDS = 900;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss_read_translate
        {--switch : فقط تحلیل فیدهای اخیر به‌جای همه فیدهای سرِ‌وقت}
        {--feed-id= : فقط پردازش یک فید مشخص (برای تست و دیباگ)}
        {--dry-run : بدون ذخیره و بدون ارسال؛ فقط بررسی وضعیت فیدها}
        {--limit= : حداکثر تعداد فید در این اجرا (پیش‌فرض بدون محدودیت)}
        {--no-translate : مرحله ترجمه/ارسال را اجرا نکند}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'خواندن RSS، تحلیل و ترجمه آیتم‌ها با لاگ ساختاریافته، قفل ضد همزمانی و exit code مناسب برای cron';

    /**
     * Execute the console command.
     *
     * @return int 0 = موفق/پارسیال، 1 = شکست کلی یا خطای command-level
     */
    public function handle(): int
    {
        $startedAt = microtime(true);
        $startedIso = now()->toIso8601String();

        $feedId = $this->option('feed-id') !== null ? (int) $this->option('feed-id') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $noTranslate = (bool) $this->option('no-translate');
        $switch = (bool) $this->option('switch');

        Log::info('RSS pipeline run started', [
            'command' => $this->signature,
            'started_at' => $startedIso,
            'options' => [
                'switch' => $switch,
                'feed_id' => $feedId,
                'limit' => $limit,
                'dry_run' => $dryRun,
                'no_translate' => $noTranslate,
            ],
        ]);

        $this->info($dryRun
            ? 'RSS run (dry-run: no save, no send)'
            : 'RSS run started');

        // ---- قفل ضد اجرای همزمان ----
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (!$lock->get()) {
            Log::warning('RSS pipeline run skipped: another run is in progress (lock held)');
            $this->warn('Another RSS run is already in progress; skipped.');

            return self::SUCCESS; // skip = نه خطا، نه موفقیت واقعی؛ برای cron 0 کافی است
        }

        try {
            // ---- مرحله 1: دریافت و ذخیره فیدها ----
            $summary = RssItemService::run($switch, $limit, $feedId, $dryRun);

            foreach ($summary['failed_feeds'] as $failed) {
                $this->warn(sprintf(
                    '  [FAILED] feed #%d — %s (%s)',
                    $failed['id'],
                    $failed['url'],
                    $failed['error']
                ));
            }

            $this->info(sprintf(
                'Fetch summary: %d ok, %d failed, %d new saved (of %d targeted)',
                $summary['ok'],
                $summary['failed'],
                $summary['new_saved'],
                $summary['total']
            ));

            // ---- مرحله 2: ترجمه و ورود به نوبت انتشار ----
            $translation = ['processed' => 0, 'skipped' => 0];

            if (!$dryRun && !$noTranslate) {
                $translation = RssPostItemTranslationToMessengerService::run(
                    $feedId !== null ? 20 : 5,
                    $feedId
                );

                $this->info(sprintf(
                    'Translation summary: %d processed, %d skipped',
                    $translation['processed'],
                    $translation['skipped']
                ));
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // ---- تعیین exit code ----
            $commandLevelFailure = $feedId !== null && $summary['total'] === 0;
            $allTargetedFailed = $summary['total'] > 0 && $summary['ok'] === 0;
            $exitCode = ($commandLevelFailure || $allTargetedFailed) ? 1 : 0;

            Log::info('RSS pipeline run finished', [
                'started_at' => $startedIso,
                'duration_ms' => $durationMs,
                'exit_code' => $exitCode,
                'dry_run' => $dryRun,
                'feed_total' => $summary['total'],
                'feed_ok' => $summary['ok'],
                'feed_failed' => $summary['failed'],
                'new_saved' => $summary['new_saved'],
                'failed_feed_ids' => array_column($summary['failed_feeds'], 'id'),
                'translated' => $translation['processed'],
                'translation_skipped' => $translation['skipped'],
            ]);

            $this->info(sprintf(
                'RSS run finished in %d ms (exit %d)',
                $durationMs,
                $exitCode
            ));

            return $exitCode;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('RSS pipeline run crashed', [
                'started_at' => $startedIso,
                'duration_ms' => $durationMs,
                'error' => \App\Helpers\TokenHelper::redactTokens($e->getMessage()),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('RSS run crashed: ' . $e->getMessage());

            return 1;
        } finally {
            $lock->release();
        }
    }
}
