<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentBroadcastJob as ContentBroadcastJobModel;
use App\Models\ContentUserProgress;
use App\Models\LibraryUserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that ContentBroadcastJob recipients are scoped to the
 * specific bot — a broadcast from bot A must never reach users
 * who are subscribed only to bot B.
 */
class ContentBroadcastJobScopingTest extends TestCase
{
    use RefreshDatabase;

    private function createBot(string $name, string $type = 'bale'): Bot
    {
        return Bot::create([
            'endpoint_id' => 'webhook-book-library',
            'bot_mother_id' => 1,
            'type' => $type,
            $type . '_bot_name' => $name,
            $type . '_bot_token' => 'test-token-' . $name,
        ]);
    }

    private function createUser(int $botId, string $chatId, string $origin = 'bale'): BotUsers
    {
        $user = BotUsers::create([
            'chat_id' => $chatId,
            'bot_id' => $botId,
            'status' => 'active',
            'origin' => $origin,
        ]);

        // Subscribe user to this bot
        LibraryUserSubscription::create([
            'bot_user_id' => $user->id,
            'bot_id' => $botId,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_broadcast_is_scoped_to_specific_bot_users_only(): void
    {
        // Bot A (the one broadcasting)
        $botA = $this->createBot('LibraryBotA');

        // Bot B (a different library bot)
        $botB = $this->createBot('LibraryBotB');

        // User subscribed to Bot A only
        $userA = $this->createUser($botA->id, '11111');

        // User subscribed to Bot B only
        $userB = $this->createUser($botB->id, '22222');

        // User subscribed to BOTH bots
        $userBoth = BotUsers::create([
            'chat_id' => '33333',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $userBoth->id,
            'bot_id' => $botA->id,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $userBoth->id,
            'bot_id' => $botB->id,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);

        // Create a broadcast job for Bot A
        $job = ContentBroadcastJobModel::create([
            'bot_id' => $botA->id,
            'target_filter' => 'all',
            'message_text' => 'Hello from Bot A',
            'status' => 'pending',
            'created_by_chat_id' => '99999',
        ]);

        // Invoke resolveRecipients via reflection
        $broadcastJob = \App\Jobs\ContentBroadcastJob::class;
        $jobInstance = new \App\Jobs\ContentBroadcastJob($job->id);
        $method = new \ReflectionMethod($jobInstance, 'resolveRecipients');
        $method->setAccessible(true);

        $recipients = $method->invoke($jobInstance, $job, 'bale');

        $recipientChatIds = $recipients->pluck('chat_id')->toArray();

        // Bot A's users should be included
        $this->assertContains('11111', $recipientChatIds, 'User subscribed to Bot A should receive the broadcast');
        $this->assertContains('33333', $recipientChatIds, 'User subscribed to both bots should receive Bot A broadcast');

        // Bot B's exclusive user should NOT be included
        $this->assertNotContains('22222', $recipientChatIds, 'User subscribed only to Bot B must NOT receive Bot A broadcast');

        // Total recipients should be exactly 2
        $this->assertCount(2, $recipientChatIds);
    }

    public function test_free_filter_only_returns_free_users_of_that_bot(): void
    {
        $botA = $this->createBot('LibFreeA');
        $botB = $this->createBot('LibFreeB');

        // Free user on Bot A
        $freeUserA = BotUsers::create([
            'chat_id' => '10001',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $freeUserA->id,
            'bot_id' => $botA->id,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);

        // Paid user on Bot A
        $paidUserA = BotUsers::create([
            'chat_id' => '10002',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $paidUserA->id,
            'bot_id' => $botA->id,
            'plan' => 'premium',
            'books_used' => 0,
            'books_limit' => 50,
            'status' => 'active',
        ]);

        // Free user on Bot B (should NOT appear)
        $freeUserB = BotUsers::create([
            'chat_id' => '10003',
            'bot_id' => $botB->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $freeUserB->id,
            'bot_id' => $botB->id,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);

        $job = ContentBroadcastJobModel::create([
            'bot_id' => $botA->id,
            'target_filter' => 'free',
            'message_text' => 'Free users only',
            'status' => 'pending',
            'created_by_chat_id' => '99999',
        ]);

        $jobInstance = new \App\Jobs\ContentBroadcastJob($job->id);
        $method = new \ReflectionMethod($jobInstance, 'resolveRecipients');
        $method->setAccessible(true);

        $recipients = $method->invoke($jobInstance, $job, 'bale');
        $chatIds = $recipients->pluck('chat_id')->toArray();

        $this->assertContains('10001', $chatIds, 'Free user of Bot A should be included');
        $this->assertNotContains('10002', $chatIds, 'Paid user of Bot A should be excluded from free filter');
        $this->assertNotContains('10003', $chatIds, 'Free user of Bot B must NOT leak into Bot A broadcast');
    }

    public function test_paid_filter_only_returns_paid_users_of_that_bot(): void
    {
        $botA = $this->createBot('LibPaidA');
        $botB = $this->createBot('LibPaidB');

        // Free user on Bot A
        $freeUserA = BotUsers::create([
            'chat_id' => '20001',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $freeUserA->id,
            'bot_id' => $botA->id,
            'plan' => 'free',
            'books_used' => 0,
            'books_limit' => 10,
            'status' => 'active',
        ]);

        // Paid user on Bot A
        $paidUserA = BotUsers::create([
            'chat_id' => '20002',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $paidUserA->id,
            'bot_id' => $botA->id,
            'plan' => 'premium',
            'books_used' => 0,
            'books_limit' => 50,
            'status' => 'active',
        ]);

        // Paid user on Bot B (should NOT appear)
        $paidUserB = BotUsers::create([
            'chat_id' => '20003',
            'bot_id' => $botB->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);
        LibraryUserSubscription::create([
            'bot_user_id' => $paidUserB->id,
            'bot_id' => $botB->id,
            'plan' => 'premium',
            'books_used' => 0,
            'books_limit' => 50,
            'status' => 'active',
        ]);

        $job = ContentBroadcastJobModel::create([
            'bot_id' => $botA->id,
            'target_filter' => 'paid',
            'message_text' => 'Paid users only',
            'status' => 'pending',
            'created_by_chat_id' => '99999',
        ]);

        $jobInstance = new \App\Jobs\ContentBroadcastJob($job->id);
        $method = new \ReflectionMethod($jobInstance, 'resolveRecipients');
        $method->setAccessible(true);

        $recipients = $method->invoke($jobInstance, $job, 'bale');
        $chatIds = $recipients->pluck('chat_id')->toArray();

        $this->assertNotContains('20001', $chatIds, 'Free user should not be in paid filter');
        $this->assertContains('20002', $chatIds, 'Paid user of Bot A should be included');
        $this->assertNotContains('20003', $chatIds, 'Paid user of Bot B must NOT leak into Bot A broadcast');
    }

    public function test_user_with_no_subscription_receives_nothing(): void
    {
        $botA = $this->createBot('LibNoSub');

        // User in bot_users but WITHOUT a LibraryUserSubscription
        $orphanUser = BotUsers::create([
            'chat_id' => '40001',
            'bot_id' => $botA->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);

        $job = ContentBroadcastJobModel::create([
            'bot_id' => $botA->id,
            'target_filter' => 'all',
            'message_text' => 'Test',
            'status' => 'pending',
            'created_by_chat_id' => '99999',
        ]);

        $jobInstance = new \App\Jobs\ContentBroadcastJob($job->id);
        $method = new \ReflectionMethod($jobInstance, 'resolveRecipients');
        $method->setAccessible(true);

        $recipients = $method->invoke($jobInstance, $job, 'bale');

        $this->assertCount(0, $recipients, 'User without a LibraryUserSubscription should not receive broadcasts');
    }
}
