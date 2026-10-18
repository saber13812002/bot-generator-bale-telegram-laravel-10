<?php

namespace Tests\Feature;

use App\Interfaces\RssChannelMessageSender;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\Fakes\FakeRssChannelMessageSender;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;
use Tests\UsesRssPipelineSqlite;

/**
 * rss:recover — admin recovery tool tests (P3).
 *
 * Scenarios covered:
 *  - dry-run classifies records (delivered/retryable/needs_review/insufficient) without resending
 *  - --execute without a valid admin chat id is refused (no sends)
 *  - --execute with a non-admin chat id is refused (no sends)
 *  - --execute with a valid admin resends ONLY "retryable" records (no repeat of successful sends)
 *  - --record-id narrows the scan to a single record
 *  - --include-queue retries evidence-less "queue" records (explicit admin confirmation)
 *  - concurrent execution is blocked by the execution lock
 *  - channel-poster: failed items are retried and published; failed-with-success-logs
 *    are needs_review (no blind resend); published/cancelled are never touched
 */
class RssRecoveryTest extends TestCase
{
    use UsesRssPipelineSqlite, UsesChannelPosterSqlite {
        UsesRssPipelineSqlite::createApplication insteadof UsesChannelPosterSqlite;
    }

    private FakeRssChannelMessageSender $sender;

    private FakeChannelPosterPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRssPipelineTables();
        $this->setUpChannelPosterTables();

        $this->neutralizeAdminEnv();

        $this->sender = new FakeRssChannelMessageSender();
        $this->app->instance(RssChannelMessageSender::class, $this->sender);

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    protected function tearDown(): void
    {
        $this->restoreAdminEnv();
        parent::tearDown();
    }

    /**
     * Build a full RSS queue-record chain (feed → post → translation → queue row)
     * on the given channel. Returns the queue id.
     */
    private function createRssRecord(int $channelId, array $queueAttrs = []): int
    {
        $feedId = $this->createRssFeed(['title' => 'Test Feed Title']);
        $postId = $this->createRssPostItem($feedId, [
            'title' => 'Test Post Title',
            'link' => 'https://example.com/post-' . uniqid(),
            'image_url' => 'https://cdn.example.com/img.jpg',
        ]);
        $translationId = $this->createRssTranslation($postId, [
            'title' => 'Test Post Title',
            'content' => 'Translated content body.',
        ]);

        return $this->createRssQueueRow($translationId, $channelId, $queueAttrs);
    }

    private function createBot(string $token): Bot
    {
        return Bot::create([
            'endpoint_id' => 'channel_poster_bot',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_token' => $token,
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => 111,
            'bale_webhook_is_set' => true,
        ]);
    }

    private function createDestination(int $botId, string $tag): ChannelPosterDestination
    {
        return ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'bale',
            'channel_chat_id' => 'channel-1',
            'channel_title' => 'Test Channel',
            'tag' => $tag,
            'is_active' => true,
        ]);
    }

    private function createQueueItem(int $botId, string $tag, array $attrs = []): ChannelPosterQueue
    {
        return ChannelPosterQueue::create(array_merge([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => 'text',
            'text' => 'recovery post',
            'scheduled_at' => now(),
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => '111',
            'owner_origin' => 'bale',
        ], $attrs));
    }

    private function queueRow(int $id): object
    {
        return DB::table('rss_post_item_translation_queues')->find($id);
    }

    // ------------------------------------------------------------------
    // RSS
    // ------------------------------------------------------------------

    public function test_dry_run_classifies_records_without_resending(): void
    {
        $channelId = $this->createRssChannel('bale');

        $sentId = $this->createRssRecord($channelId, [
            'status' => 'sent',
            'message_id' => 'msg-123',
            'sent_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $retryableId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
            'attempts' => 1,
        ]);
        $ambiguousId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'curl error 28: Operation timed out',
        ]);
        $noErrorId = $this->createRssRecord($channelId, ['status' => 'error']);
        $plainQueueId = $this->createRssRecord($channelId); // status=queue, no evidence

        // Artisan::call() (not $this->artisan()) so Artisan::output() below sees
        // the real command output — PendingCommand writes to its own mock buffer.
        $this->assertSame(0, Artisan::call('rss:recover', ['--system' => 'rss']));

        $out = Artisan::output();
        $this->assertStringContainsString('mode: DRY-RUN (report only)', $out);
        $this->assertStringContainsString(
            'Totals: 5 scanned | delivered=1 retryable=1 needs_review=1 insufficient=2 cancelled=0 pending=0',
            $out
        );
        $this->assertStringContainsString('Re-run with --execute', $out);

        // dry-run must never send anything
        $this->assertCount(0, $this->sender->baseMessages);
        $this->assertCount(0, $this->sender->photos);
        $this->assertCount(0, $this->sender->audios);

        // and never mutate any record
        $this->assertSame('sent', $this->queueRow($sentId)->status);
        $this->assertSame('error', $this->queueRow($retryableId)->status);
        $this->assertSame('error', $this->queueRow($ambiguousId)->status);
        $this->assertSame('error', $this->queueRow($noErrorId)->status);
        $this->assertSame('queue', $this->queueRow($plainQueueId)->status);
    }

    public function test_execute_without_admin_chat_id_is_refused(): void
    {
        $channelId = $this->createRssChannel('bale');
        $retryableId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
        ]);

        $this->assertSame(1, Artisan::call('rss:recover', ['--system' => 'rss', '--execute' => true]));

        $this->assertStringContainsString(
            'real execution requires a valid --admin-chat-id (admin check failed)',
            Artisan::output()
        );

        $this->assertCount(0, $this->sender->baseMessages);
        $this->assertSame('error', $this->queueRow($retryableId)->status);
    }

    public function test_execute_with_non_admin_chat_id_is_refused(): void
    {
        $channelId = $this->createRssChannel('bale');
        $retryableId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
        ]);

        $this->assertSame(1, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--execute' => true,
            '--admin-chat-id' => '999999',
        ]));

        $this->assertStringContainsString('admin check failed', Artisan::output());
        $this->assertCount(0, $this->sender->baseMessages);
        $this->assertSame('error', $this->queueRow($retryableId)->status);
    }

    public function test_execute_with_valid_admin_resends_only_retryable_records(): void
    {
        $channelId = $this->createRssChannel('bale');

        $sentId = $this->createRssRecord($channelId, [
            'status' => 'sent',
            'message_id' => 'msg-123',
            'sent_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $retryableId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
            'attempts' => 1,
        ]);
        $ambiguousId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'socket connection reset',
        ]);
        $noErrorId = $this->createRssRecord($channelId, ['status' => 'error']);
        $plainQueueId = $this->createRssRecord($channelId);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString('mode: EXECUTE (real resend)', $out);
        $this->assertStringContainsString('Executed: retried=1 ok=1 failed=0 skipped=0', $out);

        // only the retryable record was re-dispatched (sync queue → inline)
        $retry = $this->queueRow($retryableId);
        $this->assertSame('sent', $retry->status);
        $this->assertSame('fake-base-1', $retry->message_id);
        $this->assertNotNull($retry->sent_at);
        $this->assertSame(2, (int) $retry->attempts);
        $this->assertNull($retry->media_error);

        // everything else is untouched — especially the already-delivered record
        $this->assertSame('sent', $this->queueRow($sentId)->status);
        $this->assertSame('msg-123', $this->queueRow($sentId)->message_id);
        $this->assertSame('error', $this->queueRow($ambiguousId)->status);
        $this->assertSame('error', $this->queueRow($noErrorId)->status);
        $this->assertSame('queue', $this->queueRow($plainQueueId)->status);

        // exactly one base message went out (the retryable record)
        $this->assertCount(1, $this->sender->baseMessages);
        $this->assertCount(1, $this->sender->photos);
        $this->assertCount(0, $this->sender->audios);
    }

    public function test_stale_retryable_record_is_not_auto_resent(): void
    {
        $channelId = $this->createRssChannel('bale');
        $staleId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
            'created_at' => now()->subDays(3), // > 48h — backlog کهنه
        ]);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString('stale record', $out);
        $this->assertStringContainsString('manual review required', $out);
        $this->assertStringContainsString('Executed: retried=0 ok=0 failed=0 skipped=1', $out);

        // هیچ ارسال واقعی‌ای انجام نشده و رکورد دست‌نخورده مانده
        $this->assertCount(0, $this->sender->baseMessages);
        $this->assertCount(0, $this->sender->photos);
        $this->assertSame('error', $this->queueRow($staleId)->status);
    }

    public function test_record_id_limits_scan_to_a_single_record(): void
    {
        $channelId = $this->createRssChannel('bale');
        $otherId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=500 server_error',
        ]);
        $targetId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
        ]);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--record-id' => (string) $targetId,
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString('Totals: 1 scanned |', $out);
        $this->assertStringContainsString('Executed: retried=1 ok=1 failed=0 skipped=0', $out);

        $this->assertSame('sent', $this->queueRow($targetId)->status);
        $this->assertSame('error', $this->queueRow($otherId)->status);
        $this->assertCount(1, $this->sender->baseMessages);
    }

    public function test_include_queue_retries_evidenceless_queue_records(): void
    {
        $channelId = $this->createRssChannel('bale');
        $queueId = $this->createRssRecord($channelId); // status=queue, no evidence

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--execute' => true,
            '--admin-chat-id' => '424242',
            '--include-queue' => true,
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString(
            'Totals: 1 scanned | delivered=0 retryable=1 needs_review=0 insufficient=0 cancelled=0 pending=0',
            $out
        );
        $this->assertStringContainsString('Executed: retried=1 ok=1 failed=0 skipped=0', $out);

        // the execute-time status guard must allow 'queue' when --include-queue is set
        $row = $this->queueRow($queueId);
        $this->assertSame('sent', $row->status);
        $this->assertSame('fake-base-1', $row->message_id);
        $this->assertNotNull($row->sent_at);
        $this->assertCount(1, $this->sender->baseMessages);
    }

    public function test_concurrent_execution_is_blocked_by_lock(): void
    {
        $channelId = $this->createRssChannel('bale');
        $retryableId = $this->createRssRecord($channelId, [
            'status' => 'error',
            'last_error' => 'code=403 permission_denied',
        ]);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $held = Cache::lock('rss_recover_exec_lock', 60);
        $this->assertTrue($held->get());

        $this->assertSame(1, Artisan::call('rss:recover', [
            '--system' => 'rss',
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $this->assertStringContainsString('another rss:recover execution is in progress', Artisan::output());
        $this->assertCount(0, $this->sender->baseMessages);
        $this->assertSame('error', $this->queueRow($retryableId)->status);
    }

    // ------------------------------------------------------------------
    // Channel-poster
    // ------------------------------------------------------------------

    public function test_channel_poster_failed_item_is_retried_and_published(): void
    {
        $bot = $this->createBot('123456:recovery-bot-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', [
            'status' => ChannelPosterQueue::STATUS_FAILED,
        ]);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'channel-poster',
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString(
            'Totals: 1 scanned | delivered=0 retryable=1 needs_review=0 insufficient=0 cancelled=0 pending=0',
            $out
        );
        $this->assertStringContainsString('Executed: retried=1 ok=1 failed=0 skipped=0', $out);

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNotNull($item->published_at);

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('channel-1', $this->publisher->publishes[0]['channel_chat_id']);
        $this->assertSame('bale', $this->publisher->publishes[0]['platform']);
    }

    public function test_channel_poster_failed_with_success_log_is_needs_review(): void
    {
        $bot = $this->createBot('123456:recovery-bot-token');
        $dest = $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', [
            'status' => ChannelPosterQueue::STATUS_FAILED,
        ]);

        // a previous successful delivery to this destination (partial-publish history)
        DB::table('channel_poster_publish_logs')->insertGetId([
            'bot_id' => $bot->id,
            'queue_id' => $item->id,
            'destination_id' => $dest->id,
            'platform' => 'bale',
            'success' => 1,
            'message_id' => 'old-msg-1',
            'error' => null,
            'published_at' => now()->format('Y-m-d H:i:s'),
            'post_url' => null,
            'link_status' => null,
            'content_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('rss:recover', ['--system' => 'channel-poster']));

        $this->assertStringContainsString(
            'Totals: 1 scanned | delivered=0 retryable=0 needs_review=1 insufficient=0 cancelled=0 pending=0',
            Artisan::output()
        );
        $this->assertCount(0, $this->publisher->publishes, 'no blind resend when a destination already succeeded');
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->fresh()->status);
    }

    public function test_published_and_cancelled_items_are_never_touched(): void
    {
        $bot = $this->createBot('123456:recovery-bot-token');
        $this->createDestination($bot->id, 'tag1');
        $published = $this->createQueueItem($bot->id, 'tag1', [
            'status' => ChannelPosterQueue::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);
        $cancelled = $this->createQueueItem($bot->id, 'tag1', [
            'status' => ChannelPosterQueue::STATUS_CANCELLED,
        ]);

        $this->setAdminEnv('SUPER_ADMIN_CHAT_ID_BALE', '424242');

        $this->assertSame(0, Artisan::call('rss:recover', [
            '--system' => 'channel-poster',
            '--execute' => true,
            '--admin-chat-id' => '424242',
        ]));

        $out = Artisan::output();
        $this->assertStringContainsString(
            'Totals: 2 scanned | delivered=1 retryable=0 needs_review=0 insufficient=0 cancelled=1 pending=0',
            $out
        );
        $this->assertStringContainsString('Executed: retried=0 ok=0 failed=0 skipped=0', $out);

        $this->assertCount(0, $this->publisher->publishes, 'successful/cancelled records must never be republished');
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $published->fresh()->status);
        $this->assertSame(ChannelPosterQueue::STATUS_CANCELLED, $cancelled->fresh()->status);
    }
}
