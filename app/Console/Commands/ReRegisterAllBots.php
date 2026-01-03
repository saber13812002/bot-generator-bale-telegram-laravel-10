<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Models\BotLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class ReRegisterAllBots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bots:re-register {--dry-run : نمایش لیست ربات‌ها بدون re-register کردن}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-register کردن همه ربات‌های موجود با webhook جدید که شامل bot_id است';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ webhookی re-register نخواهد شد.');
            $this->newLine();
        }

        $this->info('🔍 در حال دریافت لیست ربات‌های فعال...');
        $this->newLine();

        // دریافت همه ربات‌های فعال که token دارند
        $bots = Bot::where(function($query) {
            $query->where(function($q) {
                $q->whereNotNull('telegram_bot_token')
                    ->where('telegram_bot_token', '!=', '');
            })
            ->orWhere(function($q) {
                $q->whereNotNull('bale_bot_token')
                    ->where('bale_bot_token', '!=', '');
            });
        })
        ->whereNotNull('bot_mother_id')
        ->where('bot_mother_id', '>', 0)
        ->get();

        if ($bots->isEmpty()) {
            $this->info('✅ هیچ ربات فعالی یافت نشد.');
            return 0;
        }

        $this->info("📋 {$bots->count()} ربات فعال یافت شد.");
        $this->newLine();

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        $bar = $this->output->createProgressBar($bots->count());
        $bar->start();

        foreach ($bots as $bot) {
            try {
                // تشخیص type و token
                $type = null;
                $token = null;
                $botName = null;

                if ($bot->telegram_bot_token && $bot->telegram_bot_name) {
                    $type = 'telegram';
                    $token = $bot->telegram_bot_token;
                    $botName = $bot->telegram_bot_name;
                } elseif ($bot->bale_bot_token && $bot->bale_bot_name) {
                    $type = 'bale';
                    $token = $bot->bale_bot_token;
                    $botName = $bot->bale_bot_name;
                }

                if (!$type || !$token) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // استفاده از endpoint_id و language_code از جدول bots (اولویت اول)
                $endpointId = $bot->endpoint_id;
                $language = $bot->language_code ?? 'fa';

                // اگر endpoint_id در bots وجود نداشت، از BotLog استفاده می‌کنیم (fallback)
                if (!$endpointId) {
                    $lastLog = BotLog::where('bot_mother_id', $bot->bot_mother_id)
                        ->where('type', $type)
                        ->where('bot_id', $bot->id)
                        ->whereNotNull('webhook_endpoint_uri')
                        ->where('webhook_endpoint_uri', '!=', '')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastLog && $lastLog->webhook_endpoint_uri) {
                        $endpointId = $lastLog->webhook_endpoint_uri;
                        $language = $lastLog->language ?? $language;
                    }
                }

                // اگر هنوز endpoint_id نداریم، skip می‌کنیم
                if (!$endpointId) {
                    $skippedCount++;
                    $this->newLine();
                    $this->warn("  ⏭️  Bot #{$bot->id}: {$botName} ({$type}) - endpoint_id پیدا نشد");
                    $bar->advance();
                    continue;
                }

                $botMotherId = $bot->bot_mother_id;

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  ✅ Bot #{$bot->id}: {$botName} ({$type})");
                    $this->line("     Endpoint: {$endpointId}");
                    $this->line("     Language: {$language}");
                    $bar->advance();
                    continue;
                }

                // ساخت webhook URL جدید با bot_id
                $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $bot, $type, $language, $botMotherId);

                // ایجاد instance ربات
                $telegramBot = new Telegram($token, $type);

                // Set webhook
                $result = $telegramBot->setWebhook($webhookUrl);

                if ($result['ok']) {
                    // بررسی webhook
                    $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
                    
                    if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                        $successCount++;
                        
                        // به‌روزرسانی وضعیت webhook در دیتابیس
                        if ($type == 'bale') {
                            $bot->bale_webhook_is_set = 1;
                        } else {
                            $bot->telegram_webhook_is_set = 1;
                        }
                        $bot->save();
                        
                        $this->newLine();
                        $this->info("  ✅ Bot #{$bot->id}: {$botName} ({$type}) - Re-registered successfully");
                        
                        Log::info('Bot re-registered successfully', [
                            'bot_id' => $bot->id,
                            'type' => $type,
                            'endpoint' => $endpointId,
                            'language' => $language,
                            'webhook_url' => $webhookUrl,
                        ]);
                    } else {
                        $failedCount++;
                        $this->newLine();
                        $this->warn("  ⚠️  Bot #{$bot->id}: {$botName} ({$type}) - Webhook verification failed");
                        
                        Log::warning('Bot re-registered but webhook verification failed', [
                            'bot_id' => $bot->id,
                            'type' => $type,
                            'endpoint' => $endpointId,
                            'webhook_url' => $webhookUrl,
                        ]);
                    }
                } else {
                    $failedCount++;
                    $this->newLine();
                    $this->error("  ❌ Bot #{$bot->id}: {$botName} ({$type}) - Failed: " . ($result['description'] ?? 'Unknown error'));
                    
                    Log::error('Bot re-register failed', [
                        'bot_id' => $bot->id,
                        'type' => $type,
                        'endpoint' => $endpointId,
                        'error' => $result['description'] ?? 'Unknown error',
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Exception during bot re-register', [
                    'bot_id' => $bot->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // نمایش نتایج
        $this->info('📊 نتایج:');
        $this->line("  ✅ موفق: {$successCount}");
        $this->line("  ❌ ناموفق: {$failedCount}");
        $this->line("  ⏭️  رد شده: {$skippedCount}");

        if ($dryRun) {
            $this->newLine();
            $this->info('ℹ️  حالت dry-run فعال است - هیچ webhookی re-register نشد.');
            $this->info('برای re-register واقعی، دستور را بدون --dry-run اجرا کنید.');
        }

        return 0;
    }
}
