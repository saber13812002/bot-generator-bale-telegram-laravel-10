<?php

namespace Tests\Feature\BookLibrary;

use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryPlanRequest;
use App\Models\LibraryUserSubscription;
use App\Services\BookLibraryPlanServiceImpl;
use App\Services\LibraryPlanNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * Phase A fix: plan approval from Nova (bulk action AND single-item
 * three-dot menu) must notify the user. The notification is centralized
 * inside BookLibraryPlanServiceImpl::confirmPlanRequest(), origin-aware
 * (bale vs telegram token) and never throws.
 */
class PlanApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runs against the fully-migrated schema (RefreshDatabase) and every
     * test is rolled back afterwards. It must NOT drop/recreate the shared
     * `bots`/`bot_users` tables: that DDL auto-commits and would corrupt
     * the schema for every later test in the same run.
     */

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('fa');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * The approval entry-point: exactly what the Nova Approve action calls
     * for every selected model (bulk) and for a single three-dot approval.
     * The messenger factory is overridden so no real API call is made.
     *
     * @param array<int, array<string, mixed>> $sentMessages
     */
    private function confirmViaService(int $requestId, array &$sentMessages, ?string $approvedBy = null): array
    {
        $bookLibraryService = Mockery::mock(BookLibraryService::class);
        $bookLibraryService->shouldReceive('getOrCreateSubscription')->zeroOrMoreTimes();

        $service = new class($bookLibraryService, new LibraryPlanNotificationService(), $sentMessages) extends BookLibraryPlanServiceImpl {
            /** @var array<int, array<string, mixed>> */
            public array $sentMessages;

            public function __construct($bookLibraryService, $notificationService, array &$sentMessages)
            {
                $this->sentMessages = &$sentMessages;
                parent::__construct($bookLibraryService, $notificationService);
            }

            protected function createMessenger(string $token, ?string $origin): Telegram
            {
                $messenger = Mockery::mock(Telegram::class);
                $messenger->shouldReceive('sendMessage')->zeroOrMoreTimes()->andReturnUsing(function (array $content) {
                    $this->sentMessages[] = $content;
                    return [];
                });

                return $messenger;
            }
        };

        return $service->confirmPlanRequest($requestId, $approvedBy);
    }

    public function test_nova_confirmation_notifies_telegram_user_with_reward_teaser(): void
    {
        $bot = Bot::create(['bale_bot_token' => 'bale-token', 'telegram_bot_token' => 'tg-token']);
        $user = BotUsers::create(['chat_id' => 9001, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $request = LibraryPlanRequest::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => 'plan_100',
            'user_identifier' => '@some_user',
            'status' => 'pending',
        ]);

        $sentMessages = [];
        $result = $this->confirmViaService($request->id, $sentMessages, 'nova-admin@example.com');

        $this->assertTrue($result['success']);

        // The user was notified (the reported bug: no message was sent)
        $this->assertCount(1, $sentMessages);
        $this->assertSame(9001, $sentMessages[0]['chat_id']);
        $this->assertSame('html', $sentMessages[0]['parse_mode']);

        $text = $sentMessages[0]['text'];
        // Plan activation line
        $this->assertStringContainsString(trans('book_library.plan_activated'), $text);
        // Reward teaser: receive 100 → 200 free books
        $this->assertStringContainsString(trans('book_library.milestone_teaser', ['target' => 100, 'bonus' => 200]), $text);

        // Milestone armed in the DB for the win moment
        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame(100, (int) $subscription->reward_target);
        $this->assertNull($subscription->reward_granted_at);
        $this->assertTrue($subscription->hasArmedMilestone());
    }

    public function test_bale_origin_uses_bale_token_for_user_notification(): void
    {
        $bot = Bot::create(['bale_bot_token' => 'bale-token', 'telegram_bot_token' => 'tg-token']);
        $user = BotUsers::create(['chat_id' => 9002, 'bot_id' => $bot->id, 'origin' => 'bale']);
        $request = LibraryPlanRequest::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => 'plan_300',
            'user_identifier' => '@some_user',
            'status' => 'pending',
        ]);

        $sentMessages = [];
        $result = $this->confirmViaService($request->id, $sentMessages);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $sentMessages);
        $this->assertSame(9002, $sentMessages[0]['chat_id']);

        // plan_300 is in rewards.plans → teaser with 300/600
        $this->assertStringContainsString(
            trans('book_library.milestone_teaser', ['target' => 300, 'bonus' => 600]),
            $sentMessages[0]['text']
        );

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
            ->where('bot_id', $bot->id)
            ->first();

        $this->assertSame(300, (int) $subscription->reward_target);
    }

    public function test_unlimited_confirmation_clears_milestone(): void
    {
        $bot = Bot::create(['bale_bot_token' => 'bale-token', 'telegram_bot_token' => 'tg-token']);
        $user = BotUsers::create(['chat_id' => 9003, 'bot_id' => $bot->id, 'origin' => 'bale']);

        // User previously had an armed plan_100 milestone
        LibraryUserSubscription::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => 'plan_100',
            'books_used' => 10,
            'books_limit' => 100,
            'reward_target' => 100,
            'status' => 'active',
        ]);

        $request = LibraryPlanRequest::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => 'unlimited',
            'user_identifier' => '@some_user',
            'status' => 'pending',
        ]);

        $sentMessages = [];
        $result = $this->confirmViaService($request->id, $sentMessages);

        $this->assertTrue($result['success']);
        // Still notified, but without a teaser (unlimited arms nothing)
        $this->assertCount(1, $sentMessages);
        $this->assertStringNotContainsString('هدیه ویژه', $sentMessages[0]['text']);

        $subscription = LibraryUserSubscription::where('bot_user_id', $user->id)
                    ->where('bot_id', $bot->id)
                    ->first();

        $this->assertSame('unlimited', $subscription->plan);
        $this->assertNull($subscription->reward_target);
        $this->assertNull($subscription->reward_bonus);
        $this->assertNull($subscription->reward_granted_at);
    }

    public function test_confirmation_of_unknown_request_is_rejected_without_notification(): void
    {
        $sentMessages = [];
        $result = $this->confirmViaService(999999, $sentMessages);

        $this->assertFalse($result['success']);
        $this->assertCount(0, $sentMessages);
    }

    public function test_missing_bot_token_skips_notification_but_still_confirms(): void
    {
        $bot = Bot::create(['bale_bot_token' => null, 'telegram_bot_token' => null]);
        $user = BotUsers::create(['chat_id' => 9004, 'bot_id' => $bot->id, 'origin' => 'telegram']);
        $request = LibraryPlanRequest::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'plan' => 'plan_100',
            'user_identifier' => '@some_user',
            'status' => 'pending',
        ]);

        $sentMessages = [];
        $result = $this->confirmViaService($request->id, $sentMessages);

        // Confirmation still succeeds; notification is skipped gracefully
        $this->assertTrue($result['success']);
        $this->assertCount(0, $sentMessages);

        $request->refresh();
        $this->assertSame('confirmed', $request->status);
    }
}
