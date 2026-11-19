<?php

namespace App\Console\Commands;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Console\Command;

class AiTestConnection extends Command
{
    protected $signature = 'ai:test {provider_id? : آیدی provider خاص} {--all : تست همه provider های فعال}';
    protected $description = 'تست اتصال به سرور هوش مصنوعی و دریافت لیست مدل‌ها + تست چت';

    public function handle(): int
    {
        // حالت --all: همه provider های فعال تست شوند
        if ($this->option('all')) {
            return $this->testAll();
        }

        $providerId = $this->argument('provider_id');
        $provider   = null;

        if ($providerId) {
            $provider = AiProvider::find($providerId);
            if (! $provider) {
                $this->error("❌ سرویس‌دهنده با ID {$providerId} یافت نشد.");
                return 1;
            }
        } else {
            // اول از دیتابیس، اگر نبود از .env
            $provider = AiProvider::active()->first();
            if (! $provider) {
                $this->warn("⚠️ هیچ سرویس‌دهنده فعالی در دیتابیس نیست. استفاده از تنظیمات .env");
            }
        }

        return $this->testProvider($provider);
    }

    private function testProvider(?AiProvider $provider): int
    {
        $baseUrl = $provider ? $provider->base_url : config('ai.default_base_url');
        $this->info("🔌 در حال تست اتصال به: {$baseUrl}");
        $this->newLine();

        $service = new AiProviderService($provider);

        // ── Step 1: تست اتصال (GET /v1/models) ──
        $this->info('─── قدم ۱: تست اتصال ───');
        $result = $service->testConnection();

        if ($result['success']) {
            $this->info("✅ اتصال برقرار — ping: {$result['ping_ms']}ms");

            if (! empty($result['models'])) {
                $this->info('📋 مدل‌های موجود:');
                foreach ($result['models'] as $i => $model) {
                    $this->line('   ' . ($i + 1) . '. ' . $model);
                }
            }

            // ذخیره در دیتابیس
            if ($provider) {
                $provider->update([
                    'last_tested_at'   => now(),
                    'last_test_status' => 'success',
                    'last_test_error'  => null,
                    'last_ping_ms'     => $result['ping_ms'],
                    'available_models' => $result['models'],
                ]);
            }
        } else {
            $this->error("❌ اتصال ناموفق — {$result['message']}");

            if ($provider) {
                $provider->update([
                    'last_tested_at'   => now(),
                    'last_test_status' => 'failed',
                    'last_test_error'  => $result['message'],
                    'last_ping_ms'     => $result['ping_ms'],
                ]);
            }

            // اطلاع‌رسانی به ادمین
            $notifyMsg = "🔴 AI Connection FAILED\nServer: {$baseUrl}\nError: {$result['message']}";
            AiProviderService::notifyAdmin($notifyMsg, $provider);
            $this->warn('📨 پیام خطا به ادمین ارسال شد.');

            return 1;
        }

        $this->newLine();

        // ── Step 2: تست چت (POST /v1/chat/completions) ──
        $this->info('─── قدم ۲: تست چت ───');
        $prompt = $provider?->default_prompt ?: config('ai.default_prompt');
        $this->line("📝 پرامپت: \"{$prompt}\"");

        $chatResult = $service->chat($prompt);

        if ($chatResult['success']) {
            $response = $chatResult['response'];
            $charCount = mb_strlen($response);
            $this->info("💬 جواب: \"{$response}\" ({$charCount} کاراکتر)");

            if (! empty($chatResult['usage'])) {
                $usage = $chatResult['usage'];
                $this->line("📊 Usage — prompt: " . ($usage['prompt_tokens'] ?? '?') .
                            ", completion: " . ($usage['completion_tokens'] ?? '?') .
                            ", total: " . ($usage['total_tokens'] ?? '?'));
            }

            // اطلاع‌رسانی موفقیت
            $modelsList = implode(', ', $result['models']);
            $notifyMsg  = "🟢 AI Health Check OK\n"
                        . "Server: {$baseUrl}\n"
                        . "Ping: {$result['ping_ms']}ms\n"
                        . "Models: {$modelsList}\n"
                        . "Chat: \"{$prompt}\" → \"{$response}\"";
            AiProviderService::notifyAdmin($notifyMsg, $provider);
            $this->info('📨 پیام موفقیت به ادمین ارسال شد.');
        } else {
            $this->error("❌ تست چت ناموفق — {$chatResult['response']}");

            if ($provider) {
                $provider->update([
                    'last_test_status' => 'failed',
                    'last_test_error'  => 'Chat failed: ' . $chatResult['response'],
                ]);
            }

            $notifyMsg = "🟡 AI Connection OK but Chat FAILED\n"
                       . "Server: {$baseUrl}\n"
                       . "Error: {$chatResult['response']}";
            AiProviderService::notifyAdmin($notifyMsg, $provider);
            $this->warn('📨 پیام خطای چت به ادمین ارسال شد.');

            return 1;
        }

        $this->newLine();
        $this->info('🎉 همه تست‌ها موفق بودند!');

        return 0;
    }

    private function testAll(): int
    {
        $providers = AiProvider::active()->get();

        if ($providers->isEmpty()) {
            $this->warn('⚠️ هیچ provider فعالی در دیتابیس یافت نشد.');
            return 1;
        }

        $this->info("🔄 تست {$providers->count()} provider فعال...");
        $this->newLine();

        $failed = 0;
        foreach ($providers as $provider) {
            $this->info("━━━ {$provider->name} ({$provider->base_url}) ━━━");
            $exitCode = $this->testProvider($provider);
            if ($exitCode !== 0) {
                $failed++;
            }
            $this->newLine();
        }

        $total = $providers->count();
        $ok    = $total - $failed;
        $this->info("📊 نتیجه: {$ok}/{$total} موفق — {$failed}/{$total} ناموفق");

        return $failed > 0 ? 1 : 0;
    }
}
