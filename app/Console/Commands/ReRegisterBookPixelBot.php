<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class ReRegisterBookPixelBot extends Command
{
    protected $signature = 'book-pixel:reregister {bot_id}';
    protected $description = 'Re-register webhook for Book Pixel bot';

    public function handle()
    {
        $botId = $this->argument('bot_id');
        
        $bot = Bot::find($botId);
        
        if (!$bot) {
            $this->error("❌ ربات با ID {$botId} یافت نشد!");
            return 1;
        }
        
        if ($bot->endpoint_id !== 'book-pixel') {
            $this->error("❌ این ربات مربوط به Book Pixel نیست! endpoint_id: {$bot->endpoint_id}");
            return 1;
        }
        
        $this->info("🔄 در حال ثبت مجدد webhook برای ربات #{$botId}...");
        
        // Get bot type
        $type = $bot->bale_bot_token ? 'bale' : 'telegram';
        $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
        $botMotherId = $bot->bot_mother_id ?? 1;
        $language = $bot->language_code ?? 'fa';
        
        if (!$token) {
            $this->error("❌ توکن ربات یافت نشد!");
            return 1;
        }
        
        // Create bot instance
        $telegramBot = new Telegram($token, $type);
        
        // Create webhook URL
        $webhookUrl = WebhookEndpointHelper::createWebhookUrl('book-pixel', $bot, $type, $language, $botMotherId);
        
        $this->info("📝 Webhook URL: {$webhookUrl}");
        
        // Set webhook
        $setWebhookResult = $telegramBot->setWebhook($webhookUrl);
        
        if (!$setWebhookResult['ok']) {
            $this->error("❌ خطا در تنظیم webhook: " . ($setWebhookResult['description'] ?? 'Unknown error'));
            Log::error('Book Pixel bot webhook registration failed', [
                'bot_id' => $botId,
                'error' => $setWebhookResult['description'] ?? 'Unknown error',
            ]);
            return 1;
        }
        
        // Update webhook status in database
        if ($type === 'bale') {
            $bot->bale_webhook_is_set = 1;
        } else {
            $bot->telegram_webhook_is_set = 1;
        }
        $bot->save();
        
        // Verify webhook
        $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
        
        if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
            $this->info("✅ Webhook با موفقیت ثبت شد!");
            $this->info("🔗 Webhook URL: " . ($webhookInfo['result']['url'] ?? 'N/A'));
            $this->info("📊 Pending updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0));
            
            Log::info('Book Pixel bot webhook re-registered', [
                'bot_id' => $botId,
                'webhook_url' => $webhookUrl,
                'webhook_info' => $webhookInfo,
            ]);
            
            return 0;
        } else {
            $this->error("⚠️ Webhook ثبت شد اما تایید نشد!");
            $this->error("Webhook info: " . json_encode($webhookInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 1;
        }
    }
}
