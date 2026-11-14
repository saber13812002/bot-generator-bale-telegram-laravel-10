<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * آداپتور TTS — تبدیل متن به صدا
 * --------------------------------
 * endpoint: POST /v1/audio/speech (OpenAI-compatible)
 * خروجی: بافر صوتی (mp3 پیش‌فرض) + ذخیره در مسیر داده‌شده
 */
class TtsAdapter
{
    protected AiHttp $http;
    protected string $model;

    public function __construct(?AiProvider $provider = null, ?string $model = null)
    {
        $this->http  = new AiHttp($provider);
        $this->model = $model
            ?? config('ai.tts_model')
            ?? ($provider?->settings['tts_model'] ?? null)
            ?? 'tts-1';
    }

    /**
     * تولید فایل صوتی از متن
     *
     * @param string $text        متن ورودی
     * @param string $targetPath  مسیر خروجی (با پسوند .mp3)
     * @param string $voice       صدا (پیش‌فرض: alvx برای tts-1)
     * @return array{success: bool, path: ?string, bytes: int, error_code: ?string}
     */
    public function synthesize(string $text, string $targetPath, string $voice = 'alvx', ?int $timeout = null): array
    {
        $timeout = $timeout ?: 120;

        if (trim($text) === '') {
            return $this->fail('empty_text', 0, null);
        }

        try {
            $response = $this->http->pending($timeout)
                ->acceptJson()
                ->post($this->http->baseUrl() . '/v1/audio/speech', [
                    'model'  => $this->model,
                    'input'  => $text,
                    'voice'  => $voice,
                    'format' => 'mp3',
                ]);

            if ($response->successful()) {
                $body = $response->body();

                if (strlen($body) < 100) {
                    // پاسخ خیلی کوچک = به‌احتمال‌زود audio واقعی نیست
                    Log::warning('[TtsAdapter] suspicious small response', [
                        'bytes' => strlen($body),
                        'model' => $this->model,
                    ]);
                    return $this->fail('empty_audio', 0, null);
                }

                $dir = dirname($targetPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($targetPath, $body);

                return [
                    'success'    => true,
                    'path'       => $targetPath,
                    'bytes'      => strlen($body),
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

            Log::warning('[TtsAdapter] synthesize failed', [
                'status'  => $response->status(),
                'model'   => $this->model,
                'message' => AiErrorRedactor::redact(mb_substr($response->body(), 0, 300)),
            ]);

            return $this->fail($errorCode, 0, null, AiHttp::errorMessage($response->status(), $response->body()));
        } catch (Throwable $e) {
            Log::error('[TtsAdapter] synthesize exception', [
                'error' => AiHttp::exceptionMessage($e->getMessage()),
                'model' => $this->model,
            ]);

            return $this->fail('exception', 0, null, AiHttp::exceptionMessage($e->getMessage()));
        }
    }

    protected function fail(string $errorCode, int $bytes, ?string $path, ?string $message = null): array
    {
        return [
            'success'    => false,
            'path'       => $path,
            'bytes'      => $bytes,
            'error_code' => $errorCode,
            'message'    => $message ?? $errorCode,
        ];
    }
}
