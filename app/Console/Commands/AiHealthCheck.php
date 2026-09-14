<?php

namespace App\Console\Commands;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AiHealthCheck extends Command
{
    protected $signature = 'ai:health-check';
    protected $description = 'بررسی سلامت سرورهای هوش مصنوعی فعال (هر ۱ ساعت)';

    public function handle(): int
    {
        $providers = AiProvider::active()->needsTest()->get();

        if ($providers->isEmpty()) {
            $this->info('✅ همه provider ها در ۱ ساعت اخیر تست شده‌اند.');
            return 0;
        }

        $this->info("🔄 بررسی {$providers->count()} provider ...");

        $failed    = 0;
        $recovered = 0;

        foreach ($providers as $provider) {
            $previousStatus = $provider->last_test_status;
            $service        = new AiProviderService($provider);

            // ── Step 1: تست اتصال ──
            $result = $service->testConnection();

            if (! $result['success']) {
                $failed++;
                $provider->update([
                    'last_tested_at'   => now(),
                    'last_test_status' => 'failed',
                    'last_test_error'  => $result['message'],
                    'last_ping_ms'     => $result['ping_ms'],
                ]);

                $provider->logs()->create([
                    'check_type'    => 'connection',
                    'status'        => 'failed',
                    'ping_ms'       => $result['ping_ms'],
                    'error_message' => $result['message'],
                ]);

                $this->error("❌ {$provider->name}: {$result['message']}");

                // نوتیفیکیشن fail
                $msg = "🔴 AI Health Check FAILED\n"
                     . "Server: {$provider->name} ({$provider->base_url})\n"
                     . "Error: {$result['message']}\n"
                     . "Time: " . now()->format('Y-m-d H:i:s');
                AiProviderService::notifyAdmin($msg, $provider);
                Log::warning('[AiHealthCheck] FAILED', ['provider' => $provider->name, 'error' => $result['message']]);

                continue;
            }

            // ── Step 2: تست چت ──
            $chatResult = $service->chat();

            $provider->update([
                'last_tested_at'   => now(),
                'last_test_status' => 'success',
                'last_test_error'  => null,
                'last_ping_ms'     => $result['ping_ms'],
                'available_models' => $result['models'],
            ]);

            $provider->logs()->create([
                'check_type'       => 'chat',
                'status'           => $chatResult['success'] ? 'success' : 'failed',
                'ping_ms'          => $result['ping_ms'],
                'response_payload' => $chatResult['success'] ? $chatResult['response'] : null,
                'error_message'    => $chatResult['success'] ? null : $chatResult['response'],
            ]);

            $this->info("✅ {$provider->name}: OK — ping {$result['ping_ms']}ms — models: " . count($result['models']));

            if ($chatResult['success']) {
                $this->line("   💬 Chat: \"{$chatResult['response']}\"");
            } else {
                $this->warn("   ⚠️ Chat failed: {$chatResult['response']}");
            }

            // نوتیفیکیشن recovery — فقط وقتی قبلاً fail بوده
            if ($previousStatus === 'failed') {
                $recovered++;
                $modelsList = implode(', ', $result['models']);
                $msg = "🟢 AI Server RECOVERED\n"
                     . "Server: {$provider->name} ({$provider->base_url})\n"
                     . "Ping: {$result['ping_ms']}ms\n"
                     . "Models: {$modelsList}\n"
                     . "Time: " . now()->format('Y-m-d H:i:s');
                AiProviderService::notifyAdmin($msg, $provider);
                $this->info("   📨 پیام recovery ارسال شد.");
            }

            Log::info('[AiHealthCheck] OK', ['provider' => $provider->name, 'ping' => $result['ping_ms']]);
        }

        $total = $providers->count();
        $ok    = $total - $failed;
        $this->newLine();
        $this->info("📊 نتیجه: {$ok}/{$total} OK — {$failed} failed — {$recovered} recovered");

        return $failed > 0 ? 1 : 0;
    }
}
