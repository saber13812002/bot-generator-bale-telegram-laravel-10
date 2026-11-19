<?php

namespace Tests\Feature\BookLibrary;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryDiscountCode;
use App\Models\LibraryUserSubscription;
use App\Services\LibraryMilestoneServiceImpl;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    /**
     * Runs against the fully-migrated schema (RefreshDatabase) and every
     * test is rolled back afterwards. It must NOT drop/recreate the shared
     * `bots`/`bot_users` tables: that DDL auto-commits and would corrupt
     * the schema for every later test in the same run (TopUsersSendMsg,
     * BotFileUpload, BotOwner dashboard, ...).
     */

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 111, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $subscription = $this->armSubscription($bot, $user, 'plan_100', 100, 100, 99);

        // Delivering the 100th book fires the win moment (the delivery path
        // increments books_used before calling registerDelivery)
        $subscription->increment('books_used');
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
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 222, 'bot_id' => $bot->id, 'origin' => 'bale']);
        $subscription = $this->armSubscription($bot, $user, 'plan_100', 100, 100, 99);

        // Two "concurrent" deliveries crossing the threshold (each increments
        // books_used before calling registerDelivery)
        $subscription->increment('books_used');
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'bale', 'chat-1');
        $subscription->increment('books_used');
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
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 333, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $subscription = $this->armSubscription($bot, $user, 'plan_100', 100, 100, 98);

        // One delivery only reaches book 99 — still below the 100 target
        $subscription->increment('books_used');
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
        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 444, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $subscription = $this->armSubscription($bot, $user, 'free', 3, null, 3);

        $subscription->increment('books_used');
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('free', $subscription->plan);
        $this->assertSame(0, LibraryDiscountCode::count());
    }

    public function test_disabled_rewards_are_inert(): void
    {
        config()->set('book_library.rewards.enabled', false);

        $bot = Bot::create(['bale_bot_token' => 'T', 'telegram_bot_token' => 'T']);
        $user = BotUsers::create(['chat_id' => 555, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $subscription = $this->armSubscription($bot, $user, 'plan_100', 100, 100, 100);

        $subscription->increment('books_used');
        $this->service()->registerDelivery($this->messengerMock(), $user, $bot->id, 'telegram', 'chat-1');

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame('plan_100', $subscription->plan);
        $this->assertSame(0, LibraryDiscountCode::count());
    }
}
