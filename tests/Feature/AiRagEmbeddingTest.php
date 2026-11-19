<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiEmbedding;
use App\Models\AiJob;
use App\Services\Ai\RagEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WS-I — RAG & Embedding readiness:
 * - dispatch embedding job (gate 'rag' + idempotency)
 * - executor: EmbeddingAdapter -> row ai_embeddings (vector/dimensions/model)
 * - retry/classification on provider errors
 * - search: cosine similarity ranking, never throws
 */
class AiRagEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Config::set('ai.flags', [
            'ai_global_enabled' => true,
            'ai_rag_enabled' => true,
        ]);
        // 15:00 Tehran — بیرون از پنجره‌ی deferral (job فوری)
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dispatch_creates_embedding_job(): void
    {
        $result = app(RagEmbeddingService::class)->embedSource('book_page_scan', 42, 'متن صفحه', 1);

        $this->assertTrue($result['created']);
        $this->assertSame('queued', $result['reason']);

        $job = AiJob::where('feature', 'embedding')->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame('book_page_scan', $job->payload['source_type']);
        $this->assertSame(42, $job->payload['source_id']);
        $this->assertSame('متن صفحه', $job->payload['content']);

        Queue::assertPushed(ProcessAiJob::class, fn ($j) => $j->jobId === $job->job_id);
    }

    public function test_executor_persists_embedding_on_success(): void
    {
        $job = $this->dispatchJob();
        $this->fakeEmbeddings(200, [[0.1, 0.2, 0.3, 0.4]]);

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);

        $row = AiEmbedding::where('source_type', 'book_page_scan')
            ->where('source_id', 42)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame([0.1, 0.2, 0.3, 0.4], $row->vector);
        $this->assertSame(4, $row->dimensions);
        $this->assertSame('text-embedding-3-small', $row->model);
        $this->assertSame('متن صفحه', $row->content);
        $this->assertSame(1, $row->bot_id);
        $this->assertSame('ai_embedding:'.$row->id, $job->result_reference);
    }

    public function test_executor_provider_500_retries_with_provider_error(): void
    {
        $job = $this->dispatchJob();
        $this->fakeEmbeddings(500);

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('provider_error', $job->error_code);
        $this->assertNotNull($job->due_at);
        $this->assertSame(0, AiEmbedding::count());
    }

    public function test_executor_401_classified_auth_failed(): void
    {
        $job = $this->dispatchJob();
        $this->fakeEmbeddings(401);

        (new ProcessAiJob($job->job_id))->handle();
        $job->refresh();

        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('auth_failed', $job->error_code);
        $this->assertSame(0, AiEmbedding::count());
    }

    public function test_duplicate_dispatch_is_idempotent(): void
    {
        app(RagEmbeddingService::class)->embedSource('book_page_scan', 42, 'متن صفحه', 1);
        $second = app(RagEmbeddingService::class)->embedSource('book_page_scan', 42, 'متن صفحه', 1);

        $this->assertFalse($second['created']);
        $this->assertSame(1, AiJob::where('feature', 'embedding')->count());
    }

    public function test_search_ranks_by_cosine_similarity(): void
    {
        AiEmbedding::create([
            'source_type' => 'book_page_scan',
            'source_id' => 1,
            'bot_id' => 1,
            'content' => 'صفحه اول',
            'model' => 'text-embedding-3-small',
            'dimensions' => 3,
            'vector' => [1, 0, 0],
            'status' => 'active',
        ]);
        AiEmbedding::create([
            'source_type' => 'book_page_scan',
            'source_id' => 2,
            'bot_id' => 1,
            'content' => 'صفحه دوم',
            'model' => 'text-embedding-3-small',
            'dimensions' => 3,
            'vector' => [0, 1, 0],
            'status' => 'active',
        ]);

        // query vector نزدیک به صفحه اول
        $this->fakeEmbeddings(200, [[0.9, 0.1, 0.0]]);

        $results = app(RagEmbeddingService::class)->search('پرسش کاربر', 'book_page_scan', 1);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]['source_id']);
        $this->assertSame('صفحه اول', $results[0]['content']);
        $this->assertGreaterThan(0.9, $results[0]['score']);
        $this->assertSame(2, $results[1]['source_id']);
        $this->assertLessThan($results[0]['score'], $results[1]['score']);
    }

    public function test_search_returns_empty_when_provider_down(): void
    {
        AiEmbedding::create([
            'source_type' => 'book_page_scan',
            'source_id' => 1,
            'content' => 'صفحه اول',
            'model' => 'text-embedding-3-small',
            'dimensions' => 3,
            'vector' => [1, 0, 0],
            'status' => 'active',
        ]);

        $this->fakeEmbeddings(500);

        $results = app(RagEmbeddingService::class)->search('پرسش کاربر', 'book_page_scan');

        $this->assertSame([], $results);
    }

    public function test_gate_closed_creates_no_job(): void
    {
        Config::set('ai.flags.ai_rag_enabled', false);

        $result = app(RagEmbeddingService::class)->embedSource('book_page_scan', 42, 'متن صفحه', 1);

        $this->assertFalse($result['created']);
        $this->assertSame('global_off', $result['reason']);
        $this->assertSame(0, AiJob::count());
        Queue::assertNothingPushed();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function dispatchJob(): AiJob
    {
        app(RagEmbeddingService::class)->embedSource('book_page_scan', 42, 'متن صفحه', 1);
        $job = AiJob::where('feature', 'embedding')->first();
        $this->assertNotNull($job);

        return $job;
    }

    private function fakeEmbeddings(int $status, array $vectors = []): void
    {
        $body = $status === 200
            ? [
                'data' => array_map(
                    fn (array $e, int $i) => ['index' => $i, 'embedding' => $e],
                    $vectors,
                    array_keys($vectors)
                ),
                'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
            ]
            : 'Internal Server Error';

        Http::fake([
            '*ai.ismc.ir/api/v1/embeddings*' => Http::response($body, $status),
        ]);
    }
}
