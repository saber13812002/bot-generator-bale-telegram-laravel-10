<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use Illuminate\Support\Carbon;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class ChannelPosterQueueSendTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    public function test_overdue_pending_item_is_published_and_owner_notified(): void
    {
        $bot = $this->createBot('123:queue-token');
        $destination = $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour());

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNotNull($item->published_at);

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('channel-1', $this->publisher->publishes[0]['channel_chat_id']);
        $this->assertSame('bale', $this->publisher->publishes[0]['platform']);
        $this->assertSame('text', $this->publisher->publishes[0]['content_type']);
        $this->assertSame('queued post text', $this->publisher->publishes[0]['text']);

        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'bot_id' => $bot->id,
            'queue_id' => $item->id,
            'destination_id' => $destination->id,
            'success' => true,
        ]);

        $this->assertCount(1, $this->publisher->privateMessages);
        $this->assertSame('111', $this->publisher->privateMessages[0]['chat_id']);
    }

    public function test_future_item_is_skipped_without_all_flag_and_sent_with_all(): void
    {
        $bot = $this->createBot('123:queue-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->addHour());

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $this->assertSame(ChannelPosterQueue::STATUS_PENDING, $item->refresh()->status);
        $this->assertCount(0, $this->publisher->publishes);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id, '--all' => true])
            ->assertSuccessful();

        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
        $this->assertCount(1, $this->publisher->publishes);
    }

    public function test_failed_publish_marks_item_failed(): void
    {
        $bot = $this->createBot('123:queue-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour());

        $this->publisher->publishSucceeds = false;

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertFailed();

        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'success' => false,
        ]);
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

    private function createQueueItem(int $botId, string $tag, Carbon $scheduledAt): ChannelPosterQueue
    {
        return ChannelPosterQueue::create([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => 'text',
            'text' => 'queued post text',
            'scheduled_at' => $scheduledAt,
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => '111',
            'owner_origin' => 'bale',
        ]);
    }
}
