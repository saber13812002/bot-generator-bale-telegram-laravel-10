<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;
use Tests\UsesRssPipelineSqlite;

/**
 * P5 monitoring commands: pipeline:heartbeat / pipeline:status / pipeline:watch.
 *
 *  - heartbeat stores a fresh cache marker; status reports scheduler ok
 *  - stale heartbeat → status exits non-zero + watch alerts SCHEDULER STALE (deduped 6h)
 *  - no publish in 24h with active feeds → NO PUBLISH alert (deduped)
 *  - healthy pipeline (fresh heartbeat + recent sent record + clean channel-poster) → all green
 *  - error buildup without recent sends → RSS ERROR BUILDUP alert
 *  - the scheduler actually contains the pipeline entries
 */
class PipelineMonitoringTest extends TestCase
{
    use UsesRssPipelineSqlite, UsesChannelPosterSqlite {
        UsesRssPipelineSqlite::createApplication insteadof UsesChannelPosterSqlite;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRssPipelineTables();
        $this->setUpChannelPosterTables();
    }

    private function freshHeartbeat(): void
    {
        Artisan::call('pipeline:heartbeat');
    }

    /**
     * Insert a minimal RSS queue row (translation chain not needed for counters).
     */
    private function addQueueRow(array $attrs = []): int
    {
        return DB::table('rss_post_item_translation_queues')->insertGetId(array_merge([
            'rss_post_item_translation_id' => 1,
            'rss_channel_id' => 1,
            'status' => 'queue',
            'message_id' => null,
            'sent_at' => null,
            'attempts' => 0,
            'last_error' => null,
            'media_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    public function test_heartbeat_and_status_json_report_ok(): void
    {
        // Artisan::call() (not $this->artisan()) everywhere: PendingCommand::run()
        // binds OutputStyle::class in the container to its own mock, so any
        // subsequent Artisan::call() would write into the mock's private buffer
        // and Artisan::output() would stay empty.
        $this->assertSame(0, Artisan::call('pipeline:heartbeat'));

        $this->assertNotSame(0, (int) Cache::get('pipeline:heartbeat:ts'));
        $this->assertNotEmpty(Cache::get('pipeline:heartbeat:at'));

        $this->createRssFeed(); // fixture: status report expects exactly one feed

        $this->assertSame(0, Artisan::call('pipeline:status', ['--json' => true]));

        $json = json_decode(Artisan::output(), true);
        $this->assertIsArray($json, 'status --json must print a single JSON line');
        $this->assertTrue($json['scheduler']['ok']);
        $this->assertSame(1, $json['rss']['feeds_total']);
        $this->assertSame(1, $json['rss']['feeds_active']);
        $this->assertSame(0, $json['queues']['total']);
    }

    public function test_stale_heartbeat_fails_status_and_alerts_with_dedupe(): void
    {
        // heartbeat from 2h ago → scheduler stale (> 600s)
        Cache::put('pipeline:heartbeat:ts', time() - 7200, 600);

        // Artisan::call() (not $this->artisan()) — see note in the json test:
        // PendingCommand pollutes the container's OutputStyle binding.
        $this->assertSame(1, Artisan::call('pipeline:status'));

        // watch: first run alerts, second run (same key) is suppressed
        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $first = Artisan::output();
        $this->assertStringContainsString('SCHEDULER STALE', $first);
        $this->assertStringContainsString('[DRY-RUN] would alert: pipeline:alert:scheduler_stale', $first);

        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $second = Artisan::output();
        $this->assertStringNotContainsString('SCHEDULER STALE', $second);
        $this->assertStringContainsString('all green, no alerts', $second);
    }

    public function test_no_publish_in_24h_alerts_then_dedupes(): void
    {
        $this->freshHeartbeat();
        $this->createRssFeed(); // active feed, no sent_at records anywhere

        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $first = Artisan::output();
        $this->assertStringContainsString('NO PUBLISH', $first);
        $this->assertStringContainsString('[DRY-RUN] would alert: pipeline:alert:no_publish', $first);

        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $second = Artisan::output();
        $this->assertStringNotContainsString('NO PUBLISH', $second);
        $this->assertStringContainsString('all green, no alerts', $second);
    }

    public function test_healthy_pipeline_reports_all_green(): void
    {
        $this->freshHeartbeat();
        $this->createRssFeed();

        // a successful publish in the last 24h (sent + sent_at)
        $this->addQueueRow([
            'status' => 'sent',
            'message_id' => 'msg-1',
            'sent_at' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
        ]);

        // channel-poster queue is empty → no channel-poster alert
        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $out = Artisan::output();
        $this->assertStringContainsString('all green, no alerts', $out);
        $this->assertStringNotContainsString('would alert', $out);
    }

    public function test_error_buildup_alerts_when_no_recent_sends(): void
    {
        $this->freshHeartbeat();
        $this->createRssFeed();

        // 3 error records, no recent successful sends
        foreach (range(1, 3) as $i) {
            $this->addQueueRow([
                'status' => 'error',
                'last_error' => 'code=403 permission_denied',
            ]);
        }

        $this->assertSame(0, Artisan::call('pipeline:watch', ['--dry-run' => true]));
        $out = Artisan::output();
        $this->assertStringContainsString('RSS ERROR BUILDUP', $out);
        $this->assertStringContainsString('[DRY-RUN] would alert: pipeline:alert:rss_errors', $out);
    }

    public function test_scheduler_contains_pipeline_entries(): void
    {
        $this->assertSame(0, Artisan::call('schedule:list'));

        $out = Artisan::output();
        $this->assertStringContainsString('pipeline:heartbeat', $out);
        $this->assertStringContainsString('pipeline:watch', $out);
        $this->assertStringContainsString('app:rss_read_translate', $out);
        $this->assertStringContainsString('rss:feeds-health', $out);
    }
}
