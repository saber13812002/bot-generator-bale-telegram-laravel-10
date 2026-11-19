<?php

namespace App\Console\Commands;

use App\Models\AiProvider;
use App\Services\Ai\AiErrorRedactor;
use App\Services\AiProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * کشف و طبقه‌بندی مدل‌های موجود در LiteLLM
 * ------------------------------------------
 * - GET /v1/models
 * - طبقه‌بندی نام مدل‌ها: chat | vision | embedding | transcription | tts
 * - ذخیره در available_models (فهرست خام) و settings.model_capabilities (طبقه‌بندی)
 *
 * خروجی هیچ کلیدی را چاپ نمی‌کند.
 */
class AiDiscoverModels extends Command
{
    protected $signature = 'ai:discover-models {provider? : نام یا id provider (پیش‌فرض: provider پیش‌فرض/فعال)}';
    protected $description = 'کشف مدل‌های LiteLLM و طبقه‌بندی توانایی‌ها (chat/vision/embedding/transcription/tts)';

    public function handle(): int
    {
        $provider = $this->resolveProvider();
        if (! $provider) {
            $this->error('❌ هیچ provider فعالی یافت نشد.');
            return self::FAILURE;
        }

        $this->info("🔎 دریافت مدل‌ها از {$provider->name} ({$provider->base_url}) ...");

        try {
            $response = Http::withOptions(config('ai.http_options'))
                ->withToken($provider->api_key)
                ->timeout(30)
                ->get(rtrim($provider->base_url, '/') . '/v1/models');
        } catch (Throwable $e) {
            $this->error('❌ خطای شبکه: ' . AiErrorRedactor::redact($e->getMessage()));
            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('❌ HTTP ' . $response->status() . ' — ' . AiErrorRedactor::redact(mb_substr($response->body(), 0, 300)));
            return self::FAILURE;
        }

        $data = $response->json();
        $ids  = [];
        foreach ($data['data'] ?? [] as $m) {
            $ids[] = $m['id'] ?? ($m['name'] ?? 'unknown');
        }

        if ($ids === []) {
            $this->warn('⚠️ لیست مدل‌ها خالی بود.');
        }

        $capabilities = $this->classify($ids);

        $provider->update([
            'available_models' => $ids,
            'settings' => array_merge($provider->settings ?? [], [
                'model_capabilities' => $capabilities,
                'models_discovered_at' => now()->toIso8601String(),
            ]),
            'last_tested_at'   => now(),
            'last_test_status' => 'success',
        ]);

        $this->table('توانایی', 'مدل‌ها', [
            'chat'          => $capabilities['chat'],
            'vision'        => $capabilities['vision'],
            'embedding'     => $capabilities['embedding'],
            'transcription' => $capabilities['transcription'],
            'tts'           => $capabilities['tts'],
            'unknown'       => $capabilities['unknown'],
        ]);

        $missing = array_filter(['vision', 'embedding', 'transcription', 'tts'], fn ($k) => empty($capabilities[$k]));
        if ($missing) {
            $this->warn('⚠️ توانایی‌های بدون مدل شناسایی‌شده: ' . implode(', ', $missing));
        }

        Log::info('[AiDiscoverModels] models discovered', [
            'provider' => $provider->name,
            'models'   => $ids,
        ]);

        $this->info('✅ ذخیره شد در available_models / settings.model_capabilities');
        return self::SUCCESS;
    }

    protected function resolveProvider(): ?AiProvider
    {
        AiProviderService::ensureDefaultProvider();

        $arg = $this->argument('provider');
        if ($arg) {
            return AiProvider::query()
                ->where('id', $arg)
                ->orWhere('name', $arg)
                ->first();
        }

        return AiProvider::active()->orderBy('id')->first();
    }

    /**
     * طبقه‌بندی بر اساس نام مدل (هیوریستیک LiteLLM/OpenAI)
     * مدل‌های vision معمولاً chat هم هستند — هر دو لیست پُر می‌شوند.
     */
    protected function classify(array $ids): array
    {
        $result = [
            'chat'          => [],
            'vision'        => [],
            'embedding'     => [],
            'transcription' => [],
            'tts'           => [],
            'unknown'       => [],
        ];

        foreach ($ids as $id) {
            $lower = strtolower((string) $id);

            $isVision = (bool) preg_match(
                '/(vision|vlm|-vl\b|vl-|gpt-4o|gpt-4\.1|claude.*[-_]?vision|gemini|qwen.*vl|llava|cogvlm|kandinsky|pix2text)/',
                $lower
            );
            $isEmbedding = (bool) preg_match(
                '/(embed|bge-|nomic|e5-|gte-|m3e|paradigm|snowflake|voyage|ada-002)/',
                $lower
            );
            $isAsr = (bool) preg_match(
                '/(whisper|asr|transcrib|speech.?recog|paraformer|sensevoice)/',
                $lower
            );
            $isTts = (bool) preg_match(
                '/(tts|speech\.?synth|speak|gpt-?so-|chirp|eleven|kokoro)/',
                $lower
            );

            if ($isEmbedding) {
                $result['embedding'][] = $id;
            } elseif ($isAsr) {
                $result['transcription'][] = $id;
            } elseif ($isTts) {
                $result['tts'][] = $id;
            } elseif ($isVision) {
                $result['vision'][] = $id;
                $result['chat'][] = $id;
            } else {
                $result['chat'][] = $id;
            }
        }

        return $result;
    }
}
