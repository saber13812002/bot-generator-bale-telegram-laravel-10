<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram;
use Throwable;

class AiProviderService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $model;
    protected string $prompt;

    public function __construct(?AiProvider $provider = null)
    {
        // اگر provider پاس داده نشود، از تنظیمات .env استفاده می‌شود
        $this->baseUrl = $provider ? $provider->base_url : config('ai.default_base_url');
        $this->apiKey  = $provider ? $provider->api_key  : config('ai.default_api_key');
        $this->model   = $provider ? ($provider->model_name ?: config('ai.default_model')) : config('ai.default_model');
        $this->prompt  = $provider ? ($provider->default_prompt ?: config('ai.default_prompt')) : config('ai.default_prompt');
    }

    /**
     * تست اتصال به سرور — GET /v1/models
     *
     * @return array{success: bool, message: string, models: array, ping_ms: int}
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $response = Http::withOptions(config('ai.http_options'))
                ->withToken($this->apiKey)
                ->get(rtrim($this->baseUrl, '/') . '/v1/models');

            $pingMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data   = $response->json();
                $models = [];

                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $m) {
                        $models[] = $m['id'] ?? ($m['name'] ?? 'unknown');
                    }
                }

                return [
                    'success' => true,
                    'message' => 'اتصال با موفقیت برقرار شد.',
                    'models'  => $models,
                    'ping_ms' => $pingMs,
                    'raw'     => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'خطا: HTTP ' . $response->status() . ' — ' . mb_substr($response->body(), 0, 300),
                'models'  => [],
                'ping_ms' => $pingMs,
            ];
        } catch (Throwable $e) {
            $pingMs = (int) round((microtime(true) - $start) * 1000);
            Log::error('[AiProvider] Connection test failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'خطای استثنا: ' . $e->getMessage(),
                'models'  => [],
                'ping_ms' => $pingMs,
            ];
        }
    }

    /**
     * دریافت لیست مدل‌ها
     *
     * @return array  لیست ID مدل‌ها
     */
    public function fetchModels(): array
    {
        $result = $this->testConnection();

        return $result['models'] ?? [];
    }

    /**
     * ارسال یک پیام chat/completions
     *
     * @return array{success: bool, response: string, usage: array}
     */
    public function chat(?string $prompt = null): array
    {
        $prompt = $prompt ?: $this->prompt;

        try {
            $response = Http::withOptions(config('ai.http_options'))
                ->withToken($this->apiKey)
                ->timeout(30)
                ->post(rtrim($this->baseUrl, '/') . '/v1/chat/completions', [
                    'model'    => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => 50,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data    = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage   = $data['usage'] ?? [];

                return [
                    'success'  => true,
                    'response' => trim($content),
                    'usage'    => $usage,
                ];
            }

            return [
                'success'  => false,
                'response' => 'HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300),
                'usage'    => [],
            ];
        } catch (Throwable $e) {
            Log::error('[AiProvider] Chat failed', ['error' => $e->getMessage()]);

            return [
                'success'  => false,
                'response' => $e->getMessage(),
                'usage'    => [],
            ];
        }
    }

    /**
     * فقط پینگ — زمان پاسخ‌دهی سرور (ms)
     */
    public function ping(): int
    {
        $start = microtime(true);

        try {
            Http::withOptions(config('ai.http_options'))
                ->withToken($this->apiKey)
                ->head(rtrim($this->baseUrl, '/') . '/v1/models');
        } catch (Throwable) {
            // حتی اگر fail شد، پینگ را برمی‌گردانیم
        }

        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * ارسال نوتیفیکیشن به ادمین
     * اگر provider توکن اختصاصی داشت، از آن استفاده می‌کند
     * وگرنه از BotHelper::sendMessageToSuperAdmin()
     */
    public static function notifyAdmin(string $message, ?AiProvider $provider = null): void
    {
        try {
            // اولویت 1: توکن اختصاصی provider
            if ($provider && $provider->notify_bot_token && $provider->notify_chat_id) {
                $platform = $provider->notify_platform ?: 'bale';
                $bot      = new Telegram($provider->notify_bot_token, $platform);
                BotHelper::sendMessageByChatId($bot, $provider->notify_chat_id, $message);
                return;
            }

            // اولویت 2: توکن اختصاصی از .env
            $customToken  = config('ai.custom_bot_token');
            $customChatId = config('ai.custom_chat_id');
            if ($customToken && $customChatId) {
                $platform = config('ai.notify_type', 'bale');
                $bot      = new Telegram($customToken, $platform);
                BotHelper::sendMessageByChatId($bot, $customChatId, $message);
                return;
            }

            // اولویت 3: ربات مادر → ادمین اصلی
            $type = config('ai.notify_type', 'bale');
            BotHelper::sendMessageToSuperAdmin($message, $type);
        } catch (Throwable $e) {
            Log::error('[AiProvider] Failed to notify admin', ['error' => $e->getMessage()]);
        }
    }
}
