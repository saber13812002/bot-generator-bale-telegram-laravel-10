<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiFeedback;
use App\Models\AiJob;
use App\Models\Bot;
use App\Services\Ai\AiAlertService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — Production Monitoring (brief §6, §7, §8, §10):
 * - ذخیره‌ی usage/tokens روی ai_jobs (برای cost/monitoring)
 * - سری‌های ai_job_* در /api/metrics (Prometheus)
 * - AiAlertService با dedup (cooldown cache) + دستور ai:alerts-check
 * - جدول ai_feedback (پilot feedback، brief §8) + Nova read-only
 * - ai:rollout-status (ماتریس bot×capability، brief §3)
 * - ai:stage-report (فیلدهای گزارش مرحله‌ای، brief §10)
 */
class AiProductionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-metrics-secret-token-at-least-32-chars';

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 1) usage persistence (brief §6: token usage)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_ai_jobs_table_has_usage_column(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('ai_jobs', 'usage'));
    }

    public function test_process_ai_job_persists_usage_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'سلام!']]],
                'usage'   => ['total_tokens' => 23, 'prompt_tokens' => 5, 'completion_tokens' => 18],
            ], 200),
        ]);

        $job = $this->makeJob();
        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        $this->assertIsArray($job->usage, 'usage باید در job ذخیره شود');
        $this->assertSame(23, $job->usage['total_tokens']);
        $this->assertSame(5, $job->usage['prompt_tokens']);
        $this->assertSame(18, $job->usage['completion_tokens']);
    }

    public function test_failed_attempt_does_not_persist_usage(): void
    {
        Http::fake(['*' => Http::response('HTTP 503: Service unavailable', 500)]);

        $job = $this->makeJob();
        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertNull($job->usage, 'attempt ناموفق usage ذخیره نمی‌کند');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 2) /api/metrics — سری‌های ai_job_* (brief §6)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_metrics_endpoint_exposes_ai_job_series(): void
    {
        config()->set('ai.pricing.input_per_million', 1.0);
        config()->set('ai.pricing.output_per_million', 2.0);

        $bot = $this->makeBot();
        $now  = Carbon::now();

        // 3 job COMPLETED با latency مشخص (100/200/300 ثانیه — timestamp دیتابیس second-precision است) و usage
        foreach ([100, 200, 300] as $i => $sec) {
            $start = $now->copy()->subMinutes(30 - $i);
            $this->makeJob([
                'bot_id'       => $bot->id,
                'state'        => AiJob::STATE_COMPLETED,
                'started_at'   => $start,
                'completed_at' => $start->copy()->addSeconds($sec),
                'usage'        => ['total_tokens' => ($i + 1) * 10, 'prompt_tokens' => ($i + 1) * 10, 'completion_tokens' => 0],
            ]);
        }

        $this->makeJob(['bot_id' => $bot->id, 'state' => AiJob::STATE_DEFERRED]);
        $this->makeJob([
            'bot_id'      => $bot->id,
            'state'       => AiJob::STATE_FAILED,
            'error_code'  => 'provider_error',
            'completed_at' => $now,
        ]);

        $body = $this->get('/api/metrics?token=' . self::SECRET)->assertOk()->getContent();

        // counts per state
        $this->assertMatchesRegularExpression('/ai_job_total\{state="COMPLETED"\} 3/', $body);
        $this->assertMatchesRegularExpression('/ai_job_total\{state="DEFERRED"\} 1/', $body);
        $this->assertMatchesRegularExpression('/ai_job_total\{state="FAILED"\} 1/', $body);

        // per feature
        $this->assertMatchesRegularExpression('/ai_job_by_feature\{feature="llm_chat"\} 5/', $body);

        // dead letters + latency + tokens + cost (60 tokens × 1.0/M = 0.000060)
        $this->assertMatchesRegularExpression('/ai_job_dead_letter_total\{window_hours="24"\} 1/', $body);
        $this->assertMatchesRegularExpression('/ai_job_latency_avg_ms\{window_hours="24"\} 200000/', $body);
        $this->assertMatchesRegularExpression('/ai_job_latency_p95_ms\{window_hours="24"\} 300000/', $body);
        $this->assertMatchesRegularExpression('/ai_job_tokens_total\{window_hours="24"\} 60/', $body);
        $this->assertMatchesRegularExpression(
            '/ai_job_est_cost_total\{window_hours="24",currency="USD"\} 0\.000060/',
            $body
        );

        // queue depth (DEFERRED + RETRYING + PENDING + QUEUED)
        $this->assertMatchesRegularExpression('/ai_job_queue_depth 1/', $body);
    }

    public function test_metrics_without_ai_jobs_table_is_safe(): void
    {
        // اگر جدول نباشد، endpoint باید ۲۰۰ بدهد بدون سری ai_job (guard)
        // DDL در MySQL transaction RefreshDatabase را commit می‌کند؛ بعد از تست
        // جدول را بازسازی می‌کنیم تا تست‌های بعدی (که به ai_jobs وابسته‌اند) شکست نخورند.
        \Illuminate\Support\Facades\Schema::drop('ai_jobs');

        try {
            $this->get('/api/metrics?token=' . self::SECRET)
                ->assertOk()
                ->assertDontSee('ai_job_total');
        } finally {
            \Illuminate\Support\Facades\Schema::create('ai_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_id', 64)->unique();
                $table->string('idempotency_key', 128)->unique();
                $table->unsignedBigInteger('bot_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('feature', 64)->index();
                $table->string('priority', 32)->default('regular');
                $table->string('state', 32)->default('PENDING')->index();
                $table->string('provider', 64)->nullable();
                $table->string('model', 128)->nullable();
                $table->unsignedTinyInteger('attempt_number')->default(0);
                $table->timestamp('due_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('heartbeat_at')->nullable();
                $table->string('locked_by', 64)->nullable();
                $table->string('error_code', 64)->nullable();
                $table->text('result_reference')->nullable();
                $table->json('payload')->nullable();
                $table->json('usage')->nullable();
                $table->timestamps();
                $table->index(['state', 'due_at', 'priority', 'created_at']);
            });
        }
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 3) AiAlertService — dedup + cooldown (brief §7)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_alerts_healthy_system_triggers_nothing(): void
    {
        $this->assertSame([], AiAlertService::runWithDedup());
    }

    public function test_dead_letter_triggers_alert_and_dedups_within_cooldown(): void
    {
        $this->makeJob([
            'state'        => AiJob::STATE_FAILED,
            'error_code'   => 'provider_error',
            'completed_at' => Carbon::now(),
        ]);

        $first = AiAlertService::runWithDedup();
        $this->assertCount(1, $first);
        $this->assertStringContainsString('dead_letter', $first[0]['key']);
        $this->assertStringContainsString('provider_error', $first[0]['message']);

        // همان alert در cooldown → دوباره نمی‌آید
        $this->assertSame([], AiAlertService::runWithDedup());

        // بعد از پاک شدن dedup، دوباره فعال می‌شود
        Cache::flush();
        $this->assertCount(1, AiAlertService::runWithDedup());
    }

    public function test_queue_backlog_threshold_triggers(): void
    {
        config()->set('ai.alerts.queue_backlog_threshold', 2);

        $this->makeJob(['state' => AiJob::STATE_QUEUED]);
        $this->makeJob(['state' => AiJob::STATE_QUEUED]);
        $this->makeJob(['state' => AiJob::STATE_PENDING]);

        $alerts = AiAlertService::runWithDedup();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('queue_backlog', $alerts[0]['key']);
    }

    public function test_error_rate_triggers_above_threshold(): void
    {
        config()->set('ai.alerts.dead_letter_window_hours', 1); // FAILED کهنه‌ها را از dead-letter رها می‌کند
        config()->set('ai.alerts.error_rate_window_hours', 48);
        config()->set('ai.alerts.error_rate_min_volume', 5);
        $now = Carbon::now();

        for ($i = 0; $i < 5; $i++) {
            $this->makeJob(['state' => AiJob::STATE_COMPLETED, 'completed_at' => $now->copy()->subHours($i)]);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->makeJob([
                'state'        => AiJob::STATE_FAILED,
                'error_code'   => 'provider_error',
                'completed_at' => $now->copy()->subHours($i + 10), // قدیمی‌تر از dead-letter window
            ]);
        }

        $alerts = AiAlertService::runWithDedup();
        $keys   = array_column($alerts, 'key');
        $this->assertContains('error_rate', $keys, 'error rate 37.5%% باید بالاتر از threshold 25%% باشد');
        $this->assertNotContains('dead_letter:provider_error', $keys, 'FAILED کهنه‌تر از window dead-letter نیست');
    }

    public function test_alerts_check_command_registered_in_schedule(): void
    {
        $events = app(Schedule::class)->events();

        $event = collect($events)->first(
            fn (Event $e) => $e instanceof \Illuminate\Console\Scheduling\CallbackEvent === false
                && str_contains((string) $e->command ?? '', 'ai:alerts-check')
        );

        $this->assertNotNull($event, 'ai:alerts-check باید در schedule ثبت باشد');
    }

    public function test_alerts_check_command_runs_and_never_fails(): void
    {
        $this->makeJob([
            'state'        => AiJob::STATE_FAILED,
            'error_code'   => 'provider_error',
            'completed_at' => Carbon::now(),
        ]);

        $this->artisan('ai:alerts-check --dry-run')->assertExitCode(0);
        // دوباره (dedup) → باز هم 0
        $this->artisan('ai:alerts-check --dry-run')->assertExitCode(0);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 4) ai_feedback (brief §8)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_ai_feedback_table_and_model_roundtrip(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('ai_feedback'));

        $row = AiFeedback::create([
            'bot_id'    => 7,
            'customer_id' => 42,
            'feature'   => 'ocr',
            'category'  => 'bug',
            'comment'   => 'متن استخراج‌شده نادرست بود',
            'rating'    => 2,
        ]);

        $fresh = AiFeedback::find($row->id);
        $this->assertSame('new', $fresh->status, 'پیش‌فرض status باید new باشد');
        $this->assertSame('bug', $fresh->category);
        $this->assertSame(2, $fresh->rating);

        $fresh->forceFill(['status' => 'resolved', 'resolved_by' => 'admin'])->save();
        $this->assertSame('resolved', $fresh->refresh()->status);
    }

    public function test_nova_ai_feedback_resource_is_read_only(): void
    {
        $resource = new \App\Nova\AiFeedback();
        $req      = \Laravel\Nova\Http\Requests\NovaRequest::create('/nova-admin/ai-feedback', 'GET');

        $this->assertSame(\App\Models\AiFeedback::class, \App\Nova\AiFeedback::$model);

        $attrs = collect($resource->fields($req))->map(fn ($f) => $f->attribute)->all();
        $this->assertContains('feature', $attrs);
        $this->assertContains('comment', $attrs);
        $this->assertContains('rating', $attrs);

        $this->assertFalse(\App\Nova\AiFeedback::authorizedToCreate($req));
        $this->assertFalse($resource->authorizedToUpdate($req));
        $this->assertFalse($resource->authorizedToDelete($req));
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 5) ai:rollout-status — ماتریس bot×capability (brief §3, §4)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_rollout_status_reports_bot_capability_matrix(): void
    {
        config()->set('ai.flags.ai_global_enabled', true);
        config()->set('ai.flags.ai_ocr_enabled', false);

        $botA = $this->makeBot();
        $botA->setAiRolloutStage(1, 'test');
        $botA->setAiFlag('ocr', true, 'test'); // override: روشن حتی با global خاموش

        $botB = $this->makeBot(); // follow global

        $output = $this->runCommandJson('ai:rollout-status');

        $this->assertSame(3, $output['global_stage'], 'stage سراسری از config');
        $this->assertTrue($output['global_flags']['ai_global_enabled']);
        $this->assertFalse($output['global_flags']['ai_ocr_enabled']);

        $a = collect($output['bots'])->firstWhere('bot_id', $botA->id);
        $b = collect($output['bots'])->firstWhere('bot_id', $botB->id);

        $this->assertSame(1, $a['stage'], 'stage override bot A');
        $this->assertSame('on', $a['capabilities']['ocr'], 'bot override on باید حاکم باشد');
        $this->assertSame(3, $b['stage'], 'bot B follow → stage global');
        $this->assertSame('off', $b['capabilities']['ocr'], 'bot B follow + global off');
    }

    public function test_rollout_status_global_kill_switch_turns_everything_off(): void
    {
        config()->set('ai.flags.ai_global_enabled', false);
        config()->set('ai.flags.ai_ocr_enabled', true);

        $bot = $this->makeBot();
        $bot->setAiFlag('ocr', true, 'test');

        $output = $output = $this->runCommandJson('ai:rollout-status');
        $entry  = collect($output['bots'])->firstWhere('bot_id', $bot->id);

        $this->assertSame('off', $entry['capabilities']['ocr'], 'kill switch global باید بر bot override غلبه کند');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 6) ai:stage-report — فیلدهای brief §10
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_stage_report_contains_section_10_fields(): void
    {
        $bot = $this->makeBot();
        $bot->setAiTestGroup([77, 88], 'test');

        $now = Carbon::now();
        $this->makeJob([
            'bot_id'       => $bot->id,
            'state'        => AiJob::STATE_COMPLETED,
            'started_at'   => $now->copy()->subMinutes(10),
            'completed_at' => $now->copy()->subMinutes(9),
            'usage'        => ['total_tokens' => 100, 'prompt_tokens' => 60, 'completion_tokens' => 40],
        ]);
        $this->makeJob([
            'bot_id'       => $bot->id,
            'state'        => AiJob::STATE_FAILED,
            'error_code'   => 'provider_error',
            'completed_at' => $now,
        ]);
        AiFeedback::create(['bot_id' => $bot->id, 'feature' => 'ocr', 'category' => 'bug', 'comment' => 'x']);

        $report = $this->runCommandJson('ai:stage-report --stage=1');

        $this->assertSame(1, $report['stage']);
        $this->assertStringContainsString('Pilot', $report['stage_name']);

        // brief §10 fields
        $required = [
            'current_global_stage', 'allowlisted_users', 'bots_total', 'bots_by_stage',
            'jobs_24h', 'latency_24h', 'tokens_24h', 'est_cost_24h',
            'error_rate_24h', 'dead_letters_24h', 'feedback', 'alerts_active', 'gate_criteria',
        ];
        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $report, "فیلد §10 «{$field}» باید موجود باشد");
        }

        $this->assertSame(2, $report['allowlisted_users']);
        $this->assertSame(1, $report['bots_total']);
        $this->assertSame(100, $report['tokens_24h']);
        $this->assertSame(1, $report['dead_letters_24h']);
        $this->assertSame(1, $report['jobs_24h']['completed']);
        $this->assertSame(1, $report['jobs_24h']['failed']);
        $this->assertGreaterThan(0, $report['latency_24h']['avg_ms']);
        $this->assertSame(1, $report['feedback']['total']);
        $this->assertIsArray($report['gate_criteria']);
        $this->assertNotEmpty($report['gate_criteria']);
    }

    public function test_stage_report_defaults_to_global_stage(): void
    {
        $report = $this->runCommandJson('ai:stage-report');
        $this->assertSame(3, $report['stage'], 'بدون --stage باید stage global (config) باشد');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | helpers
    |──────────────────────────────────────────────────────────────────────
    */

    private static int $seq = 0;

    private function makeBot(): Bot
    {
        self::$seq++;
        $seq = self::$seq;

        return Bot::create([
            'telegram_bot_name' => "monitor-bot-{$seq}-" . uniqid(),
            'telegram_bot_token' => "tok{$seq}",
            'telegram_bot_status' => 'Active',
        ]);
    }

    private function makeJob(array $attrs = []): AiJob
    {
        self::$seq++;
        return AiJob::create(array_merge([
            'job_id'          => 'monitor-job-' . uniqid(),
            'idempotency_key' => 'monitor-key-' . uniqid(),
            'bot_id'          => 1,
            'customer_id'     => 100 + self::$seq,
            'feature'         => 'llm_chat',
            'priority'        => AiJob::PRIORITY_REGULAR,
            'state'           => AiJob::STATE_QUEUED,
            'provider'        => 'litellm',
            'model'           => 'qwen38',
            'payload'         => ['prompt' => 'سلام'],
        ], $attrs));
    }

    private function runCommandJson(string $command): array
    {
        if (! str_contains($command, '--json')) {
            $command .= ' --json';
        }

        // در Laravel 10، artisan() فقط (command, parameters) می‌گیرد و خروجی به
        // mock داخلی می‌رود؛ برای گرفتن JSON، مستقیم از Kernel::call استفاده می‌کنیم
        // (پارامتر سوم = output buffer واقعی).
        $this->app->offsetUnset(\Illuminate\Console\OutputStyle::class);
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
        $code   = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->call($command, [], $buffer);

        $this->assertSame(0, $code, "exit code «{$command}» باید 0 باشد");
        $raw  = trim($buffer->fetch());
        $data = json_decode($raw, true);

        $this->assertIsArray($data, "خروجی JSON نامعتبر از «{$command}»: {$raw}");

        return $data;
    }
}
