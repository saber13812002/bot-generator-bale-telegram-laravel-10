<?php

namespace Tests\Feature;

use App\Interfaces\Services\GrowthMessengerFactory;
use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\GrowthAssistantMessage;
use App\Models\GrowthDailyCheckin;
use App\Models\GrowthProfile;
use App\Models\GrowthProgram;
use App\Models\GrowthResponse;
use App\Models\GrowthTemplate;
use App\Models\ProUser;
use App\Services\Ai\AiFeatureGate;
use App\Services\GrowthCompanion\GrowthAssistantService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeGrowthMessenger;
use Tests\Fakes\FakeGrowthMessengerFactory;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

/**
 * WS-H — Growth Companion AI MVP: assistant reply for free text after onboarding.
 *
 * Trigger (webhook, after onboarding, consent given, gate open) →
 * GrowthAssistantMessage(user) + AiJob(feature='growth_companion') →
 * ProcessAiJob executor → AiProviderService::chat →
 * GrowthAssistantMessage(assistant) + messenger reply.
 *
 * AI failure must never break the normal bot flow:
 * - gate closed / no consent / pre-onboarding → home card / onboarding intact
 * - executor errors → retry with classified code, no fake assistant output,
 *   terminal failure → assistant_unavailable fallback.
 */
class GrowthAssistantTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    private FakeGrowthMessenger $messenger;

    private string $token = '123:growth-token';

    private string $chatId = '555';

    /** Time outside the 09–14 Tehran deferral window (15:00 Tehran). */
    private const T_OUTSIDE = '2026-11-14 11:30:00';

    /** Time inside the 09–14 Tehran deferral window (10:00 Tehran); flush due 14:00 = 10:30 UTC. */
    private const T_INSIDE = '2026-11-14 06:30:00';

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

    // ── 1. Trigger: free text after onboarding dispatches an AI job ─────────

    public function test_free_text_after_onboarding_dispatches_growth_job(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        $this->setConsent(true);

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('امروز خیلی خسته بودم')
        );

        $response->assertOk();
        $this->assertSame(
            trans('growth_companion.assistant_thinking'),
            (string) $this->messenger->lastText()
        );

        $profile = GrowthProfile::where('bot_id', $this->lastBotId())->first();
        $this->assertNotNull($profile);

        // user message persisted
        $userMsg = GrowthAssistantMessage::where('growth_profile_id', $profile->id)->where('role', 'user')->first();
        $this->assertNotNull($userMsg);
        $this->assertSame('امروز خیلی خسته بودم', $userMsg->content);

        // job created in ai_jobs, QUEUED, pushed to technical queue
        $job = AiJob::where('feature', 'growth_companion')->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame((int) $profile->id, $job->customer_id);
        $this->assertSame('growth_companion', $job->feature);
        $this->assertSame('امروز خیلی خسته بودم', $job->payload['user_text']);
        $this->assertSame((int) $profile->id, $job->payload['profile_id']);
        $this->assertSame((int) $userMsg->id, $job->payload['message_id']);

        // normal flow untouched: no state row, no question opened
        $this->assertSame(0, BotUserState::count());
        $this->assertSame(0, GrowthResponse::count());

        Queue::assertPushed(
            ProcessAiJob::class,
            fn (ProcessAiJob $j) => $j->jobId === $job->job_id
        );
    }

    // ── 2. Gate closed → home card intact, no job ───────────────────────────

    public function test_free_text_gate_closed_keeps_home_card_and_no_job(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags([]); // all capability flags off
        $this->setConsent(true);

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('سلام رشدیار')
        );

        $response->assertOk();
        $this->assertStringContainsString(
            trans('growth_companion.home_title'),
            (string) $this->messenger->lastText()
        );
        $this->assertStringContainsString(
            trans('growth_companion.use_buttons'),
            (string) $this->messenger->lastText()
        );
        $this->assertSame(0, AiJob::count());
        $this->assertSame(0, GrowthAssistantMessage::count());
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    // ── 3. No consent → home card intact, no job ─────────────────────────────

    public function test_free_text_without_ai_consent_keeps_home_card(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        // consent stays default false

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('سلام رشدیار')
        );

        $response->assertOk();
        $this->assertStringContainsString(
            trans('growth_companion.home_title'),
            (string) $this->messenger->lastText()
        );
        $this->assertSame(0, AiJob::count());
        $this->assertSame(0, GrowthAssistantMessage::count());
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    // ── 4. Pre-onboarding free text → onboarding, not assistant ─────────────

    public function test_free_text_before_onboarding_starts_onboarding(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->flags(['growth_companion']);

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('سلام')
        );

        $response->assertOk();
        $this->assertStringContainsString(
            trans('growth_companion.ask_focus'),
            (string) $this->messenger->lastText()
        );
        $this->assertSame(0, AiJob::count());
        $this->assertSame(0, GrowthAssistantMessage::count());
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    // ── 5. Executor success → assistant row + delivered reply ───────────────

    public function test_executor_persists_assistant_reply_and_delivers_it(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $job = $this->triggerAssistantJob('امروز سر کارم خسته شدم');

        $reply = 'می‌فهمم. امروز فقط یک قدم کوچک برای خودت بگذار: پنج دقیقه پیاده‌روی یا یک استراحت کوتاه. ترجیح می‌دهی صبح یا شب وقت بیشتری داشته باشی؟';
        $this->fakeChat(200, $reply);

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        // user message row is id 1 (created at trigger), assistant row is id 2
        $this->assertSame('assistant_message:2', $job->result_reference);

        $profile = GrowthProfile::first();
        $assistant = GrowthAssistantMessage::where('growth_profile_id', $profile->id)
            ->where('role', 'assistant')
            ->first();
        $this->assertNotNull($assistant);
        $this->assertSame($reply, $assistant->content);
        $this->assertSame(['total_tokens' => 42], (array) $assistant->usage);

        // delivered via the growth messenger
        $this->assertSame($reply, (string) $this->messenger->lastText());
    }

    // ── 6. Executor 500 → RETRYING provider_error, no fake output ───────────

    public function test_executor_provider_500_retries_with_provider_error(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $job = $this->triggerAssistantJob('امروز بد بود');

        $this->fakeChat(500, 'Internal Server Error');

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('provider_error', $job->error_code);
        $this->assertNotNull($job->due_at);

        $profile = GrowthProfile::first();
        $this->assertSame(0, GrowthAssistantMessage::where('role', 'assistant')->count());
        $userMsg = GrowthAssistantMessage::where('role', 'user')->first();
        $this->assertSame('provider_error', $userMsg->error);

        // no reply delivered to the user
        $this->assertNotSame(
            trans('growth_companion.assistant_unavailable'),
            (string) $this->messenger->lastText()
        );
    }

    // ── 7. Executor 401 → auth_failed ───────────────────────────────────────

    public function test_executor_401_classified_auth_failed(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $job = $this->triggerAssistantJob('سلام');

        $this->fakeChat(401, 'Invalid API key');

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('auth_failed', $job->error_code);
    }

    // ── 8. Executor empty content → empty_result, no fake text ──────────────

    public function test_executor_empty_content_is_empty_result_not_fake_text(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $job = $this->triggerAssistantJob('خسته‌ام');

        $this->fakeChat(200, '   ');

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('empty_result', $job->error_code);
        $this->assertSame(0, GrowthAssistantMessage::where('role', 'assistant')->count());
    }

    // ── 9. Terminal failure → FAILED + assistant_unavailable fallback ───────

    public function test_terminal_failure_sends_assistant_unavailable_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $job = $this->triggerAssistantJob('امروز بد بود');
        $job->forceFill(['attempt_number' => 4])->save(); // next handle() = 5th = max

        $this->fakeChat(500, 'Internal Server Error');

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_FAILED, $job->state);
        $this->assertSame('provider_error', $job->error_code);
        $this->assertSame(
            trans('growth_companion.assistant_unavailable'),
            (string) $this->messenger->lastText()
        );
        $userMsg = GrowthAssistantMessage::where('role', 'user')->first();
        $this->assertSame('provider_error', $userMsg->error);
    }

    // ── 10. Pro user inside deferral window → immediate ─────────────────────

    public function test_pro_user_in_deferred_window_is_immediate(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_INSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        $this->setConsent(true);
        $this->makePro();

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('سلام')
        );

        $response->assertOk();
        $job = AiJob::where('feature', 'growth_companion')->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame(AiJob::PRIORITY_PRO, $job->priority);
        $this->assertNull($job->due_at);

        Queue::assertPushed(
            ProcessAiJob::class,
            fn (ProcessAiJob $j) => $j->jobId === $job->job_id
        );
    }

    // ── 11. Regular user inside deferral window → DEFERRED to 14:00 ─────────

    public function test_regular_user_in_deferred_window_is_deferred_until_14_tehran(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_INSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        $this->setConsent(true);

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('سلام')
        );

        $response->assertOk();
        $this->assertSame(
            trans('growth_companion.assistant_thinking'),
            (string) $this->messenger->lastText()
        );

        $job = AiJob::where('feature', 'growth_companion')->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJob::STATE_DEFERRED, $job->state);
        $this->assertSame(AiJob::PRIORITY_DEFERRED, $job->priority);
        $this->assertSame('2026-11-14 10:30:00', $job->due_at->format('Y-m-d H:i:s'));

        Queue::assertNotPushed(ProcessAiJob::class);
    }

    // ── 12. Context injection: prompt carries checkin, focus, user text ─────

    public function test_prompt_contains_checkin_focus_and_user_text(): void
    {
        Carbon::setTestNow(Carbon::parse(self::T_OUTSIDE, 'UTC'));
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        $this->setConsent(true);

        $profile = GrowthProfile::first();
        GrowthDailyCheckin::create([
            'growth_profile_id' => $profile->id,
            'day_key' => app(\App\Interfaces\Services\GrowthCompanionService::class)->dayKey($profile),
            'energy' => 4,
            'mood' => 'tired',
            'sleep_hours' => 5,
            'moved' => false,
            'evening_note' => 'درد کمر داشتم',
        ]);

        $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('چرا بی‌حالم؟')
        );
        $job = AiJob::where('feature', 'growth_companion')->first();
        $this->assertNotNull($job);

        $this->fakeChat(200, 'پاسخ آزمایشی');

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();
        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);

        $request = Http::recorded(
            fn ($req, $res) => str_contains($req->url(), 'v1/chat/completions')
        )->last()[0];
        $this->assertNotNull($request);

        $content = $request->data()['messages'][0]['content'];
        $this->assertStringContainsString('چرا بی‌حالم؟', $content);   // user text
        $this->assertStringContainsString('درد کمر داشتم', $content); // checkin evening_note
        $this->assertStringContainsString('سلامت', $content);          // active focus (program name)
    }

    // ── helpers ──────────────────────────────────────────────────────────────

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

    private function completeOnboarding(): void
    {
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:f:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:i:bal'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:t:skip'));
    }

    private function setConsent(bool $value): void
    {
        $profile = GrowthProfile::first();
        $profile->forceFill(['ai_consent' => $value])->save();
    }

    private function makePro(): void
    {
        $botUser = BotUsers::where('chat_id', $this->chatId)->first();
        ProUser::create([
            'bot_user_id' => $botUser->id,
            'bot_id' => $this->lastBotId(),
            'status' => 'active',
            'purchase_confirmed_at' => now(),
        ]);
    }

    private function lastBotId(): int
    {
        return (int) Bot::first()->id;
    }

    /**
     * Create bot + complete onboarding + consent, then trigger one assistant
     * message via the webhook. Returns the created AiJob.
     */
    private function triggerAssistantJob(string $text): AiJob
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->flags(['growth_companion']);
        $this->setConsent(true);

        $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate($text)
        );

        $job = AiJob::where('feature', 'growth_companion')->first();
        $this->assertNotNull($job, 'expected a growth_companion AiJob to be created');

        return $job;
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

    private function textUpdate(string $text): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'message' => [
                'message_id' => random_int(1, 999),
                'from' => ['id' => (int) $this->chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $this->chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }

    private function callbackUpdate(string $data): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'callback_query' => [
                'id' => 'cb'.random_int(1, 999),
                'from' => ['id' => (int) $this->chatId],
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => (int) $this->chatId, 'type' => 'private'],
                ],
                'data' => $data,
            ],
        ];
    }
}
