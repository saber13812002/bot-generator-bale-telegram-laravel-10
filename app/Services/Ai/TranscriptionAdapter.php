<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * آداپتور Transcription (ASR) — تبدیل صدا به متن
 * ----------------------------------------------
 * از endpoint استاندارد OpenAI-compatible:
 *   POST /v1/audio/transcriptions  (multipart/form-data: file + model)
 *
 * LiteLLM برای مدل‌های Whisper (whisper-1 / whisper-large-v3) یا
 * هر مدل ASR دیگری که در proxy ثبت شده باشد این endpoint را سرو می‌کند.
 *
 * اگر endpoint وجود نداشته باشد (404/405)، error_code = 'endpoint_missing'
 * برمی‌گردد تا فراخواننده ASR را «تزئینی» فعال نکند.
 */
class TranscriptionAdapter
{
    protected AiHttp $http;
    protected string $model;

    public function __construct(?AiProvider $provider = null, ?string $model = null)
    {
        $this->http  = new AiHttp($provider);
        $this->model = $model
            ?? config('ai.asr_model')
            ?? ($provider?->settings['asr_model'] ?? null)
            ?? 'whisper-1';
    }

    /**
     * تبدیل فایل صوتی به متن
     *
     * @param string $audioFilePath  مسیر محلی فایل (mp3/ogg/wav/m4a/opus)
     * @return array{success: bool, text: string, usage: array, error_code: ?string}
     */
    public function transcribe(string $audioFilePath, ?string $language = null, ?int $timeout = null): array
    {
        $timeout = $timeout ?: 120;

        if (! is_file($audioFilePath) || filesize($audioFilePath) < 1) {
            return $this->fail('file_not_found', 'فایل صوتی یافت نشد یا خالی است.');
        }

        try {
            $response = $this->http->pending($timeout)
                ->asMultipart()
                ->attach('file', file_get_contents($audioFilePath), basename($audioFilePath))
                ->post($this->http->baseUrl() . '/v1/audio/transcriptions', [
                    'model' => $this->model,
                    'response_format' => 'json',
                    'language'  => $language ?: 'fa',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success'    => true,
                    'text'       => trim($data['text'] ?? ''),
                    'usage'      => $data['usage'] ?? [],
                    'error_code' => null,
                ];
            }

            $errorCode = match (true) {
                in_array($response->status(), [404, 405], true) => 'endpoint_missing',
                $response->status() === 401 || $response->status() === 403 => 'auth_failed',
                $response->status() === 429 => 'rate_limited',
                $response->status() >= 500  => 'provider_error',
                default => 'http_error',
            };

            Log::warning('[TranscriptionAdapter] transcribe failed', [
                'status'  => $response->status(),
                'model'   => $this->model,
                'message' => AiErrorRedactor::redact(mb_substr($response->body(), 0, 300)),
            ]);

            return $this->fail($errorCode, AiHttp::errorMessage($response->status(), $response->body()));
        } catch (Throwable $e) {
            Log::error('[TranscriptionAdapter] transcribe exception', [
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
            'text'       => '',
            'usage'      => [],
            'error_code' => $errorCode,
            'message'    => $message,
        ];
    }
}
