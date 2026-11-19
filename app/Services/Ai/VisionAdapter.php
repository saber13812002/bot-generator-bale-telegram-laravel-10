<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * آداپتور بینایی (Vision) — تبدیل تصویر به متن (OCR)
 * ----------------------------------------------------
 * از /v1/chat/completions با content-part تصویری (data-URI) استفاده می‌کند.
 * مدل‌های قابل‌استفاده: qwen-vl / gpt-4o / claude / gemini-vision (هر مدلی که LiteLLM به‌صورت vision ثبت کرده).
 *
 * خروجی: متن استخراج‌شده + usage. اگر توانایی vision وجود نداشته باشد
 * (HTTP 400/404 model_not_found یا خطای explicit)، success=false با error_code
 * مناسب برمی‌گردد تا فراخواننده «خروجی جعلی» تولید نکند.
 */
class VisionAdapter
{
    protected AiHttp $http;
    protected string $model;

    public function __construct(?AiProvider $provider = null, ?string $model = null)
    {
        $this->http  = new AiHttp($provider);
        $this->model = $model
            ?? config('ai.vision_model')
            ?? ($provider?->settings['vision_model'] ?? null)
            ?? config('ai.default_model');
    }

    /**
     * خواندن متن از تصویر
     *
     * @param string $imageUrl   URL عمومی یا مسیر محلی فایل
     * @param string $prompt     دستور OCR (پیش‌فرض: استخراج متن فارسی/عربی)
     * @return array{success: bool, text: string, usage: array, error_code: ?string}
     */
    public function extractText(string $imageUrl, ?string $prompt = null, ?int $timeout = null): array
    {
        $timeout = $timeout ?: 60;

        $dataUrl = $this->toDataUri($imageUrl);
        if (! $dataUrl) {
            return $this->fail('file_not_found', 'فایل تصویر یافت نشد یا خالی است.');
        }

        $prompt = $prompt ?: (config('ai.vision_ocr_prompt') ?? 'متن این تصویر را دقیق و کامل استخراج کن. ترتیب سطرها و پاراگراف‌ها را حفظ کن. فقط متن خروجی بده.');

        try {
            $response = $this->http->pending($timeout)
                ->post($this->http->baseUrl() . '/v1/chat/completions', [
                    'model'    => $this->model,
                    'messages' => [
                        [
                            'role'    => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                            ],
                        ],
                    ],
                    'max_tokens'  => 2000,
                    'temperature' => 0,
                ]);

            if ($response->successful()) {
                $data    = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                return [
                    'success'    => true,
                    'text'       => trim($content),
                    'usage'      => $data['usage'] ?? [],
                    'error_code' => null,
                ];
            }

            $errorCode = $this->classifyError($response->status());
            Log::warning('[VisionAdapter] extractText failed', [
                'status'  => $response->status(),
                'model'   => $this->model,
                'message' => AiErrorRedactor::redact(mb_substr($response->body(), 0, 300)),
            ]);

            return $this->fail($errorCode, AiHttp::errorMessage($response->status(), $response->body()));
        } catch (Throwable $e) {
            Log::error('[VisionAdapter] extractText exception', [
                'error' => AiHttp::exceptionMessage($e->getMessage()),
                'model' => $this->model,
            ]);

            return $this->fail('exception', AiHttp::exceptionMessage($e->getMessage()));
        }
    }

    /**
     * تبدیل URL یا مسیر محلی به data-URI (base64)
     * LiteLLM/OpenAI هر دو data-URI را می‌پذیرند و به ماسک کلید نیاز ندارند.
     */
    protected function toDataUri(string $imageUrl): ?string
    {
        if (str_starts_with($imageUrl, 'data:')) {
            return $imageUrl;
        }

        $context = stream_context_create([
            'http' => ['timeout' => 30, 'header' => "User-Agent: bot-ai-platform\r\n"],
        ]);
        $contents = @file_get_contents($imageUrl, false, $context);

        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = (preg_match('#^https?://#', $imageUrl))
            ? $this->guessMime($imageUrl)
            : (mime_content_type($imageUrl) ?: 'image/jpeg');

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    protected function guessMime(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($ext) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            default => 'image/jpeg',
        };
    }

    protected function classifyError(int $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'auth_failed',
            $status === 404                   => 'model_not_found',
            $status >= 500                    => 'provider_error',
            $status === 429                   => 'rate_limited',
            default                           => 'http_error',
        };
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
