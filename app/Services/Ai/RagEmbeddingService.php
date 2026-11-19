<?php

namespace App\Services\Ai;

use App\Models\AiEmbedding;
use App\Models\AiJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WS-I — RAG & Embedding readiness
 * --------------------------------
 * لوله‌ی embedding: تریگر (gate + dispatch) ← اکزیکیوتور (Adapter + upsert)
 * ← جست‌وجوی cosine در PHP (بدون وابستگی به vector-DB).
 *
 * اصول:
 * - هرگز نمی‌اندازد: خطا در search() یعنی «نتیجه‌ی خالی» نه exception.
 * - خطا در اکزیکیوتور یعنی error_code مشخص برای retry/backoff صف AI.
 * - upsert بر اساس (source_type, source_id): تکرار embed همان ردیف را به‌روز می‌کند.
 */
class RagEmbeddingService
{
    /**
     * تریگر embed: درِ feature «rag» + dispatch idempotent به صف AI.
     *
     * @return array{created: bool, job: ?AiJob, reason: string}
     */
    public function embedSource(string $sourceType, int $sourceId, string $content, ?int $botId = null, ?string $model = null): array
    {
        $gate = (new AiFeatureGate())->check(AiFeatureGate::FEATURE_RAG, $botId);

        if (! $gate['allowed']) {
            return ['created' => false, 'job' => null, 'reason' => $gate['reason']];
        }

        $result = AiJobDispatcher::dispatch('embedding', [
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
            'content'     => $content,
            'model'       => $model,
        ], [
            'bot_id'      => $botId,
            'customer_id' => $sourceId,
            'user_id'     => null,
        ]);

        return [
            'created' => $result['created'],
            'job'     => $result['job'],
            'reason'  => $result['deferred'] ? 'deferred' : 'queued',
        ];
    }

    /**
     * اکزیکیوتور job embedding (از ProcessAiJob).
     */
    public function execute(AiJob $job): array
    {
        $payload = $job->payload ?? [];
        $content = (string) ($payload['content'] ?? '');

        if ($content === '') {
            return $this->fail('empty_content', 'No content to embed.');
        }

        $model   = isset($payload['model']) && $payload['model'] !== null ? (string) $payload['model'] : null;
        $adapter = new EmbeddingAdapter(null, $model);
        $result  = $adapter->embed($content);

        if (! $result['success']) {
            return $this->fail((string) $result['error_code'], (string) ($result['message'] ?? ''));
        }

        $vector = array_values($result['embeddings'])[0] ?? [];

        $row = AiEmbedding::updateOrCreate(
            [
                'source_type' => (string) $payload['source_type'],
                'source_id'   => (int) $payload['source_id'],
            ],
            [
                'bot_id'       => $job->bot_id,
                'content'      => $content,
                'model'        => $adapter->model(),
                'dimensions'   => (int) $result['dimensions'],
                'vector'       => $vector,
                'status'       => 'active',
            ]
        );

        return [
            'success'          => true,
            'error_code'       => null,
            'message'          => null,
            'result_reference' => 'ai_embedding:' . $row->id,
            'usage'            => $result['usage'] ?? [],
        ];
    }

    /**
     * جست‌وجوی معنایی: embed پرسش ← cosine روی ردیفهای active هم‌نوع.
     * هرگز نمی‌اندازد؛ خطای provider یا هر exception یعنی [].
     *
     * @return array<int, array{id: int, source_id: int, content: string, score: float}>
     */
    public function search(string $query, string $sourceType, ?int $botId = null, int $limit = 5): array
    {
        try {
            $q = (new EmbeddingAdapter())->embed($query);

            if (! $q['success']) {
                return [];
            }

            $qv = array_values($q['embeddings'])[0] ?? null;

            if (! $qv) {
                return [];
            }

            $rows = AiEmbedding::where('source_type', $sourceType)
                ->where('status', 'active')
                ->when($botId, fn ($qq) => $qq->where('bot_id', $botId))
                ->limit(500)
                ->get();

            return $rows
                ->map(fn ($r) => ['row' => $r, 'score' => $this->cosine($qv, (array) $r->vector)])
                ->filter(fn ($s) => $s['score'] > 0)
                ->sortByDesc('score')
                ->take($limit)
                ->values()
                ->map(fn ($s) => [
                    'id'        => $s['row']->id,
                    'source_id' => (int) $s['row']->source_id,
                    'content'   => $s['row']->content,
                    'score'     => $s['score'],
                ])
                ->all();
        } catch (Throwable $e) {
            Log::warning('RAG search failed (no results, never throws)', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * شباهت کسین دو بردار (ناهمسان‌طول → صفر برای فراتر از طول).
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;

        foreach (range(0, max(count($a), count($b)) - 1) as $i) {
            $x = (float) ($a[$i] ?? 0);
            $y = (float) ($b[$i] ?? 0);
            $dot += $x * $y;
            $na  += $x * $x;
            $nb  += $y * $y;
        }

        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    private function fail(string $errorCode, string $message): array
    {
        return [
            'success'          => false,
            'error_code'       => $errorCode,
            'message'          => $message,
            'result_reference' => null,
        ];
    }
}
