<?php

namespace App\Console\Commands;

use App\Services\AiProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiDailyHealthReport extends Command
{
    protected $signature = 'ai:daily-health-report
                            {--dry-run : فقط تست شود و پیامی ارسال نشود}';

    protected $description = 'گزارش ۲۴ ساعته سلامت سرویس AI — اگر سالم است «سالم است و من زنده هستم» و اگر سالم نیست هشدار، برای ربات مادر و ربات ادمین قرآن';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // تضمین وجود provider پیش‌فرض از config (اگر seeder اجرا نشده باشد)
        $provider = AiProviderService::ensureDefaultProvider();

        $this->info("🔄 بررسی سلامت {$provider->name} ({$provider->base_url}) ...");

        // ── Step 1: تست اتصال ──
        $service = new AiProviderService($provider);
        $result  = $service->testConnection();

        if (! $result['success']) {
            $provider->update([
                'last_tested_at'   => now(),
                'last_test_status' => 'failed',
                'last_test_error'  => $result['message'],
                'last_ping_ms'     => $result['ping_ms'],
            ]);

            $provider->logs()->create([
                'check_type'    => 'daily-report',
                'status'        => 'failed',
                'ping_ms'       => $result['ping_ms'],
                'error_message' => $result['message'],
            ]);

            $msg = "🔴 گزارش ۲۴ ساعته سلامت سرویس AI\n"
                 . "❌ سرویس سالم نیست!\n"
                 . "Server: {$provider->name} ({$provider->base_url})\n"
                 . "Error: {$result['message']}\n"
                 . "Time: " . now()->format('Y-m-d H:i:s');

            $this->error("❌ {$provider->name}: {$result['message']}");
            Log::error('[AiDailyHealthReport] FAILED', [
                'provider' => $provider->name,
                'error'    => $result['message'],
            ]);

            if (! $dryRun) {
                $sent = AiProviderService::notifyAllAdmins($msg, $provider);
                $this->info("📨 هشدار خرابی برای {$sent} مقصد ارسال شد.");
            } else {
                $this->warn('🧪 dry-run: هشدار ارسال نشد.');
            }

            return 1;
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
            'check_type'       => 'daily-report',
            'status'           => 'success',
            'ping_ms'          => $result['ping_ms'],
            'response_payload' => $chatResult['success'] ? $chatResult['response'] : null,
            'error_message'    => $chatResult['success'] ? null : $chatResult['response'],
        ]);

        $modelsList  = $result['models'] ? implode(', ', $result['models']) : '-';
        $chatLine    = $chatResult['success']
            ? "Chat test: ✅ (\"{$chatResult['response']}\")"
            : "Chat test: ⚠️ {$chatResult['response']}";

        $msg = "🟢 گزارش ۲۴ ساعته سلامت سرویس AI\n"
             . "سرویس سالم است و من زنده هستم ✅\n"
             . "Server: {$provider->name} ({$provider->base_url})\n"
             . "Ping: {$result['ping_ms']}ms\n"
             . "Models: {$modelsList}\n"
             . "{$chatLine}\n"
             . "Time: " . now()->format('Y-m-d H:i:s');

        $this->info("✅ {$provider->name}: OK — ping {$result['ping_ms']}ms — models: " . count($result['models']));

        Log::info('[AiDailyHealthReport] OK', ['provider' => $provider->name, 'ping' => $result['ping_ms']]);

        if (! $dryRun) {
            $sent = AiProviderService::notifyAllAdmins($msg, $provider);
            $this->info("📨 گزارش روزانه برای {$sent} مقصد ارسال شد.");
        } else {
            $this->warn('🧪 dry-run: پیام ارسال نشد.');
        }

        return 0;
    }
}
