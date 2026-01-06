<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use Illuminate\Console\Command;
use Longman\TelegramBot\Telegram;

class ResetAllWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:reset-webhooks {--bot-id= : Reset webhook for specific bot ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset webhooks for all active bots (fix missing slash issue)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 شروع ریست webhook ها...');
        $this->newLine();

        $query = Bot::query();
        
        // اگر bot-id داده شده، فقط آن ربات را پردازش کن
        if ($botId = $this->option('bot-id')) {
            $query->where('id', $botId);
        }

        $bots = $query->get();
        
        if ($bots->isEmpty()) {
            $this->error('❌ هیچ رباتی یافت نشد!');
            return 1;
        }

        $this->info("📊 تعداد ربات‌ها: {$bots->count()}");
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($bots as $bot) {
            $this->line("🤖 در حال پردازش ربات ID: {$bot->id}");
            
            // Bale Bot
            if ($bot->bale_bot_token && $bot->endpoint_id) {
                $this->line("  📱 Bale Bot: {$bot->bale_bot_name}");
                
                try {
                    $webhookUrl = WebhookEndpointHelper::createWebhookUrl(
                        $bot->endpoint_id,
                        $bot,
                        'bale',
                        $bot->language_code ?? 'fa',
                        $bot->bot_mother_id ?? 1
                    );
                    
                    $this->line("  🔗 Webhook URL: {$webhookUrl}");
                    
                    $telegramBot = new Telegram($bot->bale_bot_token, 'bale');
                    $result = $telegramBot->setWebhook($webhookUrl);
                    
                    if ($result['ok']) {
                        $bot->bale_webhook_is_set = 1;
                        $bot->save();
                        
                        // بررسی webhook
                        $webhookInfo = BotHelper::checkWebhookInfo($bot->bale_bot_token, 'bale');
                        
                        if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'])) {
                            $this->info("  ✅ Bale webhook ست شد!");
                            $this->line("     URL: {$webhookInfo['result']['url']}");
                            $successCount++;
                        } else {
                            $this->warn("  ⚠️  Bale webhook ست شد ولی بررسی موفق نبود");
                            $this->line("     " . json_encode($webhookInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        }
                    } else {
                        $this->error("  ❌ خطا در ست webhook Bale: " . ($result['description'] ?? 'Unknown error'));
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Exception در Bale: " . $e->getMessage());
                    $failCount++;
                }
                
                $this->newLine();
            }
            
            // Telegram Bot
            if ($bot->telegram_bot_token && $bot->endpoint_id) {
                $this->line("  ✈️ Telegram Bot: {$bot->telegram_bot_name}");
                
                try {
                    $webhookUrl = WebhookEndpointHelper::createWebhookUrl(
                        $bot->endpoint_id,
                        $bot,
                        'telegram',
                        $bot->language_code ?? 'fa',
                        $bot->bot_mother_id ?? 1
                    );
                    
                    $this->line("  🔗 Webhook URL: {$webhookUrl}");
                    
                    $telegramBot = new Telegram($bot->telegram_bot_token, 'telegram');
                    $result = $telegramBot->setWebhook($webhookUrl);
                    
                    if ($result['ok']) {
                        $bot->telegram_webhook_is_set = 1;
                        $bot->save();
                        
                        // بررسی webhook
                        $webhookInfo = BotHelper::checkWebhookInfo($bot->telegram_bot_token, 'telegram');
                        
                        if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'])) {
                            $this->info("  ✅ Telegram webhook ست شد!");
                            $this->line("     URL: {$webhookInfo['result']['url']}");
                            $successCount++;
                        } else {
                            $this->warn("  ⚠️  Telegram webhook ست شد ولی بررسی موفق نبود");
                            $this->line("     " . json_encode($webhookInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        }
                    } else {
                        $this->error("  ❌ خطا در ست webhook Telegram: " . ($result['description'] ?? 'Unknown error'));
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Exception در Telegram: " . $e->getMessage());
                    $failCount++;
                }
                
                $this->newLine();
            }
            
            $this->line(str_repeat('─', 60));
            $this->newLine();
        }

        // خلاصه نتایج
        $this->newLine();
        $this->info('📊 خلاصه نتایج:');
        $this->line("  ✅ موفق: {$successCount}");
        $this->line("  ❌ ناموفق: {$failCount}");
        $this->newLine();

        if ($failCount == 0) {
            $this->info('🎉 همه webhook ها با موفقیت ریست شدند!');
            return 0;
        } else {
            $this->warn('⚠️  برخی webhook ها با خطا مواجه شدند. لاگ‌ها را بررسی کنید.');
            return 1;
        }
    }
}
