<?php

namespace App\Console\Commands;

use App\Models\RssItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RssFeedsHealth extends Command
{
    protected $signature = 'rss:feeds-health
        {--disable-dead : فیدهای مردده را در دیتابیس غیرفعال کند (is_active=0)}
        {--enable-all : همه فیدها را دوباره فعال کند و شمارنده خطا را صفر کند}
        {--dry-run : فقط گزارش نشان بدهد، هیچ چیزی را تغییر ندهد}
        {--limit=100 : حداکثر تعداد فیدی که بررسی می‌شود}';

    protected $description = 'بررسی سلامت فیدهای RSS: هر فید را زنده دریافت می‌کند و نتیجه را نشان می‌دهد. می‌تواند فیدهای مرده (402/403/404/timeout/HTML) را در دیتابیس غیرفعال کند';

    public function handle(): int
    {
        if ($this->option('enable-all')) {
            $updated = RssItem::query()->where('is_active', 0)->update([
                'is_active' => 1,
                'consecutive_failures' => 0,
                'last_error' => null,
                // یک روز عقب می‌بریم تا فید در همان دقیقه بعد در نوبت دریافت قرار بگیرد
                'last_synced_at' => now()->subDay(),
            ]);
            $this->info("Enabled {$updated} disabled feed(s).");
            return self::SUCCESS;
        }

        $feeds = RssItem::query()->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($feeds->isEmpty()) {
            $this->warn('No RSS feeds found in the database.');
            return self::SUCCESS;
        }

        $this->info("Checking {$feeds->count()} feed(s) (timeout 15s each)...");

        $rows = [];
        $deadFeeds = [];
        $okCount = 0;

        foreach ($feeds as $feed) {
            $probe = $this->probe((string) $feed->url);
            $verdict = $probe['verdict'];
            $isDead = in_array($verdict, ['invalid_url', 'connection_error', 'http_error', 'html_not_xml'], true);

            $rows[] = [
                $feed->id,
                $feed->is_active ? 'active' : 'disabled',
                $probe['code'],
                $verdict,
                Str::limit((string) $feed->url, 55),
            ];

            if ($isDead) {
                $deadFeeds[$feed->id] = ['feed' => $feed, 'verdict' => $verdict];
            } else {
                $okCount++;
            }
        }

        $this->table(['ID', 'State', 'HTTP', 'Verdict', 'URL'], $rows);
        $this->line("OK: {$okCount} | Dead: " . count($deadFeeds) . ' | Total: ' . $feeds->count());

        if (empty($deadFeeds)) {
            $this->info('All feeds are healthy.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: dead feeds were NOT disabled. Run with --disable-dead to apply.');
            return self::SUCCESS;
        }

        if (!$this->option('disable-dead')) {
            $this->warn('Dead feeds were not disabled (no --disable-dead flag).');
            return self::SUCCESS;
        }

        $disabled = 0;
        foreach ($deadFeeds as $entry) {
            /** @var RssItem $feed */
            $feed = $entry['feed'];

            if ((int) $feed->is_active !== 1) {
                continue;
            }

            $feed->is_active = 0;
            $feed->consecutive_failures = max((int) ($feed->consecutive_failures ?? 0), 1);
            $feed->last_error = Str::limit('rss:feeds-health probe: ' . $entry['verdict'], 250);
            // last_synced_at به الان می‌رسد تا تست مجدد خودکار (retest) 7 روز دیگر انجام شود
            $feed->last_synced_at = now();
            $feed->save();
            $disabled++;
        }

        Log::info('rss:feeds-health: disabled dead feeds', [
            'count' => $disabled,
            'ids' => array_values(array_map(fn ($e) => $e['feed']->id, $deadFeeds)),
        ]);

        $this->info("Disabled {$disabled} dead feed(s). They will be re-tested automatically after 7 days.");
        return self::SUCCESS;
    }

    /**
     * دریافت زنده فید و دسته‌بندی ساده نتیجه.
     *
     * verdict یکی از: ok | invalid_url | connection_error | http_error | html_not_xml | invalid_xml
     *
     * @return array{code: string, verdict: string}
     */
    protected function probe(string $url): array
    {
        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
            // مثلاً آدرس‌های خراب به شکل view-source:https://...
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
