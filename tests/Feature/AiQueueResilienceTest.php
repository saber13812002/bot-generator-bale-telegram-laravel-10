<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Services\Ai\AiJobDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WS-D — تاب‌آوری صف AI:
 * - سیاست زمان (09–14 Tehran → DEFERRED؛ Pro ۲۴ساعته)
 * - idempotency (acceptance scenario 4)
 * - ماشین حالت و retry/backoff/dead-letter
 * - flush deferred بدون تکرار (acceptance scenario 1)
 * - recovery از worker مرده (acceptance scenario 7)
 *
 * توجه: با Queue::fake() jobهای فنی ثبت می‌شوند تا بدون worker واقعی
 * رفتار dispatch/push تست شود.
 */
class AiQueueResilienceTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = ['prompt' => 'سلام', 'max_tokens' => 50];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | سیاست زمان (Asia/Tehran)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_regular_job_in_morning_window_is_deferred_until_14_tehran(): void
    {
        // 10:00 Tehran = 06:30 UTC — داخل پنجره 09–14
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));

        $result = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 100,
        ]);

        $this->assertTrue($result['created']);
        $this->assertTrue($result['deferred']);
        $this->assertSame(AiJob::STATE_DEFERRED, $result['job']->state);
        $this->assertSame(AiJob::PRIORITY_DEFERRED, $result['job']->priority);
        $this->assertNotNull($result['job']->due_at);

        // due_at باید 14:00 Tehran همان روز (10:30 UTC) باشد
        $this->assertSame('2026-11-14 10:30:00', $result['job']->due_at->format('Y-m-d H:i:s'));

        // job فنی نباید زودتر از flush در صف برود
        Queue::assertNothingPushed();

        Carbon::setTestNow();
    }

    public function test_regular_job_outside_deferred_window_is_immediate(): void
    {
        // 15:00 Tehran = 11:30 UTC — بعد از 14:00
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $result = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 100,
        ]);

        $this->assertFalse($result['deferred']);
        $this->assertSame(AiJob::STATE_QUEUED, $result['job']->state);
        $this->assertSame(AiJob::PRIORITY_REGULAR, $result['job']->priority);
        $this->assertNull($result['job']->due_at);
        Queue::assertPushed(ProcessAiJob::class, 1);

        Carbon::setTestNow();
    }

    public function test_pro_job_is_immediate_even_in_deferred_window(): void
    {
        // 10:00 Tehran — پنجره deferred، ولی Pro ۲۴ ساعته
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));

        $result = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 200, 'is_pro' => true,
        ]);

        $this->assertFalse($result['deferred']);
        $this->assertSame(AiJob::PRIORITY_PRO, $result['job']->priority);
        $this->assertSame(AiJob::STATE_QUEUED, $result['job']->state);
        Queue::assertPushed(ProcessAiJob::class, 1);

        Carbon::setTestNow();
    }

    public function test_regular_job_before_09_tehran_is_immediate(): void
    {
        // 07:00 Tehran = 03:30 UTC — قبل از پنجره
        Carbon::setTestNow(Carbon::parse('2026-11-14 03:30:00', 'UTC'));

        $result = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 300,
        ]);

        $this->assertFalse($result['deferred']);
        $this->assertSame(AiJob::STATE_QUEUED, $result['job']->state);

        Carbon::setTestNow();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Idempotency (acceptance scenario 4: duplicate → one job)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_duplicate_dispatch_creates_only_one_job(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $first = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 100,
        ]);
        $second = AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, [
            'bot_id' => 1, 'customer_id' => 100,
        ]);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created'], 'dispatch تکراری باید created=false برگرداند');
        $this->assertSame($first['job']->job_id, $second['job']->job_id);
        $this->assertSame(1, AiJob::count(), 'فقط یک رکورد باید وجود داشته باشد');

        // فقط یک job فنی push شده
        Queue::assertPushed(ProcessAiJob::class, 1);

        Carbon::setTestNow();
    }

    public function test_same_user_different_feature_creates_separate_jobs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        AiJobDispatcher::dispatch('llm_chat', self::PAYLOAD, ['bot_id' => 1, 'customer_id' => 100]);
        AiJobDispatcher::dispatch('ocr', self::PAYLOAD, ['bot_id' => 1, 'customer_id' => 100]);

        $this->assertSame(2, AiJob::count());

        Carbon::setTestNow();
    }

    public function test_idempotency_key_includes_bot_customer_and_payload(): void
    {
        $keyA = AiJob::idempotencyKeyFor('llm_chat', 1, 100, null, ['prompt' => 'a']);
        $keyB = AiJob::idempotencyKeyFor('llm_chat', 1, 100, null, ['prompt' => 'a']);
        $keyC = AiJob::idempotencyKeyFor('llm_chat', 1, 200, null, ['prompt' => 'a']);
        $keyD = AiJob::idempotencyKeyFor('llm_chat', 1, 100, null, ['prompt' => 'b']);

        $this->assertSame($keyA, $keyB, 'همان ورودی → همان key');
        $this->assertNotSame($keyA, $keyC, 'customer متفاوت → key متفاوت');
        $this->assertNotSame($keyA, $keyD, 'payload متفاوت → key متفاوت');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | ماشین حالت و retry
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_process_ai_job_completes_successfully(): void
    {
        $this->mockChatSuccess();

        $job = $this->makeAiJob(['prompt' => 'سلام']);
        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        $this->assertSame(1, $job->attempt_number);
        $this->assertNotNull($job->completed_at);
        $this->assertNull($job->error_code);
    }

    public function test_process_ai_job_retries_on_provider_error_with_backoff(): void
    {
        $this->mockChatFailure('HTTP 429: Rate limit reached');

        $job = $this->makeAiJob();
        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('rate_limited', $job->error_code);
        $this->assertSame(1, $job->attempt_number);
        $this->assertNotNull($job->due_at, 'backoff باید due_at آینده بگذارد');
        $this->assertTrue($job->due_at->isFuture());
    }

    public function test_process_ai_job_becomes_dead_letter_after_max_attempts(): void
    {
        config()->set('ai.queue.max_attempts', 2);
        $this->mockChatFailure('HTTP 503: Service unavailable');

        // attempt 1
        $job = $this->makeAiJob();
        (new ProcessAiJob($job->job_id))->handle();
        $this->assertSame(AiJob::STATE_RETRYING, $job->refresh()->state);

        // attempt 2 (آخرین) → FAILED
        $job->refresh()->forceFill(['state' => AiJob::STATE_QUEUED, 'due_at' => null])->save();
        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_FAILED, $job->state, 'بعد از max attempts باید FAILED شود');
        $this->assertSame('provider_unavailable', $job->error_code);
        $this->assertSame(2, $job->attempt_number);
        $this->assertNotNull($job->completed_at);
    }

    public function test_process_ai_job_skips_terminal_states(): void
    {
        $this->mockChatSuccess();

        $job = $this->makeAiJob();
        $job->markCancelled();

        (new ProcessAiJob($job->job_id))->handle();

        // execute نشده → هیچ درخواست HTTP جدیدی نباید برود
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame(AiJob::STATE_CANCELLED, $job->refresh()->state);
        $this->assertSame(0, $job->attempt_number, 'attempt_number نباید روی job ترمینال زیاد شود');
    }

    public function test_process_ai_job_unknown_feature_is_retryable_then_failed(): void
    {
        config()->set('ai.queue.max_attempts', 1);

        $job = $this->makeAiJob();
        $job->feature = 'unknown_future_feature';
        $job->save();

        (new ProcessAiJob($job->job_id))->handle();

        $this->assertSame(AiJob::STATE_FAILED, $job->refresh()->state);
        $this->assertSame('feature_not_supported', $job->error_code);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | flush deferred (acceptance scenario 1: no lost, no duplicate)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_flush_deferred_releases_due_jobs_once(): void
    {
        // دو job deferred با due_at گذشته + یک job با due_at آینده
        $this->makeAiJob([], AiJob::STATE_DEFERRED, AiJob::PRIORITY_DEFERRED, now()->subMinute());
        $this->makeAiJob([], AiJob::STATE_DEFERRED, AiJob::PRIORITY_DEFERRED, now()->subMinute());
        $this->makeAiJob([], AiJob::STATE_DEFERRED, AiJob::PRIORITY_DEFERRED, now()->addHour());

        $this->artisan('ai:queue-flush-deferred')->assertExitCode(0);

        $this->assertSame(2, AiJob::byState(AiJob::STATE_QUEUED)->count(), 'فقط jobهای سررسیدگشته flush شوند');
        $this->assertSame(1, AiJob::byState(AiJob::STATE_DEFERRED)->count(), 'job آینده باید DEFERRED بماند');
        Queue::assertPushed(ProcessAiJob::class, 2);

        // flush دوم → بدون تکرار (state دیگر DEFERRED نیست)
        $this->artisan('ai:queue-flush-deferred')->assertExitCode(0);
        $this->assertSame(2, AiJob::byState(AiJob::STATE_QUEUED)->count());
        Queue::assertPushed(ProcessAiJob::class, 2, false);
    }

    public function test_flush_deferred_expires_past_expiry(): void
    {
        $job = $this->makeAiJob([], AiJob::STATE_DEFERRED, AiJob::PRIORITY_DEFERRED, now()->subMinute());
        $job->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('ai:queue-flush-deferred')->assertExitCode(0);

        $this->assertSame(AiJob::STATE_EXPIRED, $job->refresh()->state);
        Queue::assertNothingPushed();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | recovery از worker مرده (acceptance scenario 7)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_recover_stale_requeues_dead_worker_job(): void
    {
        $job = $this->makeAiJob();
        $job->markProcessing('worker-1:999');
        // heartbeat ۳۰ دقیقه پیش → کهنه (stale_after_minutes=20)
        $job->forceFill(['heartbeat_at' => now()->subMinutes(30)])->save();

        $this->artisan('ai:queue-recover-stale')->assertExitCode(0);

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('stale_worker_lost', $job->error_code);
        Queue::assertPushed(ProcessAiJob::class, 1);
    }

    public function test_recover_stale_exhausts_to_failed_after_max_attempts(): void
    {
        config()->set('ai.queue.max_attempts', 3);

        $job = $this->makeAiJob();
        $job->forceFill(['attempt_number' => 3])->save();
        $job->markProcessing('worker-1:999');
        $job->forceFill(['heartbeat_at' => now()->subMinutes(30)])->save();

        $this->artisan('ai:queue-recover-stale')->assertExitCode(0);

        $this->assertSame(AiJob::STATE_FAILED, $job->refresh()->state);
        $this->assertSame('stale_exhausted', $job->error_code);
    }

    public function test_recover_stale_marks_expired_jobs(): void
    {
        $job = $this->makeAiJob();
        $job->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('ai:queue-recover-stale')->assertExitCode(0);

        $this->assertSame(AiJob::STATE_EXPIRED, $job->refresh()->state);
    }

    public function test_fresh_processing_job_is_not_touched(): void
    {
        $job = $this->makeAiJob();
        $job->markProcessing('worker-1:999'); // heartbeat تازه

        $this->artisan('ai:queue-recover-stale')->assertExitCode(0);

        $this->assertSame(AiJob::STATE_PROCESSING, $job->refresh()->state);
        Queue::assertNothingPushed();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | commands و ساختار
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_ai_queue_status_reports_states(): void
    {
        $this->makeAiJob(); // QUEUED
        $job = $this->makeAiJob();
        $job->markCompleted('ref-1');

        $this->artisan('ai:queue-status')
            ->expectsOutputToContain('COMPLETED')
            ->assertExitCode(0);
    }

    public function test_queue_config_section_is_sane(): void
    {
        $this->assertSame('database', config('ai.queue.connection'));
        $this->assertSame('ai', config('ai.queue.queue'));
        $this->assertGreaterThanOrEqual(1, (int) config('ai.queue.tries'));
        $this->assertIsArray(config('ai.queue.backoff'));
        $this->assertSame(0, AiJob::PRIORITY_ORDER[AiJob::PRIORITY_PRO], 'Pro باید بالاترین اولویت را داشته باشد');
    }

    public function test_jobs_and_ai_jobs_tables_exist_after_migrate(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('jobs'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('ai_jobs'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('failed_jobs'));
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Helpers
    |──────────────────────────────────────────────────────────────────────
    */

    private function makeAiJob(
        array $payload = self::PAYLOAD,
        ?string $state = null,
        ?string $priority = null,
        ?\DateTimeInterface $dueAt = null
    ): AiJob {
        static $seq = 0;
        $seq++;

        $job = AiJob::create([
            'job_id'          => 'test-job-' . uniqid(),
            'idempotency_key' => 'test-key-' . uniqid(),
            'bot_id'          => 1,
            'customer_id'     => 100 + $seq,
            'feature'         => 'llm_chat',
            'priority'        => $priority ?? AiJob::PRIORITY_REGULAR,
            'state'           => $state ?? AiJob::STATE_QUEUED,
            'provider'        => 'litellm',
            'model'           => 'qwen38',
            'due_at'          => $dueAt,
            'payload'         => $payload,
        ]);

        return $job;
    }

    /**
     * ماک chat موفق (Http::fake)
     */
    private function mockChatSuccess(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => 'سلام!']]],
                'usage'   => ['total_tokens' => 10],
            ], 200),
        ]);
    }

    private function mockChatFailure(string $body): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response($body, 500),
        ]);
    }
}
