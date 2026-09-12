<?php

namespace Tests\Feature\BookLibrary;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryDiscountCode;
use App\Models\LibraryUserSubscription;
use App\Services\LibraryMilestoneServiceImpl;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * Milestone reward engine — "buy N, receive N → 2N free books +
 * an auto-activated 100% discount code (library free to the end)".
 *
 * "Listening" is not verified: books_used reaching reward_target is
 * the milestone. The grant must fire exactly once (idempotent).
 */
class MilestoneRewardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setUpLibraryTables(): void
    {
        Schema::dropIfExists('library_discount_codes');
        Schema::dropIfExists('library_user_subscriptions');
        Schema::dropIfExists('bot_users');
        Schema::dropIfExists('bots');

        Schema::create('bots', function ($table) {
            $table->id();
            $table->string('bale_bot_token')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_users', function ($table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('origin')->default('telegram');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Matches 2026_06_15_100000_create_library_tables.php
        // + 2026_09_10_000001_add_milestone_rewards_to_library_tables.php
        Schema::create('library_user_subscriptions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('plan', 20)->default('free');
            $table->unsignedInteger('books_used')->default(0);
            $table->unsignedInteger('books_limit')->default(3);
            $table->unsignedInteger('reward_target')->nullable();
            $table->unsignedInteger('reward_bonus')->nullable();
            $table->timestamp('reward_granted_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['bot_user_id', 'bot_id']);
        });

        Schema::create('library_discount_codes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('code', 40)->unique();
            $table->unsignedTinyInteger('percent')->default(100);
            $table->unsignedBigInteger('display_amount')->default(0);
            $table->string('source', 30)->default('milestone');
            $table->boolean('auto_activated')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['bot_user_id', 'bot_id']);
        });
    }

    private function armSubscription(
        Bot $bot,
        BotUsers $user,
        string $plan,
        int $limit,
        ?int $target,
        int $used
    ): LibraryUserSubscription {
        $subscription = LibraryUserSubscription::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => $plan,
            'books_used' => $used,
            'books_limit' => $limit,
            'status' => 'active',
        ]);

        if ($target !== null) {
            $subscription->armMilestone($target);
            $subscription->save();
        }

        return $subscription;
    }

    private function messengerMock(): Telegram
    {
        $messenger = Mockery::mock(Telegram::class);
        $messenger->shouldReceive('ChatID')->andReturn('chat-1');
        $messenger->shouldReceive('sendMessage')->zeroOrMoreTimes()->andReturn([]);

        return $messenger;
    }

    private function service(): LibraryMilestoneServiceImpl
    {
        return new LibraryMilestoneServiceImpl();
    }

    public function test_milestone_grants_unlimited_plus_auto_activated_code_when_target_book_received(): void
    {
        $this->setUpLibraryTables();
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 111, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $this->armSubscription($bot, $user, 'plan_100', 100, 100, 99);

        // Delivering the 100th book fires the win moment
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('unlimited', $subscription->plan);
        $this->assertSame(999999, (int) $subscription->books_limit);
        $this->assertSame(200, (int) $subscription->reward_bonus);
        $this->assertNotNull($subscription->reward_granted_at);

        $code = LibraryDiscountCode::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertNotNull($code);
        $this->assertSame(100, (int) $code->percent);
        $this->assertTrue($code->auto_activated);
        $this->assertNotNull($code->activated_at);
        $this->assertSame(5000000, (int) $code->display_amount);
        $this->assertStringStartsWith('LIB100-', $code->code);
    }

    public function test_milestone_grant_is_idempotent_across_repeated_deliveries(): void
    {
        $this->setUpLibraryTables();
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 222, 'bot_id' => $bot->id, 'origin' => 'bale']);
        $this->armSubscription($bot, $user, 'plan_100', 100, 100, 99);

        // Two "concurrent" deliveries crossing the threshold
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'bale', 'chat-1');
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'bale', 'chat-1');

        $this->assertSame(1, LibraryDiscountCode::where('bot_user_id', $user->id)->count());

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('unlimited', $subscription->plan);
        $this->assertSame(200, (int) $subscription->reward_bonus);
        $this->assertNotNull($subscription->reward_granted_at);
    }

    public function test_no_grant_before_the_target_book_is_received(): void
    {
        $this->setUpLibraryTables();
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 333, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $this->armSubscription($bot, $user, 'plan_100', 100, 100, 98);

        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('plan_100', $subscription->plan);
        $this->assertNull($subscription->reward_granted_at);
        $this->assertSame(0, LibraryDiscountCode::count());
    }

    public function test_no_grant_when_no_milestone_is_armed(): void
    {
        $this->setUpLibraryTables();
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 444, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $this->armSubscription($bot, $user, 'free', 3, null, 3);

        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('free', $subscription->plan);
        $this->assertSame(0, LibraryDiscountCode::count());
    }

    public function test_disabled_rewards_are_inert(): void
    {
        $this->setUpLibraryTables();
        config()->set('book_library.rewards.enabled', false);

        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 555, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $this->armSubscription($bot, $user, 'plan_100', 100, 100, 100);

        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('plan_100', $subscription->plan);
        $this->assertSame(0, LibraryDiscountCode::count());
    }
}
