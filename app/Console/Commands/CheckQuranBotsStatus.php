<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotUsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Telegram;

class CheckQuranBotsStatus extends Command
{
    protected $signature = 'quran:check-bots-status
                            {--type=all : filter by type: telegram, bale, all}
                            {--detail : show detailed info for each bot}';

    protected $description = 'بررسی وضعیت ربات‌های قرآنی - Webhook, کاربران, آخرین فعالیت';

    public function handle()
    {
        $type = $this->option('type');
        $detail = $this->option('detail');

        $this->info('============================================');
        $this->info('  🔍 بررسی وضعیت ربات‌های قرآنی');
        $this->info('============================================');
        $this->newLine();

        // دریافت ربات‌های قرآنی
        $bots = Bot::where('bot_mother_id', 1)
            ->where(function ($q) {
                $q->whereNotNull('telegram_bot_name')
                  ->orWhereNotNull('bale_bot_name');
            })
            ->orderBy('language_code')
            ->get();

        if ($bots->isEmpty()) {
            $this->warn('هیچ ربات قرآنی در دیتابیس یافت نشد!');
            return 0;
        }

        $this->info("تعداد ربات‌های یافت شده: " . $bots->count());
        $this->newLine();

        $totalActiveUsers = 0;
        $totalTelegramUsers = 0;
        $totalBaleUsers = 0;

        foreach ($bots as $bot) {
            $botName = $bot->telegram_bot_name ?? $bot->bale_bot_name;
            $platform = $bot->telegram_bot_name ? '📱 تلگرام' : '💬 بله';
            $language = $this->getLanguageName($bot->language_code);

            // وضعیت webhook
            if ($bot->telegram_bot_name) {
                $webhookStatus = $bot->telegram_webhook_is_set ? '✅' : '❌';
                $botStatus = $bot->telegram_bot_status;
            } else {
                $webhookStatus = $bot->bale_webhook_is_set ? '✅' : '❌';
                $botStatus = $bot->bale_bot_status;
            }

            $statusIcon = $botStatus == 'Active' ? '🟢' : '🔴';

            // تعداد کاربران از bot_users
            $userCount = BotUsers::where('bot_id', $bot->id)->count();
            $activeUserCount = BotUsers::where('bot_id', $bot->id)
                ->where('status', 'active')
                ->count();

            $totalActiveUsers += $activeUserCount;
            if ($bot->telegram_bot_name) $totalTelegramUsers += $activeUserCount;
            if ($bot->bale_bot_name) $totalBaleUsers += $activeUserCount;

            // آخرین فعالیت از bot_logs
            $lastLog = BotLog::where('webhook_endpoint_uri', 'webhook-quran-word')
                ->where('language', $bot->language_code)
                ->latest('created_at')
                ->first();

            $lastActivity = $lastLog ? $lastLog->created_at->diffForHumans() : '❌ هیچ فعالیتی';

            // تعداد درخواست‌های 30 روز اخیر
            $recentRequests = BotLog::where('webhook_endpoint_uri', 'webhook-quran-word')
                ->where('language', $bot->language_code)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $recentUsers = BotLog::where('webhook_endpoint_uri', 'webhook-quran-word')
                ->where('language', $bot->language_code)
                ->where('created_at', '>=', now()->subDays(30))
                ->distinct('chat_id')
                ->count('chat_id');

            $this->line("  {$statusIcon} {$platform} @{$botName}");
            $this->line("     🌍 زبان: {$language} ({$bot->language_code})");
            $this->line("     🔗 وب‌هوک: {$webhookStatus} | وضعیت: {$botStatus}");
            $this->line("     👥 کاربران کل: {$userCount} | فعال: {$activeUserCount}");
            $this->line("     📊 درخواست 30 روز: {$recentRequests} | کاربران فعال: {$recentUsers}");
            $this->line("     🕐 آخرین فعالیت: {$lastActivity}");

            if ($detail && $bot->telegram_bot_token) {
                $this->checkTelegramWebhook($bot);
            }

            $this->newLine();
        }

        // خلاصه
        $this->info('============================================');
        $this->info('  📊 خلاصه وضعیت کلی');
        $this->info('============================================');
        $this->line("  کل ربات‌ها: {$bots->count()}");
        $this->line("  کاربران فعال تلگرام: {$totalTelegramUsers}");
        $this->line("  کاربران فعال بله: {$totalBaleUsers}");
        $this->line("  مجموع کاربران فعال: {$totalActiveUsers}");
        $this->newLine();

        return 0;
    }

    private function checkTelegramWebhook(Bot $bot): void
    {
        if (!$bot->telegram_bot_token) return;

        try {
            $response = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$bot->telegram_bot_token}/getWebhookInfo");

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok']) {
                    $info = $result['result'];
                    $this->line("     📡 Telegram API Webhook Info:");
                    $this->line("        URL: " . ($info['url'] ?: '❌ SET نشده'));
                    $this->line("        Pending: {$info['pending_update_count']}");
                    if ($info['last_error_message']) {
                        $this->error("        خطا: {$info['last_error_message']}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("     ⚠️ خطا در ارتباط با API: {$e->getMessage()}");
        }
    }

    private function getLanguageName(?string $code): string
    {
        $languages = [
            'ar-IQ' => '🇸🇦 عربی',
            'ur' => '🇵🇰 اردو',
            'zh-CN' => '🇨🇳 چینی',
            'es' => '🇪🇸 اسپانیایی',
            'de-DE' => '🇩🇪 آلمانی',
            'fr' => '🇫🇷 فرانسوی',
            'en' => '🇬🇧 انگلیسی',
            'fa' => '🇮🇷 فارسی',
            'ru' => '🇷🇺 روسی',
            'tr' => '🇹🇷 ترکی',
            'he' => '🇮🇱 عبری',
        ];

        return $languages[$code] ?? $code ?? 'نامشخص';
    }
}
