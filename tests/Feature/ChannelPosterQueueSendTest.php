<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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

    // ─────────────────────────────────────────────────────────────────────
    // rubika destinations via the REAL factory (CP-RUBIKA-RESPONSE-FIX / M6)
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_unknown_result_marks_item_failed_and_is_never_auto_retried(): void
    {
        $bot = $this->createSoroushOriginBot();
        $destination = $this->createRubikaDestination($bot->id);
        $item = $this->createSoroushQueueItem($bot->id, 'tag1');

        $this->useRealPublisherFactory();
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['chat_id' => '9876543210'], 200),
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertFailed();

        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'destination_id' => $destination->id,
            'success' => false,
            'message_id' => null,
        ]);
        $this->assertSame(1, $this->rubikaSendCount());

        // بدون «مدرک موفقیت» هرگز retry خودکار نمی‌شود؛ --all فقط آیتم‌های
        // pending را انتخاب می‌کند و این آیتم حالا FAILED است.
        $this->artisan('channel-poster:send-queued', ['--all' => true])
            ->assertSuccessful();

        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);
        $this->assertSame(1, $this->rubikaSendCount());
    }

    public function test_rubika_success_without_message_id_marks_item_published_without_republish(): void
    {
        $bot = $this->createSoroushOriginBot();
        $destination = $this->createRubikaDestination($bot->id);
        $item = $this->createSoroushQueueItem($bot->id, 'tag1');

        $this->useRealPublisherFactory();
        Http::fake([
            // پذیرش صریح API بدون message_id (پاکت واقعی روبیکا)
            'botapi.rubika.ir/*' => Http::response(['status' => 'OK', 'data' => []], 200),
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
        // message_id می‌تواند null باشد — مهم: success=true تا انتشار تکراری نشود
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'destination_id' => $destination->id,
            'success' => true,
            'message_id' => null,
        ]);
        $this->assertSame(1, $this->rubikaSendCount());

        // حتی اگر دستی دوباره اجرا شود، پیام تکراری منتشر نمی‌شود
        // (آیتم دیگر pending نیست + dedup روی success=true)
        $this->artisan('channel-poster:send-queued', ['--all' => true])
            ->assertSuccessful();

        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
        $this->assertSame(1, $this->rubikaSendCount());
    }

    private function useRealPublisherFactory(): void
    {
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new \App\Services\TelegramChannelPosterPublisherFactory()
        );
    }

    /** تعداد درخواست‌های واقعی رفته به botapi.rubika.ir */
    private function rubikaSendCount(): int
    {
        $count = 0;
        foreach (\Illuminate\Support\Facades\Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'botapi.rubika.ir')) {
                $count++;
            }
        }

        return $count;
    }

    /** bot با مبدأ soroush — سرویس، telegram_bot_token را برای مبدأ غیر-bale می‌خواند. */
    private function createSoroushOriginBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => 'channel_poster_bot',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_token' => '123:queue-token',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => 111,
            'bale_webhook_is_set' => true,
            'telegram_bot_token' => '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234',
        ]);
    }

    private function createRubikaDestination(int $botId): ChannelPosterDestination
    {
        return ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'rubika',
            'channel_chat_id' => '9876543210',
            'channel_title' => 'Rubika Channel',
            'bot_token' => 'rubikatoken1234',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
    }

    private function createSoroushQueueItem(int $botId, string $tag): ChannelPosterQueue
    {
        return ChannelPosterQueue::create([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => 'text',
            'text' => 'queued post text',
            'scheduled_at' => now()->subHour(),
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => '111',
            'owner_origin' => 'soroush',
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
