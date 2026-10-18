<?php

namespace App\Console\Commands;

use App\Models\ChannelPosterQueue;
use App\Models\RssItem;
use App\Models\RssPostItemTranslationQueue;
use App\Services\AiProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * pipeline:watch — پایش خط لوله‌ی محتوا با هشدار ددوپلیکیته‌شده و rate-limit شده (P5).
 *
 * هشدارها (هر کدام با کلید dedupe در Cache — تکرار تا ۶ ساعت بعد):
 * - SCHEDULER-STALE : heartbeat بیش از 10 دقیقه قدیمی (cron/schedule:run مرده)
 * - NO-PUBLISH      : هیچ انتشار sent_at شده‌ای در 24 ساعت اخیر (درحالی‌که فید فعال داریم)
 * - RSS-ERRORS      : رکورد error در صف RSS بدون هیچ sent موفق در 24h اخیر (شکست انتشار)
 * - CHANNEL-FAILED  : رکورد failed در صف channel-poster بدون published در 24h اخیر
 *
 * alert dedup: هر کلید هشدار بعد از ارسال 6 ساعت Suppress می‌شود تا ادمین‌ها اسپم نشوند.
 * خروجی exit 0 (حتی با هشدار) تا cron log سیاه نشود؛ وضعیت واقعی را pipeline:status می‌گوید.
 */
class PipelineWatch extends Command
{
    protected $signature = 'pipeline:watch {--dry-run : هشدارها را فقط در ترمینال بنویس، ارسال نکن}';

    protected $description = 'Watch the content pipeline and send deduplicated, rate-limited alerts to admins (scheduler heartbeat, no-publish, error buildup)';

    protected const HEARTBEAT_STALE_SECONDS = 600;
    protected const PUBLISH_WINDOW_HOURS = 24;
    protected const ALERT_SUPPRESS_SECONDS = 6 * 3600;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $alerts = [];

        $alerts = array_merge($alerts, $this->checkSchedulerHeartbeat());
        $alerts = array_merge($alerts, $this->checkNoPublish());
        $alerts = array_merge($alerts, $this->checkRssErrorBuildup());
        $alerts = array_merge($alerts, $this->checkChannelPosterFailures());

        if (empty($alerts)) {
            Log::info('pipeline:watch: all green, no alerts');
            $this->line('pipeline:watch — all green, no alerts.');

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            Log::warning('pipeline:watch: alert', $alert);

            if ($dryRun) {
                $this->warn('[DRY-RUN] would alert: ' . $alert['key'] . ' — ' . $alert['message']);

                continue;
            }

            try {
                AiProviderService::notifyAllAdmins($alert['message']);
            } catch (\Throwable $e) {
                Log::error('pipeline:watch: failed to send alert', [
                    'key' => $alert['key'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{key: string, message: string}>
     */
    protected function checkSchedulerHeartbeat(): array
    {
        $ts = (int) (Cache::get('pipeline:heartbeat:ts') ?? 0);

        if ($ts > 0 && (now()->getTimestamp() - $ts) <= self::HEARTBEAT_STALE_SECONDS) {
            return [];
        }

        $age = $ts > 0 ? now()->getTimestamp() - $ts : null;
        $key = 'pipeline:alert:scheduler_stale';
        if (!$this->shouldAlert($key)) {
            return [];
        }

        return [[
            'key' => $key,
            'message' => '🔴 Pipeline: SCHEDULER STALE'
                . "\nHeartbeat missing for "
                . ($age !== null ? floor($age / 60) . ' minutes' : 'unknown (never seen)')
                . '. cron/schedule:run may be down.'
                . "\nCheck: cPanel cron → php artisan schedule:run"
                . "\nTime: " . now()->toIso8601String(),
        ]];
    }

    /**
     * @return array<int, array{key: string, message: string}>
     */
    protected function checkNoPublish(): array
    {
        $activeFeeds = RssItem::where('is_active', true)->count();
        if ($activeFeeds === 0) {
            return [];
        }

        $cutoff = now()->subHours(self::PUBLISH_WINDOW_HOURS);
        $lastSent = RssPostItemTranslationQueue::whereNotNull('sent_at')
            ->where('sent_at', '>=', $cutoff)
            ->count();

        if ($lastSent > 0) {
            return [];
        }

        $key = 'pipeline:alert:no_publish';
        if (!$this->shouldAlert($key)) {
            return [];
        }

        return [[
            'key' => $key,
            'message' => '🔴 Pipeline: NO PUBLISH in ' . self::PUBLISH_WINDOW_HOURS . 'h'
                . "\n" . $activeFeeds . ' active feed(s) but zero sent_at in the last ' . self::PUBLISH_WINDOW_HOURS . ' hours.'
                . "\nDiagnose: php artisan pipeline:status"
                . "\nTime: " . now()->toIso8601String(),
        ]];
    }

    /**
     * @return array<int, array{key: string, message: string}>
     */
    protected function checkRssErrorBuildup(): array
    {
        $cutoff = now()->subHours(self::PUBLISH_WINDOW_HOURS);
        $sentRecently = RssPostItemTranslationQueue::where('status', 'sent')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $cutoff)
            ->count();

        // اگر در 24h اخیر اصلاً انتشاری نشده، هشدار no-publish کافی است
        if ($sentRecently > 0) {
            return [];
        }

        $errorCount = RssPostItemTranslationQueue::where('status', 'error')->count();
        if ($errorCount < 3) {
            return [];
        }

        $key = 'pipeline:alert:rss_errors';
        if (!$this->shouldAlert($key)) {
            return [];
        }

        $lastError = RssPostItemTranslationQueue::where('status', 'error')
            ->whereNotNull('last_error')
            ->orderByDesc('id')
            ->first(['id', 'created_at', 'last_error']);

        return [[
            'key' => $key,
            'message' => '🔴 Pipeline: RSS ERROR BUILDUP'
                . "\n{$errorCount} error record(s) and no successful publish in " . self::PUBLISH_WINDOW_HOURS . 'h.'
                . ($lastError ? "\nLast error (record #{$lastError->id}): " . \Illuminate\Support\Str::limit((string) $lastError->last_error, 150) : '')
                . "\nDiagnose: php artisan rss:recover (dry-run) / php artisan pipeline:status"
                . "\nTime: " . now()->toIso8601String(),
        ]];
    }

    /**
     * @return array<int, array{key: string, message: string}>
     */
    protected function checkChannelPosterFailures(): array
    {
        $cutoff = now()->subHours(self::PUBLISH_WINDOW_HOURS);
        $publishedRecently = ChannelPosterQueue::where('status', ChannelPosterQueue::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $cutoff)
            ->count();

        if ($publishedRecently > 0) {
            return [];
        }

        $failedCount = ChannelPosterQueue::where('status', ChannelPosterQueue::STATUS_FAILED)->count();
        $pendingCount = ChannelPosterQueue::where('status', ChannelPosterQueue::STATUS_PENDING)->count();
        if ($failedCount < 1 && $pendingCount === 0) {
            return [];
        }

        $key = 'pipeline:alert:channel_poster';
        if (!$this->shouldAlert($key)) {
            return [];
        }

        return [[
            'key' => $key,
            'message' => '🔴 Pipeline: CHANNEL-POSTER STUCK'
                . "\nNo published channel-poster items in " . self::PUBLISH_WINDOW_HOURS . 'h. failed=' . $failedCount . ' pending=' . $pendingCount
                . "\nDiagnose: php artisan channel-poster:watchdog / php artisan pipeline:status"
                . "\nTime: " . now()->toIso8601String(),
        ]];
    }

    /**
     * dedupe + rate-limit هر هشدار: کلید Cache تا 6 ساعت suppress می‌ماند.
     */
    protected function shouldAlert(string $key): bool
    {
        $already = Cache::get($key);
        if ($already) {
            return false;
        }

        Cache::put($key, now()->toIso8601String(), now()->addSeconds(self::ALERT_SUPPRESS_SECONDS));

        return true;
    }
}
