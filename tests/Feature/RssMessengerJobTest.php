<?php

namespace Tests\Feature;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use App\Models\RssPostItemTranslationQueue;
use Tests\Fakes\FakeRssChannelMessageSender;
use Tests\TestCase;
use Tests\UsesRssPipelineSqlite;

/**
 * RSS → channel messenger job (RssPostItemTranslationToMessengerJob) tests:
 *  - successful base + photo → status 'sent' with delivery evidence
 *  - base failure (403) → 'error', NO false success, NO wasted media calls
 *  - partial media failure after base success → 'sent' + media_error
 *  - exception after base success → 'sent' + media_error (no duplicate resend later)
 *  - hard exception before base → 'error' with exception reason
 *  - missing channel → 'error' without any sender call
 */
class RssMessengerJobTest extends TestCase
{
    use UsesRssPipelineSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRssPipelineTables();
    }

    /**
     * Build the full fixture chain (feed → post → translation → queue row).
     * Returns the queue id.
     */
    private function makeQueueFixture(string $mediumSlug = 'bale'): int
    {
        $feedId = $this->createRssFeed(['title' => 'Test Feed Title']);
        $channelId = $this->createRssChannel($mediumSlug);
        $postId = $this->createRssPostItem($feedId, [
            'title' => 'Test Post Title',
            'link' => 'https://example.com/post-1',
            'image_url' => 'https://cdn.example.com/img.jpg',
        ]);
        $translationId = $this->createRssTranslation($postId, [
            'title' => 'Test Post Title',
            'content' => 'Translated content body.',
        ]);

        return $this->createRssQueueRow($translationId, $channelId);
    }

    private function runJob(int $queueId, FakeRssChannelMessageSender $sender): void
    {
        $queue = RssPostItemTranslationQueue::find($queueId);
        (new RssPostItemTranslationToMessengerJob($queue, $sender))->handle();
    }

    public function test_successful_base_and_photo_mark_queue_sent_with_evidence(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        $sender = new FakeRssChannelMessageSender();

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('sent', $row->status);
        $this->assertSame('fake-base-1', $row->message_id);
        $this->assertNotNull($row->sent_at);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertNull($row->last_error);
        $this->assertNull($row->media_error);

        // base message: bale origin → parseMode null
        $this->assertCount(1, $sender->baseMessages);
        $this->assertSame('bale', $sender->baseMessages[0]['mediumSlug']);
        $this->assertNull($sender->baseMessages[0]['parseMode']);
        $this->assertStringContainsString('Test Post Title', $sender->baseMessages[0]['message']);

        // photo sent with the post image
        $this->assertCount(1, $sender->photos);
        $this->assertSame('https://cdn.example.com/img.jpg', $sender->photos[0]['photoUrl']);
        $this->assertStringContainsString('https://example.com/post-1', $sender->photos[0]['caption']);
        $this->assertStringContainsString('#bale', $sender->photos[0]['caption']);

        // example.com link → no audio branches
        $this->assertCount(0, $sender->audios);
    }

    public function test_base_failure_is_error_without_false_success_or_media_waste(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        $sender = new FakeRssChannelMessageSender();
        $sender->baseResponse = ['ok' => false, 'code' => 403, 'description' => 'permission_denied'];

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('error', $row->status, 'a failed base send must never be recorded as sent');
        $this->assertStringContainsString('code=403', (string) $row->last_error);
        $this->assertStringContainsString('permission_denied', (string) $row->last_error);
        $this->assertNull($row->sent_at, 'no delivery evidence may be recorded');
        $this->assertNull($row->message_id);
        $this->assertSame(1, (int) $row->attempts);

        $this->assertCount(0, $sender->photos, 'no media calls after a failed base send');
        $this->assertCount(0, $sender->audios);
    }

    public function test_photo_failure_after_base_success_stays_sent_with_media_error(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        $sender = new FakeRssChannelMessageSender();
        $sender->photoResponse = ['ok' => false, 'code' => 500, 'description' => 'internal_error'];

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('sent', $row->status, 'base was delivered → resending would duplicate text');
        $this->assertSame('fake-base-1', $row->message_id);
        $this->assertNotNull($row->sent_at);
        $this->assertStringContainsString('photo: code=500', (string) $row->media_error);
    }

    public function test_exception_after_base_success_records_media_error_not_error(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        $sender = new FakeRssChannelMessageSender();
        $sender->photoException = new \RuntimeException('cdn exploded');

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('sent', $row->status);
        $this->assertSame('fake-base-1', $row->message_id);
        $this->assertStringContainsString('exception_after_base: cdn exploded', (string) $row->media_error);
        $this->assertNull($row->last_error);
    }

    public function test_exception_before_base_marks_error_with_reason(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        $sender = new FakeRssChannelMessageSender();
        $sender->baseException = new \RuntimeException('socket reset by peer');

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('error', $row->status);
        $this->assertStringContainsString('socket reset by peer', (string) $row->last_error);
        $this->assertNull($row->sent_at);
        $this->assertNull($row->message_id);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_missing_channel_marks_error_without_any_sender_call(): void
    {
        $queueId = $this->makeQueueFixture('bale');
        \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')
            ->where('id', $queueId)
            ->update(['rss_channel_id' => 999]);

        $sender = new FakeRssChannelMessageSender();

        $this->runJob($queueId, $sender);

        $row = \Illuminate\Support\Facades\DB::table('rss_post_item_translation_queues')->find($queueId);

        $this->assertSame('error', $row->status);
        $this->assertStringContainsString('rss_channel_not_found', (string) $row->last_error);

        $this->assertCount(0, $sender->baseMessages);
        $this->assertCount(0, $sender->photos);
        $this->assertCount(0, $sender->audios);
    }
}
