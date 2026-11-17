<?php

namespace Tests\Feature;

use App\Interfaces\Services\GrowthMessengerFactory;
use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\GrowthAssistantMessage;
use App\Models\GrowthProfile;
use App\Models\GrowthTemplate;
use App\Services\Ai\AiFeatureGate;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeGrowthMessenger;
use Tests\Fakes\FakeGrowthMessengerFactory;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

/**
 * M9 Task 4 — Growth Companion: FULL multi-user isolation (mission phase C).
 *
 * Two different users on the SAME bot must never leak data across each other:
 *  - assistant messages / AI jobs are scoped per growth profile (per bot user);
 *  - the LLM prompt for user A must never contain user B's text (or vice versa);
 *  - replies are delivered only to the sender's chat;
 *  - consent is per user: a non-consenting user never dispatches a job.
 *
 * `GrowthAssistantService::buildPrompt` is already scoped on growth_profile_id;
 * these tests PIN that contract so any future refactoring that introduces a
 * cross-user leak fails loudly.
 */
class GrowthAssistantIsolationTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    private FakeGrowthMessenger $messenger;

    /** @var array<int, string> */
    private array $llmRequests = [];

    private string $token = '444:isolation-token';

    /** User A. */
    private const CHAT_A = 555;

    /** User B (same bot). */
    private const CHAT_B = 666;

    /** Time outside the 09–14 Tehran deferral window (15:00 Tehran). */
    private const T_OUTSIDE = '2026-11-14 11:30:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGrowthCompanionTables();
        app()->setLocale('fa');
        Queue::fake();
        AiFeatureGate::clearHealthCache();

        $this->messenger = new FakeGrowthMessenger();
        $this->app->instance(
            GrowthMessengerFactory::class,
            new FakeGrowthMessengerFactory($this->messenger)
        );

        $this->seedHealthTemplate();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── 1. Two users, same bot → jobs, prompts, rows and delivery fully isolated ──

    public function test_two_users_same_bot_are_fully_isolated(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->onboard(self::CHAT_A);
        $this->onboard(self::CHAT_B);
        $this->flags(['growth_companion']);
        $this->consentFor(self::CHAT_A, true);
        $this->consentFor(self::CHAT_B, true);

        $jobA = $this->triggerFor(self::CHAT_A, 'امروز خیلی خسته بودم');
        $jobB = $this->triggerFor(self::CHAT_B, 'مشکل خواب دارم');

        $profileA = $this->profileFor(self::CHAT_A);
        $profileB = $this->profileFor(self::CHAT_B);
        $this->assertNotSame((int) $profileA->id, (int) $profileB->id);

        // two distinct jobs, each tied to its own profile
        $this->assertSame(2, AiJob::where('feature', 'growth_companion')->count());
        $this->assertNotSame((int) $jobA->customer_id, (int) $jobB->customer_id);
        $this->assertSame((int) $profileA->id, (int) $jobA->customer_id);
        $this->assertSame((int) $profileB->id, (int) $jobB->customer_id);
        $this->assertSame((int) $profileA->id, (int) $jobA->payload['profile_id']);
        $this->assertSame((int) $profileB->id, (int) $jobB->payload['profile_id']);

        // execute A → assistant row ONLY on A's profile, reply ONLY to chat A
        $this->fakeChatSequence('replyA-پاسخ', 'replyB-پاسخ');
        (new ProcessAiJob($jobA->job_id))->handle();
        $jobA->refresh();

        $this->assertSame(AiJob::STATE_COMPLETED, $jobA->state);
        $this->assertSame(
            1,
            GrowthAssistantMessage::where('growth_profile_id', $profileA->id)
                ->where('role', 'assistant')->count(),
            'assistant reply must be persisted on A\'s profile'
        );
        $this->assertSame(
            0,
            GrowthAssistantMessage::where('growth_profile_id', $profileB->id)
                ->where('role', 'assistant')->count(),
            'no assistant row may appear on B\'s profile'
        );
        $this->assertContains('replyA-پاسخ', $this->textsSentTo(self::CHAT_A));
        $this->assertNotContains('replyA-پاسخ', $this->textsSentTo(self::CHAT_B));

        // execute B → assistant row ONLY on B's profile, reply ONLY to chat B
        (new ProcessAiJob($jobB->job_id))->handle();
        $jobB->refresh();

        $this->assertSame(AiJob::STATE_COMPLETED, $jobB->state);
        $this->assertSame(
            1,
            GrowthAssistantMessage::where('growth_profile_id', $profileB->id)
                ->where('role', 'assistant')->count(),
            'assistant reply must be persisted on B\'s profile'
        );
        $this->assertContains('replyB-پاسخ', $this->textsSentTo(self::CHAT_B));
        $this->assertNotContains('replyB-پاسخ', $this->textsSentTo(self::CHAT_A));
    }

    // ── 2. LLM request bodies never contain the other user's text ───────────────

    public function test_llm_request_never_contains_other_users_text(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->onboard(self::CHAT_A);
        $this->onboard(self::CHAT_B);
        $this->flags(['growth_companion']);
        $this->consentFor(self::CHAT_A, true);
        $this->consentFor(self::CHAT_B, true);

        $jobA = $this->triggerFor(self::CHAT_A, 'امروز خیلی خسته بودم');
        $jobB = $this->triggerFor(self::CHAT_B, 'مشکل خواب دارم');

        $this->fakeChatRecording('replyA-پاسخ');
        (new ProcessAiJob($jobA->job_id))->handle();
        $bodyA = $this->llmRequests[0] ?? '';

        $this->fakeChatRecording('replyB-پاسخ');
        (new ProcessAiJob($jobB->job_id))->handle();
        $bodyB = $this->llmRequests[1] ?? '';

        // request bodies are json_encode'd (ASCII \uXXXX escapes) → decode first
        $decodedA = json_decode($bodyA, true) ?? [];
        $userA = (string) ($decodedA['messages'][0]['content'] ?? '');
        $decodedB = json_decode($bodyB, true) ?? [];
        $userB = (string) ($decodedB['messages'][0]['content'] ?? '');

        $this->assertStringContainsString('خسته بودم', $userA, 'A\'s LLM prompt must contain A\'s text');
        $this->assertStringNotContainsString('مشکل خواب', $userA, 'A\'s LLM prompt must NOT contain B\'s text');
        $this->assertStringContainsString('مشکل خواب', $userB, 'B\'s LLM prompt must contain B\'s text');
        $this->assertStringNotContainsString('خسته بودم', $userB, 'B\'s LLM prompt must NOT contain A\'s text');
    }

    // ── 3. Ambiguous short message is still dispatched and answered ─────────────

    public function test_ambiguous_short_message_still_dispatches_and_replies(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->onboard(self::CHAT_A);
        $this->flags(['growth_companion']);
        $this->consentFor(self::CHAT_A, true);

        $response = $this->postJson($this->webhookUrl(), $this->textUpdate('بله', self::CHAT_A));

        $response->assertOk();
        $job = AiJob::where('feature', 'growth_companion')
            ->where('payload->profile_id', (int) $this->profileFor(self::CHAT_A)->id)
            ->first();
        $this->assertNotNull($job, 'expected a growth_companion AiJob for the ambiguous message');

        // thinking message was sent to the user's chat
        $this->assertContains(
            trans('growth_companion.assistant_thinking'),
            $this->textsSentTo(self::CHAT_A)
        );
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame('بله', $job->payload['user_text']);

        $this->fakeChat(200, 'بله؟ درباره چی؟');
        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        $this->assertContains('بله؟ درباره چی؟', $this->textsSentTo(self::CHAT_A));
    }

    // ── 4. Consent is per user ───────────────────────────────────────────────────

    public function test_consent_is_per_user(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->onboard(self::CHAT_A);
        $this->onboard(self::CHAT_B);
        $this->flags(['growth_companion']);
        $this->consentFor(self::CHAT_A, true);
        $this->consentFor(self::CHAT_B, false);

        // B (no consent) → home-card path, NO job, NO assistant rows
        $response = $this->postJson($this->webhookUrl(), $this->textUpdate('مشکل خواب دارم', self::CHAT_B));
        $response->assertOk();
        $this->assertNotContains(
            trans('growth_companion.assistant_thinking'),
            $this->textsSentTo(self::CHAT_B),
            'non-consenting user must not get the thinking message'
        );
        // the home card is a multi-line message that CONTAINS the home title
        $this->assertTrue(
            collect($this->textsSentTo(self::CHAT_B))
                ->contains(fn ($t) => str_contains((string) $t, trans('growth_companion.home_title'))),
            'non-consenting user falls back to the home card'
        );
        $this->assertSame(0, AiJob::count());
        $this->assertSame(0, GrowthAssistantMessage::count());

        // A (consent) → still dispatches normally
        $job = $this->triggerFor(self::CHAT_A, 'امروز خیلی خسته بودم');
        $this->assertSame((int) $this->profileFor(self::CHAT_A)->id, (int) $job->customer_id);
        $this->assertSame(1, AiJob::count());

        // and B still has zero assistant rows
        $this->assertSame(
            0,
            GrowthAssistantMessage::where('growth_profile_id', $this->profileFor(self::CHAT_B)->id)->count()
        );
    }

    // ── helpers (chat-parameterized copies from GrowthAssistantTest) ─────────────

    private function flags(array $on = []): void
    {
        Config::set('ai.flags', [
            'ai_global_enabled'           => true,
            'ai_llm_enabled'              => in_array('llm', $on, true),
            'ai_ocr_enabled'              => in_array('ocr', $on, true),
            'ai_asr_enabled'              => in_array('asr', $on, true),
            'ai_rag_enabled'              => in_array('rag', $on, true),
            'ai_tts_enabled'              => in_array('tts', $on, true),
            'ai_growth_companion_enabled' => in_array('growth_companion', $on, true),
        ]);
    }

    private function createBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => 'webhook-growth-companion',
            'language_code' => 'fa',
            'bale_bot_token' => $this->token,
            'bot_mother_id' => 1,
        ]);
    }

    private function webhookUrl(): string
    {
        return '/api/webhook-growth-companion?origin=bale&token='.$this->token;
    }

    /** Full onboarding for one chat: /start + focus + intensity + time(skip). */
    private function onboard(int $chatId): void
    {
        $url = $this->webhookUrl();
        $this->postJson($url, $this->textUpdate('/start', $chatId));
        $this->postJson($url, $this->callbackUpdate('gc:f:health', $chatId));
        $this->postJson($url, $this->callbackUpdate('gc:i:bal', $chatId));
        $this->postJson($url, $this->callbackUpdate('gc:t:skip', $chatId));
    }

    private function consentFor(int $chatId, bool $value): void
    {
        $profile = $this->profileFor($chatId);
        $profile->forceFill(['ai_consent' => $value])->save();
    }

    /**
     * Growth profile of the bot user behind a chat (profile per bot user —
     * exactly how GrowthCompanionController resolves it via getOrCreateProfile).
     */
    private function profileFor(int $chatId): GrowthProfile
    {
        $botUser = BotUsers::where('chat_id', $chatId)->first();
        $this->assertNotNull($botUser, "expected a bot user for chat $chatId");

        $profile = GrowthProfile::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($profile, "expected a growth profile for chat $chatId");

        return $profile;
    }

    /**
     * Post one free-text message for the chat and return the growth job that
     * was created for THAT user's profile (never another user's job).
     */
    private function triggerFor(int $chatId, string $text): AiJob
    {
        $this->postJson($this->webhookUrl(), $this->textUpdate($text, $chatId));

        $job = AiJob::where('feature', 'growth_companion')
            ->where('payload->profile_id', (int) $this->profileFor($chatId)->id)
            ->first();
        $this->assertNotNull($job, "expected a growth_companion AiJob for chat $chatId");

        return $job;
    }

    private function fakeChat(int $status, string $content): void
    {
        Http::fake([
            '*ai.ismc.ir/api/v1/chat/completions*' => Http::response(
                [
                    'choices' => [['message' => ['content' => $content]]],
                    'usage' => ['total_tokens' => 42],
                ],
                $status
            ),
        ]);
    }

    /**
     * Laravel 10 Http::fake stubs ACCUMULATE (a second Http::fake does not
     * replace the first) — consecutive LLM calls therefore need ONE sequence
     * fake instead of repeated fake() calls.
     */
    private function fakeChatSequence(string ...$contents): void
    {
        $sequence = Http::sequence();
        foreach ($contents as $content) {
            $sequence->push([
                'choices' => [['message' => ['content' => $content]]],
                'usage' => ['total_tokens' => 42],
            ]);
        }

        Http::fake(['*ai.ismc.ir/api/v1/chat/completions*' => $sequence]);
    }

    /** fakeChat + capture of every LLM request body, in order. */
    private function fakeChatRecording(string $content): void
    {
        Http::fake([
            '*ai.ismc.ir/api/v1/chat/completions*' => function (Request $request) use ($content) {
                $this->llmRequests[] = $request->body();

                return Http::response(
                    [
                        'choices' => [['message' => ['content' => $content]]],
                        'usage' => ['total_tokens' => 42],
                    ]
                );
            },
        ]);
    }

    /** All texts delivered to one chat (in send order). */
    private function textsSentTo(int|string $chatId): array
    {
        return array_map(
            fn (array $m) => $m['text'],
            $this->messenger->textsSentTo($chatId)
        );
    }

    private function seedHealthTemplate(): void
    {
        $template = GrowthTemplate::create([
            'slug' => 'health',
            'name' => 'سلامت',
            'is_system' => true,
        ]);
        $template->questions()->create([
            'question_key' => 'daily_health_reflection',
            'intent' => 'daily_health_checkin',
            'domain' => 'health',
            'difficulty' => 1,
            'default_frequency' => 'daily',
            'variants' => [
                ['locale' => 'fa', 'body' => 'امروز برای سلامت چه کردی؟'],
                ['locale' => 'en', 'body' => 'What did you do for your health today?'],
            ],
        ]);
    }

    private function textUpdate(string $text, int $chatId): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'message' => [
                'message_id' => random_int(1, 999),
                'from' => ['id' => $chatId, 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }

    private function callbackUpdate(string $data, int $chatId): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'callback_query' => [
                'id' => 'cb'.random_int(1, 999),
                'from' => ['id' => $chatId],
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => $chatId, 'type' => 'private'],
                ],
                'data' => $data,
            ],
        ];
    }
}
