<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\EmailAdminHelper;
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

    /**
     * ایجاد خودکار provider پیش‌فرض از تنظیمات .env (config/ai.php)
     *
     * اگر رکورد provider با همان base_url موجود نباشد، ساخته می‌شود —
     * دقیقاً مشابه AiProviderDefaultSeeder اما بدون وابستگی به اجرای seeder.
     */
    public static function ensureDefaultProvider(): AiProvider
    {
        $provider = AiProvider::firstOrCreate(
            ['base_url' => config('ai.default_base_url')],
            [
                'name'            => 'ISMC AI Server',
                'base_url'        => config('ai.default_base_url', 'https://ai.ismc.ir/api'),
                'api_key'         => config('ai.default_api_key', ''),
                'model_name'      => config('ai.default_model', 'qwen38'),
                'default_prompt'  => config('ai.default_prompt', 'سلام! جواب سلام بده و در ۴ کاراکتر'),
                'provider_type'   => 'openai_compatible',
                'is_active'       => true,
                'notify_platform' => config('ai.notify_type', 'bale'),
            ]
        );

        if ($provider->wasRecentlyCreated) {
            Log::info('[AiProvider] Default provider created from config', ['base_url' => $provider->base_url]);
        }

        return $provider;
    }

    /**
     * ارسال پیام به همه ادمین‌ها از دو مسیر:
     * 1) ربات مادر (bale + telegram) — الگوی درخواست تایید پلن کتابخانه
     * 2) ربات ادمین قرآن QURAN_HEFZ (bale + telegram) — الگوی گزارش روزانه قرآن
     *
     * @return int تعداد ارسال‌های موفق
     */
    public static function notifyAllAdmins(string $message, ?AiProvider $provider = null): int
    {
        $sent = 0;

        // اولویت ۱: توکن اختصاصی provider (همان رفتار قبلی)
        if ($provider && $provider->notify_bot_token && $provider->notify_chat_id) {
            try {
                $platform = $provider->notify_platform ?: 'bale';
                $bot      = new Telegram($provider->notify_bot_token, $platform);
                BotHelper::sendMessageByChatId($bot, $provider->notify_chat_id, $message);
                $sent++;
            } catch (Throwable $e) {
                Log::error('[AiProvider] Failed to notify provider-specific bot', ['error' => $e->getMessage()]);
            }
        }

        // مسیر ۱: ربات مادر (bale + telegram)
        try {
            EmailAdminHelper::sendToAllAdmins($message, 'bale');
            EmailAdminHelper::sendToAllAdmins($message, 'telegram');
        } catch (Throwable $e) {
            Log::error('[AiProvider] Failed to notify via mother bot', ['error' => $e->getMessage()]);
        }

        // مسیر ۲: ربات ادمین قرآن (QURAN_HEFZ)
        try {
            $admins = array_filter(AdminHelper::getAdmins());
            $baleToken     = env('QURAN_HEFZ_BOT_TOKEN_BALE');
            $telegramToken = env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM');

            foreach ($admins as $chatId) {
                if (!$chatId) {
                    continue;
                }
                try {
                    if ($baleToken) {
                        $botBale = new Telegram($baleToken, 'bale');
                        BotHelper::sendMessageByChatId($botBale, (string) $chatId, $message);
                        $sent++;
                    }
                    if ($telegramToken) {
                        $botTelegram = new Telegram($telegramToken);
                        BotHelper::sendMessageByChatId($botTelegram, (string) $chatId, $message);
                        $sent++;
                    }
                } catch (Throwable $e) {
                    Log::warning('[AiProvider] Failed to send via Quran bot', [
                        'chat_id' => $chatId,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error('[AiProvider] Failed to notify via Quran bot', ['error' => $e->getMessage()]);
        }

        return $sent;
    }
}
