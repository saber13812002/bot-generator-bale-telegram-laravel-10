<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\WeeklyReadInvitation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class WeeklyReadInviteTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        Schema::dropIfExists('weekly_read_invitations');
        Schema::create('weekly_read_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('destination_id');
            $table->unsignedTinyInteger('day_of_week')->default(5);
            $table->unsignedInteger('max_post_id');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_invited_at')->nullable();
            $table->timestamps();
            $table->index('destination_id');
            $table->index(['enabled', 'day_of_week']);
        });

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
        parent::tearDown();
    }

    public function test_dry_run_on_due_friday_publishes_nothing(): void
    {
        [$bot, $invitation] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));

        $this->artisan('weekly-read:invite', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull($invitation->refresh()->last_invited_at);
    }

    public function test_real_send_on_due_friday_publishes_invite_and_records_it(): void
    {
        [$bot, $invitation, $destination] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));

        $this->artisan('weekly-read:invite')
            ->assertSuccessful();

        $this->assertCount(1, $this->publisher->publishes);
        $publish = $this->publisher->publishes[0];
        $this->assertSame('channel-invite', $publish['channel_chat_id']);
        $this->assertSame('bale', $publish['platform']);
        $this->assertStringStartsWith('📖 دعوت به مطالعه‌ی مطلب شماره', $publish['text']);
        $this->assertStringContainsString('از کانال «کتابخانه مطالعاتی»', $publish['text']);
        $this->assertStringContainsString('bale://ch/12345', $publish['text']);

        $this->assertNotNull($invitation->refresh()->last_invited_at);
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'bot_id' => $bot->id,
            'destination_id' => $destination->id,
            'success' => true,
            'queue_id' => null,
        ]);
    }

    public function test_not_due_on_thursday_is_skipped(): void
    {
        [, $invitation] = $this->createFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-08 06:00:00'));

        $this->artisan('weekly-read:invite')
            ->assertSuccessful();

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertNull($invitation->refresh()->last_invited_at);
    }

    public function test_already_invited_this_week_is_skipped(): void
    {
        [, $invitation] = $this->createFixture();
        $invitation->update(['last_invited_at' => Carbon::parse('2026-10-09 05:00:00')]);
        Carbon::setTestNow(Carbon::parse('2026-10-09 06:00:00'));

        $this->artisan('weekly-read:invite')
            ->assertSuccessful();

        $this->assertCount(0, $this->publisher->publishes);
    }

    /**
     * @return array{0: Bot, 1: WeeklyReadInvitation, 2: ChannelPosterDestination}
     */
    private function createFixture(): array
    {
        $bot = Bot::create([
            'endpoint_id' => 'channel_poster_bot',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_token' => '123:invite-token',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => 111,
            'bale_webhook_is_set' => true,
        ]);

        $destination = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => 'channel-invite',
            'channel_title' => 'کتابخانه مطالعاتی',
            'channel_link' => 'bale://ch/12345',
            'is_active' => true,
        ]);

        $invitation = WeeklyReadInvitation::create([
            'destination_id' => $destination->id,
            'day_of_week' => 5,
            'max_post_id' => 100,
            'enabled' => true,
        ]);

        return [$bot, $invitation, $destination];
    }
}
