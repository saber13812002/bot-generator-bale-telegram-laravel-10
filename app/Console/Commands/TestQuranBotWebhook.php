<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class TestQuranBotWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-bot:test-webhook {bot_id : شناسه ربات}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تست webhook یک ربات قرآنی';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botId = $this->argument('bot_id');
        
        $this->info("🔍 در حال جستجوی ربات #{$botId}...");
        $this->newLine();

        $bot = Bot::find($botId);

        if (!$bot) {
            $this->error("❌ ربات با شناسه {$botId} یافت نشد.");
            return 1;
        }

        // بررسی اینکه ربات قرآنی است
        if ($bot->endpoint_id !== 'webhook-quran-word') {
            $this->warn("⚠️  این ربات یک ربات قرآنی نیست (endpoint_id: {$bot->endpoint_id})");
            if (!$this->confirm('آیا می‌خواهید ادامه دهید؟', false)) {
                return 0;
            }
        }

        // بررسی فیلدهای لازم
        if (!$bot->type) {
            $this->error("❌ فیلد type برای این ربات تنظیم نشده است.");
            return 1;
        }

        if (!$bot->language_code) {
            $this->error("❌ فیلد language_code برای این ربات تنظیم نشده است.");
            return 1;
        }

        if (!$bot->endpoint_id) {
            $this->error("❌ فیلد endpoint_id برای این ربات تنظیم نشده است.");
            return 1;
        }

        if (!$bot->bot_mother_id) {
            $this->error("❌ فیلد bot_mother_id برای این ربات تنظیم نشده است.");
            return 1;
        }

        // تشخیص token
        $type = $bot->type;
        $token = $type == 'telegram' ? $bot->telegram_bot_token : $bot->bale_bot_token;
        $botName = $type == 'telegram' ? $bot->telegram_bot_name : $bot->bale_bot_name;

        if (!$token) {
            $this->error("❌ توکن برای این ربات پیدا نشد.");
            return 1;
        }

        $this->info("📋 اطلاعات ربات:");
        $this->line("   ID: {$bot->id}");
        $this->line("   Name: {$botName}");
        $this->line("   Type: {$type}");
        $this->line("   Language: {$bot->language_code}");
        $this->line("   Endpoint: {$bot->endpoint_id}");
        $this->line("   Bot Mother ID: {$bot->bot_mother_id}");
        $this->newLine();

        // ساخت webhook URL
        try {
            $webhookUrl = WebhookEndpointHelper::createWebhookUrl(
                $bot->endpoint_id,
                $bot,
                $type,
                $bot->language_code,
                $bot->bot_mother_id
            );

            $this->info("🔗 Webhook URL:");
            $this->line("   {$webhookUrl}");
            $this->newLine();

            // Set webhook
            $this->info("🔄 در حال تنظیم webhook...");
            $telegramBot = new Telegram($token, $type);
            $result = $telegramBot->setWebhook($webhookUrl);

            if ($result['ok']) {
                $this->info("✅ Webhook با موفقیت تنظیم شد!");
                
                // بررسی webhook
                $this->info("🔍 در حال بررسی webhook...");
                $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
                
                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $actualUrl = $webhookInfo['result']['url'];
                    $this->info("✅ Webhook تایید شد!");
                    $this->line("   URL: {$actualUrl}");
                    
                    // به‌روزرسانی وضعیت webhook در دیتابیس
                    if ($type == 'bale') {
                        $bot->bale_webhook_is_set = 1;
                    } else {
                        $bot->telegram_webhook_is_set = 1;
                    }
                    $bot->save();
                    
                    Log::info('Quran bot webhook test successful', [
                        'bot_id' => $bot->id,
                        'type' => $type,
                        'language' => $bot->language_code,
                        'webhook_url' => $actualUrl,
                    ]);

                    $this->newLine();
                    $this->info("🎉 همه چیز درست است! ربات آماده استفاده است.");
                    
                } else {
                    $this->warn("⚠️  Webhook تنظیم شد اما تایید نشد.");
                    Log::warning('Quran bot webhook set but verification failed', [
                        'bot_id' => $bot->id,
                        'type' => $type,
                        'webhook_info' => $webhookInfo,
                    ]);
                }
            } else {
                $this->error("❌ خطا در تنظیم webhook:");
                $this->error("   " . ($result['description'] ?? 'Unknown error'));
                
                Log::error('Quran bot webhook test failed', [
                    'bot_id' => $bot->id,
                    'type' => $type,
                    'error' => $result['description'] ?? 'Unknown error',
                ]);
                
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('Exception during quran bot webhook test', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }

        return 0;
    }
}
