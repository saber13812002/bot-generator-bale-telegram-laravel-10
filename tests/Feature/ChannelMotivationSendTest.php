<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelMotivationalSchedule;
use App\Models\ChannelPosterDestination;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesAiProviderSqlite;
use Tests\UsesChannelPosterSqlite;

class ChannelMotivationSendTest extends TestCase
{
    use UsesAiProviderSqlite;
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        $this->setUpAiProvidersTable();
        $this->createActiveAiProvider();

        Schema::dropIfExists('channel_motivational_schedules');
        Schema::create('channel_motivational_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('destination_id');
            $table->unsignedTinyInteger('day_of_week')->default(5);
            $table->string('frequency', 16)->default('weekly');
            $table->text('prompt')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('sent_texts')->nullable();
            $table->timestamps();
            $table->index('destination_id');
            $table->index(['enabled', 'day_of_week', 'frequency']);
        });

        $this->neutralizeAdminNotificationEnv();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );

        Carbon::setTestNow(null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        $this->restoreAdminNotificationEnv();
        parent::tearDown();
    }

    public function test_dry_run_on_due_friday_skips_llm_and_publish(): void
    {
        [$bot, $schedule] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));
        Http::fake(fn () => Http::response([]));

        $this->artisan('channel-motivation:send', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull($schedule->refresh()->last_sent_at);
        Http::assertNothingSent();
    }

    public function test_llm_text_is_posted_with_sparkle_and_recorded(): void
    {
        [$bot, $schedule] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'هر روز یک قدم کوچک به سوی هدف بزرگ.']]],
            'usage' => ['total_tokens' => 10],
        ], 200));

        $this->artisan('channel-motivation:send')
            ->assertSuccessful();

        $this->assertCount(1, $this->publisher->publishes);
        $publish = $this->publisher->publishes[0];
        $this->assertSame('channel-mot', $publish['channel_chat_id']);
        $this->assertSame('bale', $publish['platform']);
        $this->assertSame('✨ هر روز یک قدم کوچک به سوی هدف بزرگ.', $publish['text']);

        $this->assertNotNull($schedule->refresh()->last_sent_at);
        $this->assertContains('هر روز یک قدم کوچک به سوی هدف بزرگ.', $schedule->recentSentTexts(5));

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);

            return str_contains($request->url(), '/v1/chat/completions')
                && $data['max_tokens'] === 150
                && $data['temperature'] === 0.8
                && str_contains($data['messages'][0]['content'], 'نویسنده');
        });
    }

    public function test_llm_failure_fails_run_and_skips_publish(): void
    {
        [$bot, $schedule] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));
        Http::fake(fn () => Http::response(['error' => 'boom'], 500));

        $this->artisan('channel-motivation:send')
            ->assertFailed();

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull($schedule->refresh()->last_sent_at);
    }

    public function test_not_due_on_thursday_is_skipped(): void
    {
        [$bot, $schedule] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-08 06:00:00'));
        Http::fake(fn () => Http::response([]));

        $this->artisan('channel-motivation:send')
            ->assertSuccessful();

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull($schedule->refresh()->last_sent_at);
        Http::assertNothingSent();
    }

    /**
     * @return array{0: Bot, 1: ChannelMotivationalSchedule}
     */
    private function createFixture(): array
    {
        $bot = Bot::create([
            'endpoint_id' => 'channel_poster_bot',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_token' => '123:motivation-token',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => 111,
            'bale_webhook_is_set' => true,
        ]);

        $destination = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => 'channel-mot',
            'channel_title' => 'کانال انگیزشی',
            'is_active' => true,
        ]);

        $schedule = ChannelMotivationalSchedule::create([
            'destination_id' => $destination->id,
            'day_of_week' => 5,
            'frequency' => 'weekly',
            'enabled' => true,
        ]);

        return [$bot, $schedule];
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
