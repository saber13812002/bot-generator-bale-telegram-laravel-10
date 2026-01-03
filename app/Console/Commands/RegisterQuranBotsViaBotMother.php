<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Models\BotLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class RegisterQuranBotsViaBotMother extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-bots:register-via-bot-mother 
                            {--bot-mother-id=1 : شناسه ربات مادر}
                            {--dry-run : نمایش لیست ربات‌ها بدون ثبت کردن}
                            {--endpoint=webhook-quran-word : endpoint_id ربات‌های قرآنی}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ثبت ربات‌های قرآنی که در ربات مادر ثبت نشده‌اند';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $botMotherId = (int) $this->option('bot-mother-id');
        $endpointId = $this->option('endpoint');

        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ رباتی ثبت نخواهد شد.');
            $this->newLine();
        }

        $this->info("🔍 در حال جستجوی ربات‌های قرآنی بدون bot_mother_id...");
        $this->info("   Endpoint: {$endpointId}");
        $this->info("   Bot Mother ID: {$botMotherId}");
        $this->newLine();

        // پیدا کردن ربات‌های قرآنی که bot_mother_id ندارند
        // اول از جدول bots
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
        ->where(function($query) use ($endpointId) {
            $query->where('endpoint_id', $endpointId)
                ->orWhere('endpoint_id', 'webhook-quran-ayat'); // شامل هر دو نوع ربات قرآنی
        })
        ->where(function($query) {
            $query->whereNull('bot_mother_id')
                ->orWhere('bot_mother_id', 0);
        })
        ->get();

        // همچنین از لاگ‌ها، ربات‌های قرآنی که bot_id دارند اما bot_mother_id ندارند را پیدا کنیم
        $quranEndpoints = ['webhook-quran-word', 'webhook-quran-ayat', 'quran-word', 'quran-ayat'];
        
        $logsWithBots = BotLog::whereIn('webhook_endpoint_uri', $quranEndpoints)
            ->whereNotNull('bot_id')
            ->where('bot_id', '!=', 1) // bot_id = 1 مربوط به Bot Mother است
            ->whereNotNull('type')
            ->whereNotNull('language')
            ->select('bot_id', 'type', 'language', 'webhook_endpoint_uri')
            ->distinct()
            ->get();

        // برای هر لاگ، بررسی کنیم که آیا ربات در bots وجود دارد و bot_mother_id دارد یا نه
        $botIdsFromLogs = $logsWithBots->pluck('bot_id')->unique();
        
        foreach ($botIdsFromLogs as $botId) {
            $bot = Bot::find($botId);
            if ($bot && (!$bot->bot_mother_id || $bot->bot_mother_id == 0)) {
                // اگر ربات در لیست نیست، اضافه کن
                if (!$bots->contains('id', $botId)) {
                    $bots->push($bot);
                }
            }
        }

        // بررسی ربات‌هایی که در لاگ‌ها هستند اما در bots نیستند
        $this->info("🔍 بررسی ربات‌هایی که در لاگ‌ها هستند اما در bots نیستند...");
        $missingBots = [];
        foreach ($logsWithBots as $log) {
            $botId = $log->bot_id;
            $bot = Bot::find($botId);
            if (!$bot) {
                // این ربات در bots نیست
                $missingBots[] = [
                    'bot_id_from_log' => $botId,
                    'type' => $log->type,
                    'language' => $log->language,
                    'endpoint' => $log->webhook_endpoint_uri,
                ];
            }
        }

        if ($bots->isEmpty() && empty($missingBots)) {
            $this->info('✅ هیچ ربات قرآنی بدون bot_mother_id یافت نشد.');
            return 0;
        }

        if (!empty($missingBots)) {
            $this->warn("⚠️  " . count($missingBots) . " ربات در لاگ‌ها یافت شد که در جدول bots نیستند:");
            $this->newLine();
            $this->table(
                ['Bot ID (از لاگ)', 'Type', 'Language', 'Endpoint'],
                collect($missingBots)->map(function($item) {
                    return [
                        $item['bot_id_from_log'],
                        $item['type'],
                        $item['language'],
                        $item['endpoint'],
                    ];
                })
            );
            $this->newLine();
            $this->warn("⚠️  برای این ربات‌ها باید توکن پیدا کنید و از طریق ربات مادر ثبت کنید.");
            $this->newLine();
        }

        if ($bots->isEmpty()) {
            $this->info('✅ هیچ ربات قرآنی بدون bot_mother_id در جدول bots یافت نشد.');
            $this->info('💡 اما ' . count($missingBots) . ' ربات در لاگ‌ها یافت شد که باید توکن آن‌ها را پیدا کنید.');
            return 0;
        }

        $this->info("📋 {$bots->count()} ربات قرآنی بدون bot_mother_id در جدول bots یافت شد.");
        $this->newLine();

        // نمایش لیست ربات‌ها
        $this->table(
            ['ID', 'Name', 'Type', 'Token Preview', 'Language', 'Endpoint'],
            $bots->map(function($bot) {
                $type = null;
                $token = null;
                $name = null;

                if ($bot->telegram_bot_token && $bot->telegram_bot_name) {
                    $type = 'telegram';
                    $token = $bot->telegram_bot_token;
                    $name = $bot->telegram_bot_name;
                } elseif ($bot->bale_bot_token && $bot->bale_bot_name) {
                    $type = 'bale';
                    $token = $bot->bale_bot_token;
                    $name = $bot->bale_bot_name;
                }

                return [
                    $bot->id,
                    $name ?? 'N/A',
                    $type ?? 'N/A',
                    $token ? substr($token, 0, 20) . '...' : 'N/A',
                    $bot->language_code ?? 'fa',
                    $bot->endpoint_id ?? 'N/A',
                ];
            })
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('ℹ️  حالت dry-run فعال است - هیچ رباتی ثبت نشد.');
            $this->info('برای ثبت واقعی، دستور را بدون --dry-run اجرا کنید.');
            return 0;
        }

        if (!$this->confirm('آیا می‌خواهید این ربات‌ها را ثبت کنید؟', true)) {
            $this->info('❌ عملیات لغو شد.');
            return 0;
        }

        $this->newLine();
        $this->info('🚀 شروع ثبت ربات‌ها...');
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

                // استفاده از endpoint_id و language_code از جدول bots
                $endpointId = $bot->endpoint_id ?? $endpointId;
                $language = $bot->language_code ?? 'fa';

                // اگر endpoint_id نداریم، skip می‌کنیم
                if (!$endpointId) {
                    $skippedCount++;
                    $this->newLine();
                    $this->warn("  ⏭️  Bot #{$bot->id}: {$botName} ({$type}) - endpoint_id پیدا نشد");
                    $bar->advance();
                    continue;
                }

                // ایجاد instance ربات برای بررسی
                $telegramBot = new Telegram($token, $type);
                $getMe = $telegramBot->getMe();

                if (!$getMe['ok']) {
                    $failedCount++;
                    $this->newLine();
                    $this->error("  ❌ Bot #{$bot->id}: {$botName} ({$type}) - خطا در دریافت اطلاعات: " . ($getMe['description'] ?? 'Unknown error'));
                    $bar->advance();
                    continue;
                }

                // به‌روزرسانی ربات با اطلاعات جدید
                $bot->bot_mother_id = $botMotherId;
                $bot->endpoint_id = $endpointId;
                $bot->language_code = $language;
                $bot->type = $type;

                // به‌روزرسانی اطلاعات ربات از getMe
                if ($type == 'bale') {
                    $bot->bale_bot_name = $getMe['result']['username'] ?? $bot->bale_bot_name;
                    $bot->bale_get_me_api_response = json_encode($getMe['result']);
                    $bot->bale_bot_status = 'Active';
                } else {
                    $bot->telegram_bot_name = $getMe['result']['username'] ?? $bot->telegram_bot_name;
                    $bot->telegram_get_me_api_response = json_encode($getMe['result']);
                    $bot->telegram_bot_status = 'Active';
                }

                $bot->save();

                // ساخت webhook URL جدید
                $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $bot, $type, $language, $botMotherId);

                // Set webhook
                $setWebhookResult = $telegramBot->setWebhook($webhookUrl);

                if (!$setWebhookResult['ok']) {
                    $failedCount++;
                    $this->newLine();
                    $this->error("  ❌ Bot #{$bot->id}: {$botName} ({$type}) - خطا در تنظیم webhook: " . ($setWebhookResult['description'] ?? 'Unknown error'));
                    
                    Log::error('Quran bot registration failed - webhook error', [
                        'bot_id' => $bot->id,
                        'bot_mother_id' => $botMotherId,
                        'type' => $type,
                        'endpoint_id' => $endpointId,
                        'language' => $language,
                        'error' => $setWebhookResult['description'] ?? 'Unknown error',
                    ]);
                    $bar->advance();
                    continue;
                }

                // به‌روزرسانی وضعیت webhook در دیتابیس
                if ($type == 'bale') {
                    $bot->bale_webhook_is_set = 1;
                } else {
                    $bot->telegram_webhook_is_set = 1;
                }
                $bot->save();

                // بررسی webhook
                $webhookInfo = BotHelper::checkWebhookInfo($token, $type);

                if ($webhookInfo['ok'] && !empty($webhookInfo['result']['url'] ?? null)) {
                    $successCount++;
                    
                    $this->newLine();
                    $this->info("  ✅ Bot #{$bot->id}: {$botName} ({$type}) - با موفقیت ثبت شد");
                    
                    Log::info('Quran bot registered via Bot Mother command', [
                        'bot_id' => $bot->id,
                        'bot_mother_id' => $botMotherId,
                        'type' => $type,
                        'language' => $language,
                        'endpoint_id' => $endpointId,
                        'webhook_url' => $webhookUrl,
                    ]);
                } else {
                    $failedCount++;
                    $this->newLine();
                    $this->warn("  ⚠️  Bot #{$bot->id}: {$botName} ({$type}) - Webhook verification failed");
                    
                    Log::warning('Quran bot registered but webhook verification failed', [
                        'bot_id' => $bot->id,
                        'type' => $type,
                        'endpoint_id' => $endpointId,
                        'webhook_url' => $webhookUrl,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->newLine();
                $botId = $bot->id ?? 'N/A';
                $this->error("  ❌ Bot #{$botId}: خطا - " . $e->getMessage());
                
                Log::error('Exception during Quran bot registration', [
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

        return 0;
    }
}
