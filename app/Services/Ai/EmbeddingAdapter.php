<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * آداپتور Embedding — تولید بردارهای معنایی برای RAG
 * ----------------------------------------------------
 * endpoint: POST /v1/embeddings  (OpenAI-compatible)
 *
 * نکته مهم: طول بردار (dimensions) باید قبل از طراحی دیتابیس برداری
 * تأیید شود؛ این آداپتور در هر پاسخ `dimensions` را گزارش می‌کند
 * تا از تغییر ناگهانی ساختار در میانه راه جلوگیری شود.
 */
class EmbeddingAdapter
{
    protected AiHttp $http;
    protected string $model;

    public function __construct(?AiProvider $provider = null, ?string $model = null)
    {
        $this->http  = new AiHttp($provider);
        $this->model = $model
            ?? config('ai.embedding_model')
            ?? ($provider?->settings['embedding_model'] ?? null)
            ?? 'text-embedding-3-small';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * تولید embedding برای یک یا چند متن
     *
     * @param string|string[] $input
     * @return array{success: bool, embeddings: array, dimensions: ?int, usage: array, error_code: ?string}
     */
    public function embed(string|array $input, ?int $timeout = null): array
    {
        $timeout = $timeout ?: 60;

        try {
            $response = $this->http->pending($timeout)
                ->post($this->http->baseUrl() . '/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $input,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $embeddings = [];
                foreach ($data['data'] ?? [] as $item) {
                    $embeddings[$item['index'] ?? count($embeddings)] = $item['embedding'] ?? [];
                }

                $dimensions = count($embeddings) ? count(reset($embeddings)) : null;

                return [
                    'success'    => true,
                    'embeddings' => $embeddings,
                    'dimensions' => $dimensions,
                    'usage'      => $data['usage'] ?? [],
                    'error_code' => null,
                ];
            }

            $errorCode = match (true) {
                $response->status() === 401 || $response->status() === 403 => 'auth_failed',
                $response->status() === 404                                => 'model_not_found',
                $response->status() === 429                                => 'rate_limited',
                $response->status() >= 500                                => 'provider_error',
                default                                                    => 'http_error',
            };

            Log::warning('[EmbeddingAdapter] embed failed', [
                'status'  => $response->status(),
                'model'   => $this->model,
                'message' => AiErrorRedactor::redact(mb_substr($response->body(), 0, 300)),
            ]);

            return $this->fail($errorCode, AiHttp::errorMessage($response->status(), $response->body()));
        } catch (Throwable $e) {
            Log::error('[EmbeddingAdapter] embed exception', [
                'error' => AiHttp::exceptionMessage($e->getMessage()),
                'model' => $this->model,
            ]);

            return $this->fail('exception', AiHttp::exceptionMessage($e->getMessage()));
        }
    }

    protected function fail(string $errorCode, string $message): array
    {
        return [
            'success'    => false,
            'embeddings' => [],
            'dimensions' => null,
            'usage'      => [],
            'error_code' => $errorCode,
            'message'    => $message,
        ];
    }
}
