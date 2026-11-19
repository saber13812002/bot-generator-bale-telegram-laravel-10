<?php

namespace Tests\Feature;

use App\Models\RssItem;
use App\Services\RssItemService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesRssPipelineSqlite;

/**
 * P0 — RSS fetch pipeline tests:
 *  - healthy 200 → items saved, health counters reset
 *  - 403 / timeout / bad URL → failure recorded, feed NOT disabled on one error
 *  - per-feed isolation (one failing feed does not stop the run)
 *  - concurrency lock → second run skipped, exit 0
 *  - dry-run → no HTTP, no save, no translate
 *  - link dedup → re-fetching the same feed saves nothing twice
 *  - --limit → oldest feed first (anti-starvation)
 *  - 10 consecutive failures → auto-disable; 8 → still active
 *  - disabled feed with resolved error → retest re-enables it
 *  - tokens in error messages are redacted before being stored
 */
class RssFetchPipelineTest extends TestCase
{
    use UsesRssPipelineSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRssPipelineTables();
    }

    private function rssXml(string $host, int $count = 3): string
    {
        $items = '';
        for ($i = 1; $i <= $count; $i++) {
            $items .= '<item>'
                . '<title>Post ' . $i . '</title>'
                . '<link>https://' . $host . '/post-' . $i . '</link>'
                . '<description>Body ' . $i . '</description>'
                . '<pubDate>Mon, 01 Jan 2026 00:00:00 +0000</pubDate>'
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0"><channel>'
            . '<title>Feed</title><link>https://' . $host . '/</link>'
            . $items
            . '</channel></rss>';
    }

    public function test_healthy_200_feed_saves_items_and_resets_health_counters(): void
    {
        $feedId = $this->createRssFeed([
            'url' => 'https://feed-a.example.com/rss.xml',
            'consecutive_failures' => 3,
            'last_error' => 'stale error from before',
        ]);

        Http::fake(['*' => Http::response($this->rssXml('feed-a.example.com'), 200)]);

        // Artisan::call() (not $this->artisan()) so Artisan::output() below sees
        // the real command output — PendingCommand writes to its own mock buffer.
        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $this->assertDatabaseCount('rss_post_items', 3);
        $this->assertSame(3, DB::table('rss_post_items')->where('rss_item_id', $feedId)->count());

        $feed = RssItem::find($feedId);
        $this->assertSame(0, (int) $feed->consecutive_failures);
        $this->assertNull($feed->last_error);
        $this->assertSame(1, (int) $feed->is_active);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Fetch summary: 1 ok, 0 failed, 3 new saved (of 1 targeted)', $output);
    }

    public function test_http_403_records_failure_but_keeps_feed_active(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-403.example.com/rss.xml']);

        Http::fake(['*' => Http::response('forbidden', 403)]);

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $feed = RssItem::find($feedId);
        $this->assertSame(1, (int) $feed->consecutive_failures);
        $this->assertStringContainsString('HTTP status: 403', (string) $feed->last_error);
        $this->assertSame(1, (int) $feed->is_active, 'a single transient error must not disable the feed');
        $this->assertDatabaseCount('rss_post_items', 0);
    }

    public function test_connection_timeout_records_failure(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-slow.example.com/rss.xml']);

        Http::fake(function () {
            throw new ConnectException(
                'cURL error 28: Operation timed out',
                new Request('GET', 'https://feed-slow.example.com/rss.xml')
            );
        });

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $feed = RssItem::find($feedId);
        $this->assertSame(1, (int) $feed->consecutive_failures);
        $this->assertStringContainsString('timed out', (string) $feed->last_error);
        $this->assertSame(1, (int) $feed->is_active);
    }

    public function test_token_in_error_message_is_redacted_before_storage(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-secret.example.com/rss.xml']);

        Http::fake(function () {
            throw new ConnectException(
                'rejected: bot12345678:abcdefghijklmnopqrstuvwxyz123456 refused',
                new Request('GET', 'https://feed-secret.example.com/rss.xml')
            );
        });

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $lastError = (string) RssItem::find($feedId)->last_error;
        $this->assertStringNotContainsString('12345678:abcdefghijklmnopqrstuvwxyz', $lastError);
        $this->assertStringContainsString('[REDACTED]', $lastError);
    }

    public function test_one_failing_feed_does_not_stop_the_others(): void
    {
        $feedA = $this->createRssFeed(['url' => 'https://feed-a.example.com/rss.xml']);
        $feedB = $this->createRssFeed(['url' => 'https://feed-b.example.com/rss.xml']);

        Http::fake([
            'feed-a.example.com/*' => Http::response('nope', 403),
            'feed-b.example.com/*' => Http::response($this->rssXml('feed-b.example.com'), 200),
        ]);

        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--no-translate' => true]));

        $this->assertSame(3, DB::table('rss_post_items')->where('rss_item_id', $feedB)->count());
        $this->assertSame(0, DB::table('rss_post_items')->where('rss_item_id', $feedA)->count());
        $this->assertSame(1, (int) RssItem::find($feedA)->consecutive_failures);

        $this->assertStringContainsString(
            '[FAILED] feed #' . $feedA,
            \Illuminate\Support\Facades\Artisan::output()
        );
    }

    public function test_concurrent_run_is_skipped_when_lock_is_held(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-lock.example.com/rss.xml']);
        // RssItem casts last_synced_at to 'datetime:Y-m-d' (day granularity) and a
        // fresh Carbon is produced on every model load, so compare formatted values.
        $before = RssItem::find($feedId)->last_synced_at?->format('Y-m-d');

        Http::fake(['*' => Http::response($this->rssXml('feed-lock.example.com'), 200)]);

        $lock = \Illuminate\Support\Facades\Cache::lock('rss_read_translate_lock', 60);
        $this->assertTrue($lock->get());

        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $this->assertStringContainsString(
            'Another RSS run is already in progress; skipped.',
            \Illuminate\Support\Facades\Artisan::output()
        );
        $this->assertDatabaseCount('rss_post_items', 0);
        $this->assertSame($before, RssItem::find($feedId)->last_synced_at?->format('Y-m-d'), 'skipped run must not touch feed state');
    }

    public function test_dry_run_makes_no_requests_and_saves_nothing(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-dry.example.com/rss.xml']);
        $before = RssItem::find($feedId)->last_synced_at;

        Http::fake(['*' => Http::response($this->rssXml('feed-dry.example.com'), 200)]);

        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--dry-run' => true, '--feed-id' => $feedId, '--no-translate' => true]));

        Http::assertNothingSent();
        $this->assertDatabaseCount('rss_post_items', 0);
        // RssItem casts last_synced_at to 'datetime:Y-m-d' (day granularity), so
        // compare by formatted string — two Carbon objects from separate model
        // loads are never the same instance for assertSame.
        $this->assertSame(
            $before?->format('Y-m-d'),
            RssItem::find($feedId)->last_synced_at?->format('Y-m-d')
        );
    }

    public function test_dry_run_flags_invalid_url_without_any_request(): void
    {
        $feedId = $this->createRssFeed(['url' => 'view-source:https://example.com/rss.xml']);

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--dry-run' => true, '--feed-id' => $feedId, '--no-translate' => true]));

        Http::assertNothingSent();
        $feed = RssItem::find($feedId);
        // dry-run does not record feed results at all
        $this->assertSame(0, (int) $feed->consecutive_failures);
    }

    public function test_unknown_feed_id_is_a_command_level_failure(): void
    {
        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => 999999, '--no-translate' => true]));

        $this->assertDatabaseCount('rss_post_items', 0);
    }

    public function test_limit_processes_oldest_feed_first_without_starving_the_tail(): void
    {
        // RssItem casts last_synced_at to 'datetime:Y-m-d' (day granularity),
        // so distinct DATES are required for a deterministic oldest-first order.
        $feedA = $this->createRssFeed([
            'url' => 'https://feed-old.example.com/rss.xml',
            'last_synced_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
        ]);
        $feedB = $this->createRssFeed([
            'url' => 'https://feed-new.example.com/rss.xml',
            'last_synced_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        // ⚠️ The fake callback receives an Illuminate\Http\Client\Request
        // (NOT a Guzzle PSR-7 request) — type-hinting the PSR-7 class throws
        // a TypeError inside the command and marks the feed as failed.
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $host = (string) parse_url($request->url(), PHP_URL_HOST);

            return Http::response($this->rssXml($host), 200);
        });

        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--limit' => 1, '--no-translate' => true]));

        $this->assertSame(3, DB::table('rss_post_items')->where('rss_item_id', $feedA)->count());
        $this->assertSame(0, DB::table('rss_post_items')->where('rss_item_id', $feedB)->count(), 'with --limit=1 only the oldest feed runs');

        $this->assertStringContainsString(
            'Fetch summary: 1 ok, 0 failed, 3 new saved (of 1 targeted)',
            \Illuminate\Support\Facades\Artisan::output()
        );
    }

    public function test_re_fetching_same_feed_does_not_duplicate_items(): void
    {
        $feedId = $this->createRssFeed(['url' => 'https://feed-dup.example.com/rss.xml']);

        Http::fake(['*' => Http::response($this->rssXml('feed-dup.example.com'), 200)]);

        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));
        $this->assertDatabaseCount('rss_post_items', 3);

        // second run: same links already exist → deduped
        $this->assertSame(0, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));
        $this->assertDatabaseCount('rss_post_items', 3);
    }

    public function test_tenth_consecutive_failure_auto_disables_feed(): void
    {
        $feedId = $this->createRssFeed([
            'url' => 'https://feed-dead.example.com/rss.xml',
            'consecutive_failures' => 9,
        ]);

        Http::fake(['*' => Http::response('nope', 403)]);

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $feed = RssItem::find($feedId);
        $this->assertSame(10, (int) $feed->consecutive_failures);
        $this->assertSame(0, (int) $feed->is_active);
    }

    public function test_nine_consecutive_failures_keep_feed_active(): void
    {
        $feedId = $this->createRssFeed([
            'url' => 'https://feed-alive.example.com/rss.xml',
            'consecutive_failures' => 8,
        ]);

        Http::fake(['*' => Http::response('nope', 403)]);

        $this->assertSame(1, Artisan::call('app:rss_read_translate', ['--feed-id' => $feedId, '--no-translate' => true]));

        $feed = RssItem::find($feedId);
        $this->assertSame(9, (int) $feed->consecutive_failures);
        $this->assertSame(1, (int) $feed->is_active, 'below the threshold the feed must stay active');
    }

    public function test_disabled_feed_is_re_enabled_once_its_error_is_resolved(): void
    {
        $feedId = $this->createRssFeed([
            'url' => 'https://feed-healed.example.com/rss.xml',
            'is_active' => 0,
            'consecutive_failures' => 10,
            'last_error' => 'Failed to fetch RSS feed. HTTP status: 403',
            'last_synced_at' => now()->subDays(8)->format('Y-m-d H:i:s'),
        ]);

        Http::fake(['*' => Http::response($this->rssXml('feed-healed.example.com'), 200)]);

        $reEnabled = RssItemService::retestDisabledFeeds();

        $this->assertSame(1, $reEnabled);

        $feed = RssItem::find($feedId);
        $this->assertSame(1, (int) $feed->is_active);
        $this->assertSame(0, (int) $feed->consecutive_failures);
        $this->assertNull($feed->last_error);
        $this->assertSame(3, DB::table('rss_post_items')->where('rss_item_id', $feedId)->count());
    }
}
