<?php

namespace App\Console\Commands;

use App\Models\RssItem;
use App\Helpers\TokenHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RssFeedsHealth extends Command
{
    /**
     * حداقل خطاهای متوالی (probe یا fetch) برای غیرفعال‌سازی خودکار.
     * قبل از این تغییر، یک probe خراب (مثلاً timeout لحظه‌ای) فید را برای همیشه
     * غیرفعال می‌کرد — همان اتفاقی که ۲۰۲۶-۱۰-۱۲ برای ۴۹ فید افتاد.
     */
    public const DISABLE_THRESHOLD_TRANSIENT = 3;

    /** برای پاسخ HTML به‌جای XML (paywall/soft-403) یک بار شانس بیشتر می‌دهیم */
    public const DISABLE_THRESHOLD_HTML = 2;

    protected $signature = 'rss:feeds-health
        {--disable-dead : فیدهای مرده‌ی تأییدشده (بالای آستانه خطا) را غیرفعال کند}
        {--enable-healthy : فیدهای غیرفعالِ سالم در این اجرا را دوباره فعال کند (به‌صورت تکی و کنترل‌شده)}
        {--enable-all : [ابزار اضطراری دستی] همه فیدهای غیرفعال را فعال کند — بدون بررسی سلامت}
        {--dry-run : فقط گزارش نشان بدهد، هیچ چیزی را تغییر ندهد}
        {--only-disabled : فقط فیدهای غیرفعال را بررسی کند (برای بازتست بعد از حادثه)}
        {--limit=100 : حداکثر تعداد فیدی که بررسی می‌شود}';

    protected $description = 'بررسی سلامت فیدهای RSS با retry برای خطاهای گذرا، آستانه‌ی خطا برای غیرفعال‌سازی، و بازفعال‌سازی کنترل‌شده‌ی فیدهای سالم';

    public function handle(): int
    {
        // ابزار اضطراری دستی: بدون بررسی، همه را فعال می‌کند (فقط دستی، خارج از schedule)
        if ($this->option('enable-all')) {
            $updated = RssItem::query()->where('is_active', 0)->update([
                'is_active' => 1,
                'consecutive_failures' => 0,
                'last_error' => null,
                'last_synced_at' => now()->subDay(),
            ]);
            $this->warn("EMERGENCY MANUAL: Enabled {$updated} disabled feed(s) without health verification.");
            return self::SUCCESS;
        }

        $onlyDisabled = (bool) $this->option('only-disabled');

        $query = RssItem::query()->orderBy('id');
        if ($onlyDisabled) {
            $query->where('is_active', 0);
        }

        $feeds = $query->limit((int) $this->option('limit'))->get();

        if ($feeds->isEmpty()) {
            $this->warn('No RSS feeds to check.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $disableDead = (bool) $this->option('disable-dead');
        $enableHealthy = (bool) $this->option('enable-healthy');

        $this->info(sprintf(
            'Checking %d feed(s) — retry on transient errors, disable threshold: %d (transient) / %d (HTML) / 1 (invalid URL)%s',
            $feeds->count(),
            self::DISABLE_THRESHOLD_TRANSIENT,
            self::DISABLE_THRESHOLD_HTML,
            $dryRun ? ' [DRY RUN]' : ''
        ));

        $rows = [];
        $okCount = 0;
        $newlyDisabled = 0;
        $reEnabled = 0;
        $stillDeadIds = [];

        foreach ($feeds as $feed) {
            $probe = $this->probeWithRetry((string) $feed->url);
            $verdict = $probe['verdict'];

            $wasActive = (int) $feed->is_active === 1;
            $failuresBefore = (int) ($feed->consecutive_failures ?? 0);
            $action = 'ok';

            if ($verdict === 'ok') {
                $okCount++;

                if (!$dryRun && $failuresBefore > 0) {
                    // probe سالم → شمارنده خطا صفر می‌شود (reset بعد از recover)
                    $feed->consecutive_failures = 0;
                    $feed->last_error = null;
                    $feed->save();
                }

                if (!$wasActive && $enableHealthy && !$dryRun) {
                    // بازفعال‌سازی کنترل‌شده: فقط اگر در همین حالا probe سالم بود
                    $feed->is_active = 1;
                    $feed->consecutive_failures = 0;
                    $feed->last_error = null;
                    $feed->last_synced_at = now()->subDay(); // بلافاصله سرِ‌وقت دریافت شود
                    $feed->save();
                    $reEnabled++;
                    $action = 'RE-ENABLED';

                    Log::info('rss:feeds-health: disabled feed re-enabled after healthy probe', [
                        'rss_item_id' => $feed->id,
                    ]);
                } elseif (!$wasActive && $enableHealthy && $dryRun) {
                    $action = 'would re-enable';
                }
            } else {
                $failuresAfter = $failuresBefore + 1;
                $shouldDisable = $this->shouldDisable($verdict, $failuresAfter);

                if (!$dryRun) {
                    // probe خراب → شمارنده خطا +۱ (مشترک با شمارنده fetch در RssItemService)
                    $feed->consecutive_failures = $failuresAfter;
                    $feed->last_error = Str::limit('rss:feeds-health probe: ' . $verdict, 250);

                    if ($shouldDisable && $wasActive) {
                        $feed->is_active = 0;
                        // last_synced_at به الان تا بازتست خودکار ۷ روز دیگر باشد (نه زودتر)
                        $feed->last_synced_at = now();
                        $action = 'DISABLED';
                        $newlyDisabled++;

                        Log::warning('rss:feeds-health: feed disabled after repeated failures', [
                            'rss_item_id' => $feed->id,
                            'verdict' => $verdict,
                            'failures' => $failuresAfter,
                        ]);
                    }

                    $feed->save();
                } elseif ($shouldDisable && $wasActive) {
                    $action = 'would disable';
                }

                $stillDeadIds[] = (int) $feed->id;
            }

            $stateLabel = $wasActive
                ? (($verdict !== 'ok' && $shouldDisable && !$dryRun) ? 'active→disabled' : 'active')
                : ((!$wasActive && $verdict === 'ok' && $enableHealthy && !$dryRun) ? 'disabled→active' : 'disabled');

            $rows[] = [
                $feed->id,
                $stateLabel,
                $probe['code'],
                $verdict,
                $action,
                $failuresBefore,
                Str::limit(TokenHelper::redactTokens((string) $feed->url), 45),
            ];
        }

        $this->table(['ID', 'State', 'HTTP', 'Verdict', 'Action', 'Fails', 'URL'], $rows);

        $this->line(sprintf(
            'OK: %d | Dead: %d | Newly disabled: %d | Re-enabled: %d | Total: %d',
            $okCount,
            count($stillDeadIds),
            $newlyDisabled,
            $reEnabled,
            $feeds->count()
        ));

        if ($dryRun) {
            $this->warn('Dry run: no changes were applied.');
        }

        if (!$dryRun) {
            Log::info('rss:feeds-health: run finished', [
                'checked' => $feeds->count(),
                'ok' => $okCount,
                'dead_ids' => $stillDeadIds,
                'newly_disabled' => $newlyDisabled,
                're_enabled' => $reEnabled,
            ]);
        }

        // اگر فید مرده‌ی تأییدشده وجود دارد → exit 1 تا در لاگ cron دیده شود
        return empty($stillDeadIds) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * آیا فید را غیرفعال کنیم؟ (قوانین P2)
     *
     * - invalid_url: ساختاری/داده‌ای — بلافاصله (هیچ وقت کار نمی‌کند)
     * - html_not_xml (paywall/soft-403): بعد از DISABLE_THRESHOLD_HTML خطای متوالی
     * - connection_error / http_error / invalid_xml (گذرا): بعد از DISABLE_THRESHOLD_TRANSIENT
     *
     * یک خطای لحظه‌ای دیگر هرگز فید را برای همیشه غیرفعال نمی‌کند.
     */
    private function shouldDisable(string $verdict, int $currentFailures): bool
    {
        return match ($verdict) {
            'invalid_url' => true,
            'html_not_xml' => $currentFailures >= self::DISABLE_THRESHOLD_HTML,
            'connection_error', 'http_error', 'invalid_xml' => $currentFailures >= self::DISABLE_THRESHOLD_TRANSIENT,
            default => false,
        };
    }

    /**
     * دریافت زنده فید با یک retry برای خطاهای گذرا (timeout/5xx).
     * خطاها: ok | invalid_url | connection_error | http_error | html_not_xml | invalid_xml
     *
     * @return array{code: string, verdict: string, retried: bool}
     */
    protected function probeWithRetry(string $url): array
    {
        $first = $this->probe($url);

        // retry فقط برای خطاهای گذرا و فقط یک‌بار؛ 4xx (به‌جز 408/429) retry نمی‌شود
        if (in_array($first['verdict'], ['connection_error', 'http_error', 'invalid_xml'], true)) {
            $isTransientHttp = $first['verdict'] === 'http_error'
                && in_array($first['code'], ['408', '429', '500', '502', '503', '504'], true);

            if ($first['verdict'] === 'connection_error' || $isTransientHttp) {
                usleep(1_500_000); // 1.5 ثانیه
                $second = $this->probe($url);

                if ($second['verdict'] === 'ok') {
                    return ['code' => $second['code'], 'verdict' => 'ok', 'retried' => true];
                }

                // اگر retry هم شد، verdict نهایی (با count خطا توسط caller)
                return ['code' => $second['code'], 'verdict' => $second['verdict'], 'retried' => true];
            }
        }

        return ['code' => $first['code'], 'verdict' => $first['verdict'], 'retried' => false];
    }

    /**
     * دریافت زنده فید و دسته‌بندی ساده نتیجه.
     *
     * @return array{code: string, verdict: string}
     */
    protected function probe(string $url): array
    {
        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
            return ['code' => '---', 'verdict' => 'invalid_url'];
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable $e) {
            return ['code' => '000', 'verdict' => 'connection_error'];
        }

        $status = (string) $response->status();

        if (!$response->successful()) {
            return ['code' => $status, 'verdict' => 'http_error'];
        }

        $head = substr(ltrim($response->body()), 0, 500);
        if (stripos($head, '<!doctype html') !== false || stripos($head, '<html') !== false) {
            return ['code' => $status, 'verdict' => 'html_not_xml'];
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            return ['code' => $status, 'verdict' => 'invalid_xml'];
        }

        return ['code' => $status, 'verdict' => 'ok'];
    }
}
