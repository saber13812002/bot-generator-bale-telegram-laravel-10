<?php

namespace App\Console\Commands;

use App\Models\RssItem;
use App\Services\RssService;
use Illuminate\Console\Command;

class RssTestFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rss:feed-test
        {--feed-id= : شناسه فید (rss_items.id)؛ اگر ندهید فهرست 10 فید فعال چاپ می‌شود}
        {--dry-run : (همیشه بدون ذخیره/ارسال است؛ این گزینه فقط صریح‌سازی می‌کند)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تست تک‌فید RSS برای دیباگ: دریافت، parse، شمارش آیتم جدید — بدون انتشار و بدون dispatch job';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $feedId = $this->option('feed-id');

        if ($feedId === null || $feedId === '' || !is_numeric($feedId)) {
            return $this->listActiveFeeds();
        }

        $feedId = (int) $feedId;
        $dryRun = (bool) $this->option('dry-run');

        $item = RssItem::query()->find($feedId);

        if (!$item) {
            $this->error("Feed #{$feedId} not found.");

            return 1;
        }

        $this->info(sprintf(
            'Feed #%d | active=%d | interval=%d min | last_synced_at=%s',
            $feedId,
            (int) $item->is_active,
            (int) ($item->interval_minutes ?? 59),
            $item->last_synced_at ?? 'never'
        ));

        if ($item->last_error) {
            $this->warn('last_error: ' . $item->last_error);
        }

        $this->info(sprintf(
            'URL: %s (unique tag: %s)',
            $item->url,
            $item->unique_xml_tag ?? 'link'
        ));
        $this->newLine();

        // این command عمداً هرگز ذخیره/انتشار انجام نمی‌دهد (فقط probe)
        $result = $this->probeFeed($item->url, $item->unique_xml_tag ?? 'link');

        if ($result['ok']) {
            $this->info(sprintf(
                'OK: HTTP 200, XML valid, %d items in feed, %d new links not yet in DB',
                $result['total'],
                $result['new']
            ));

            return 0;
        }

        $this->error(sprintf('FAILED: %s', $result['error']));

        return 1;
    }

    /**
     * دریافت فید بدون هیچ ذخیره‌ای — فقط برای تشخیص وضعیت (HTTP + parse + count).
     *
     * @return array{ok: bool, error: ?string, total: int, new: int}
     */
    private function probeFeed(string $url, string $uniqueField): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);

            if (!$response->successful()) {
                return ['ok' => false, 'error' => 'HTTP ' . $response->status(), 'total' => 0, 'new' => 0];
            }

            $body = $response->body();
            $head = substr(ltrim($body), 0, 500);

            if (stripos($head, '<!doctype html') !== false || stripos($head, '<html') !== false) {
                return ['ok' => false, 'error' => 'response is HTML, not XML (paywall/bot-block?)', 'total' => 0, 'new' => 0];
            }

            $xml = simplexml_load_string($body);

            if ($xml === false) {
                return ['ok' => false, 'error' => 'XML parse failed', 'total' => 0, 'new' => 0];
            }

            $total = isset($xml->channel->item) ? count($xml->channel->item) : 0;

            $new = 0;
            try {
                $new = RssService::countNewLinksInFeed($xml, $uniqueField);
            } catch (\Throwable $ignore) {
                $new = -1; // شمارش جدید فقط اطلاع‌رسان است
            }

            return ['ok' => true, 'error' => null, 'total' => $total, 'new' => $new];
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if (stripos($message, 'timed out') !== false || stripos($message, 'Timeout') !== false) {
                $message = 'timeout after 15s';
            }

            return ['ok' => false, 'error' => $message, 'total' => 0, 'new' => 0];
        }
    }

    private function listActiveFeeds(): int
    {
        $feeds = RssItem::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'title', 'url', 'last_synced_at', 'consecutive_failures']);

        $this->info(sprintf('Top 10 active feeds (%d total active):', RssItem::where('is_active', 1)->count()));

        foreach ($feeds as $feed) {
            $this->line(sprintf(
                '  #%d %s | synced: %s | failures: %d',
                $feed->id,
                \Illuminate\Support\Str::limit($feed->title ?? $feed->url, 40),
                $feed->last_synced_at ?? 'never',
                (int) $feed->consecutive_failures
            ));
        }

        $this->comment('Use --feed-id=<id> to test a single feed.');

        return 0;
    }
}
