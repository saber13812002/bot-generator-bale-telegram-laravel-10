<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class PostOverdueWatchdogTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        Schema::dropIfExists('admin_daily_channel_configs');
        Schema::create('admin_daily_channel_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_chat_id');
            $table->string('content_type', 30);
            $table->bigInteger('bale_channel_chat_id')->nullable();
            $table->bigInteger('telegram_channel_chat_id')->nullable();
            $table->string('eitaa_channel_chat_id', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('posts_per_day')->default(1);
            $table->string('last_sent_content_type')->nullable();
            $table->timestamps();
        });

        $this->neutralizeAdminNotificationEnv();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    protected function tearDown(): void
    {
        $this->restoreAdminNotificationEnv();
        parent::tearDown();
    }

    public function test_overdue_pending_item_is_resended_and_no_red_state(): void
    {
        $bot = $this->createBot('123:watchdog-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subMinutes(45));

        $this->artisan('channel-poster:watchdog')
            ->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('channel-1', $this->publisher->publishes[0]['channel_chat_id']);
        $this->assertSame([], Cache::get('watchdog_state:red_bots', []));
        $this->assertNull(Cache::get('watchdog_state:bot:' . $bot->id));
    }

    public function test_dry_run_reports_red_without_side_effects(): void
    {
        $bot = $this->createBot('123:watchdog-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subMinutes(45));

        $this->artisan('channel-poster:watchdog', ['--dry-run' => true])
            ->assertFailed();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PENDING, $item->status);
        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull(Cache::get('watchdog_state:red_bots'));
        $this->assertNull(Cache::get('watchdog_state:bot:' . $bot->id));
    }

    public function test_failed_item_marks_bot_red_in_cache(): void
    {
        $bot = $this->createBot('123:watchdog-token');
        $this->createDestination($bot->id, 'tag1');
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour());
        $item->update(['status' => ChannelPosterQueue::STATUS_FAILED]);

        $this->artisan('channel-poster:watchdog')
            ->assertFailed();

        $this->assertSame('red', Cache::get('watchdog_state:bot:' . $bot->id));
        $this->assertContains($bot->id, Cache::get('watchdog_state:red_bots', []));
        $this->assertCount(0, $this->publisher->publishes);
    }

    public function test_recovered_bot_gets_ok_state_and_red_bots_cleared(): void
    {
        $bot = $this->createBot('123:watchdog-token');
        Cache::put('watchdog_state:red_bots', [$bot->id]);
        Cache::put('watchdog_state:bot:' . $bot->id, 'red');

        $this->artisan('channel-poster:watchdog')
            ->assertSuccessful();

        $this->assertSame('ok', Cache::get('watchdog_state:bot:' . $bot->id));
        $this->assertSame([], Cache::get('watchdog_state:red_bots', []));
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
            'text' => 'watchdog queued post',
            'scheduled_at' => $scheduledAt,
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => '111',
            'owner_origin' => 'bale',
        ]);
    }

    private function neutralizeAdminNotificationEnv(): void
    {
        foreach (['BOT_MOTHER_TOKEN_BALE', 'BOT_MOTHER_TOKEN_TELEGRAM', 'QURAN_HEFZ_BOT_TOKEN_BALE', 'QURAN_HEFZ_BOT_TOKEN_TELEGRAM', 'QURAN_HEFZ_BOT_TOKEN_GAP'] as $key) {
            $this->savedEnv[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function restoreAdminNotificationEnv(): void
    {
        foreach ($this->savedEnv as $key => $v) {
            if ($v['env'] !== null) {
                $_ENV[$key] = $v['env'];
            }
            if ($v['server'] !== null) {
                $_SERVER[$key] = $v['server'];
            }
            if ($v['getenv'] !== false) {
                putenv($key . '=' . $v['getenv']);
            }
        }
        $this->savedEnv = [];
    }
}
