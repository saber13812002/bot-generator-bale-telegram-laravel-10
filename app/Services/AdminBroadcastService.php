<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotLog;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram;

class AdminBroadcastService
{
    private const CACHE_PREFIX = 'broadcast_pending_';
    private const CACHE_TTL = 300; // 5 دقیقه

    /**
     * دریافت آمار کامل گروه‌بندی شده بر اساس زبان و پلتفرم
     * برای webhook-quran-word
     */
    public function getStats(int $botMotherId = 1, int $days = 30): array
    {
        $stats = BotLog::getQuranStatsGrouped($botMotherId, $days);
        
        // اضافه کردن نام ربات به هر ردیف
        $result = [];
        foreach ($stats as $stat) {
            $botName = null;
            // پیدا کردن نام ربات از bots table
            $botInfo = Bot::where('bot_mother_id', $botMotherId)
                ->where('language_code', $stat['language'])
                ->where('type', $stat['type'])
                ->first();
            
            if ($botInfo) {
                $botName = $stat['type'] == 'telegram' 
                    ? $botInfo->telegram_bot_name 
                    : $botInfo->bale_bot_name;
            }
            
            // fallback به config
            if (!$botName) {
                $botName = AdminHelper::getBotNameByLanguage($stat['language'], $stat['type']);
            }
            
            $result[] = [
                'language' => $stat['language'],
                'language_name' => AdminHelper::getLanguageName($stat['language']),
                'platform' => $stat['type'],
                'bot_name' => $botName ?? '—',
                'unique_users' => (int) $stat['unique_users'],
                'total_requests' => (int) $stat['total_requests'],
                'last_activity' => $stat['last_activity'],
            ];
        }
        
        return $result;
    }

    /**
     * دریافت آمار یک زبان خاص به تفکیک ربات‌ها
     */
    public function getStatsByLanguage(string $language, string $platform, int $botMotherId = 1, int $days = 30): array
    {
        $botsStats = BotLog::getStatsByLanguageWithBots($language, $platform, $botMotherId, $days);
        
        $totalUsers = 0;
        $totalRequests = 0;
        
        foreach ($botsStats as &$stat) {
            $totalUsers += $stat['unique_users'];
            $totalRequests += $stat['total_requests'];
        }
        
        return [
            'language' => $language,
            'language_name' => AdminHelper::getLanguageName($language),
            'platform' => $platform,
            'total_users' => $totalUsers,
            'total_requests' => $totalRequests,
            'bots' => $botsStats,
        ];
    }

    /**
     * آماده‌سازی broadcast و ذخیره در cache برای تأیید ادمین
     */
    public function prepareBroadcast(
        string $language,
        string $message,
        string $platform,
        int $botMotherId,
        string $adminChatId
    ): array {
        // دریافت آمار
        $stats = $this->getStatsByLanguage($language, $platform, $botMotherId);
        
        // ذخیره در cache
        $cacheKey = self::CACHE_PREFIX . $adminChatId;
        Cache::put($cacheKey, [
            'language' => $language,
            'message' => $message,
            'platform' => $platform,
            'bot_mother_id' => $botMotherId,
            'total_users' => $stats['total_users'],
            'created_at' => now(),
        ], self::CACHE_TTL);
        
        return $stats;
    }

    /**
     * آماده‌سازی broadcast به همه زبان‌ها
     */
    public function prepareBroadcastToAll(
        string $message,
        string $platform,
        int $botMotherId,
        string $adminChatId
    ): array {
        // دریافت آمار همه زبان‌ها
        $allStats = $this->getStats($botMotherId);
        $totalUsers = 0;
        foreach ($allStats as $stat) {
            $totalUsers += $stat['unique_users'];
        }
        
        // ذخیره در cache با language = '*'
        $cacheKey = self::CACHE_PREFIX . $adminChatId;
        Cache::put($cacheKey, [
            'language' => '*',
            'message' => $message,
            'platform' => $platform,
            'bot_mother_id' => $botMotherId,
            'total_users' => $totalUsers,
            'created_at' => now(),
        ], self::CACHE_TTL);
        
        return [
            'all_stats' => $allStats,
            'total_users' => $totalUsers,
        ];
    }

    /**
     * تأیید و ارسال broadcast ذخیره شده
     */
    public function confirmAndSend(string $adminChatId): array
    {
        $cacheKey = self::CACHE_PREFIX . $adminChatId;
        $pending = Cache::get($cacheKey);
        
        if (!$pending) {
            return [
                'success' => false,
                'message' => '❌ هیچ درخواست ارسال همگانی در انتظار تأیید نیست.',
                'sent_count' => 0,
                'bots_report' => [],
            ];
        }
        
        // حذف از cache
        Cache::forget($cacheKey);
        
        $language = $pending['language'];
        $message = $pending['message'];
        $platform = $pending['platform'];
        $botMotherId = $pending['bot_mother_id'];
        
        if ($language === '*') {
            return $this->sendBroadcastToAll($message, $platform, $botMotherId, $adminChatId);
        }
        
        return $this->sendBroadcast($language, $message, $platform, $botMotherId);
    }

    /**
     * ارسال مستقیم broadcast (بدون تأیید)
     */
    public function sendBroadcast(
        string $language,
        string $message,
        string $platform,
        int $botMotherId
    ): array {
        // دریافت chat_id ها به تفکیک bot_id
        $chatIdsByBot = BotLog::getChatIdsByLanguageAndBot($language, $platform, $botMotherId);
        
        // دریافت توکن ربات مادر
        $token = $platform == 'bale' 
            ? env('BOT_MOTHER_TOKEN_BALE') 
            : env('BOT_MOTHER_TOKEN_TELEGRAM');
        
        if (!$token) {
            throw new Exception("Token not found for platform: {$platform}");
        }
        
        $bot = new Telegram($token, $platform);
        
        $totalSent = 0;
        $totalErrors = 0;
        $botsReport = [];
        
        foreach ($chatIdsByBot as $botId => $chatIds) {
            $botName = 'ناشناخته';
            $botModel = Bot::find($botId);
            if ($botModel) {
                $botName = $platform == 'telegram' 
                    ? ($botModel->telegram_bot_name ?? $botModel->bale_bot_name ?? 'ناشناخته')
                    : ($botModel->bale_bot_name ?? $botModel->telegram_bot_name ?? 'ناشناخته');
            }
            
            $botSent = 0;
            $botErrors = 0;
            
            foreach ($chatIds as $chatId) {
                try {
                    BotHelper::sendMessageByChatId($bot, $chatId, $message);
                    $botSent++;
                    $totalSent++;
                    usleep(100000); // Rate limiting
                } catch (Exception $e) {
                    $botErrors++;
                    $totalErrors++;
                    Log::error('Broadcast error', [
                        'chat_id' => $chatId,
                        'bot_id' => $botId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $botsReport[] = [
                'bot_id' => $botId,
                'bot_name' => '@' . $botName,
                'sent' => $botSent,
                'errors' => $botErrors,
                'total' => count($chatIds),
            ];
        }
        
        // ارسال گزارش به ادمین
        $this->sendReportToAdmin($message, $language, $platform, $totalSent, $totalErrors, $botsReport);
        
        return [
            'success' => true,
            'language' => $language,
            'platform' => $platform,
            'sent_count' => $totalSent,
            'error_count' => $totalErrors,
            'bots_report' => $botsReport,
        ];
    }

    /**
     * ارسال به همه زبان‌ها
     */
    public function sendBroadcastToAll(
        string $message,
        string $platform,
        int $botMotherId,
        ?string $adminChatId = null
    ): array {
        $allStats = $this->getStats($botMotherId);
        $combinedResult = [
            'success' => true,
            'sent_count' => 0,
            'error_count' => 0,
            'languages_report' => [],
        ];
        
        foreach ($allStats as $stat) {
            if ($stat['platform'] !== $platform) continue;
            
            try {
                $result = $this->sendBroadcast(
                    $stat['language'],
                    $message,
                    $platform,
                    $botMotherId
                );
                
                $combinedResult['sent_count'] += $result['sent_count'];
                $combinedResult['error_count'] += $result['error_count'];
                $combinedResult['languages_report'][] = [
                    'language' => $stat['language'],
                    'language_name' => $stat['language_name'],
                    'sent' => $result['sent_count'],
                    'errors' => $result['error_count'],
                    'bots' => $result['bots_report'],
                ];
            } catch (Exception $e) {
                Log::error("Error broadcasting to language {$stat['language']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $combinedResult;
    }

    /**
     * ارسال گزارش به ادمین
     */
    private function sendReportToAdmin(
        string $message,
        string $language,
        string $platform,
        int $totalSent,
        int $totalErrors,
        array $botsReport
    ): void {
        $report = "📊 *گزارش ارسال پیام همگانی*\n";
        $report .= "─────────────────────\n";
        $report .= "🌍 زبان: " . AdminHelper::getLanguageName($language) . "\n";
        $report .= "📱 پلتفرم: {$platform}\n";
        $report .= "✅ ارسال موفق: {$totalSent}\n";
        $report .= "❌ خطا: {$totalErrors}\n\n";
        
        if (!empty($botsReport)) {
            $report .= "📋 *تفکیک ربات‌ها:*\n";
            foreach ($botsReport as $botReport) {
                $report .= "└ {$botReport['bot_name']}: ";
                $report .= "✅{$botReport['sent']} ";
                if ($botReport['errors'] > 0) {
                    $report .= "❌{$botReport['errors']} ";
                }
                $report .= "(کل: {$botReport['total']})\n";
            }
        }
        
        BotHelper::sendMessageToSuperAdmin($report, $platform);
    }

    /**
     * ساخت متن پیام آمار برای ارسال به ادمین
     */
    public function formatStatsMessage(array $stats): string
    {
        $message = "📊 *آمار کاربران ربات‌های قرآنی (۳۰ روز اخیر)*\n";
        $message .= "─────────────────────────────\n";
        
        $totalUsers = 0;
        $totalRequests = 0;
        
        foreach ($stats as $stat) {
            $botInfo = $stat['bot_name'] ? " @{$stat['bot_name']}" : '';
            $platformIcon = $stat['platform'] == 'bale' ? '💬' : '📱';
            
            $users = number_format($stat['unique_users']);
            $requests = number_format($stat['total_requests']);
            
            $message .= "{$stat['language_name']} {$platformIcon}{$botInfo}:\n";
            $message .= "  └ 👥 {$users} کاربر | 📨 {$requests} درخواست\n";
            
            $totalUsers += $stat['unique_users'];
            $totalRequests += $stat['total_requests'];
        }
        
        $message .= "─────────────────────────────\n";
        $message .= "📌 مجموع: " . number_format($totalUsers) . " کاربر | " . number_format($totalRequests) . " درخواست\n";
        $message .= "🕐 بروزرسانی: " . now()->format('Y-m-d H:i') . "\n\n";
        $message .= "💡 *راهنما:*\n";
        $message .= "└ `///stats` ← آمار کامل\n";
        $message .= "└ `///stats ru` ← آمار روسی\n";
        $message .= "└ `////ru متن` ← ارسال به روسی\n";
        $message .= "└ `/////all متن` ← ارسال به همه\n";
        $message .= "└ `/confirm` ← تأیید ارسال\n";
        
        return $message;
    }

    /**
     * ساخت متن تأیید ارسال
     */
    public function formatConfirmationMessage(array $stats, string $message, string $platform, int $totalUsers): string
    {
        $text = "📋 *تأیید ارسال پیام همگانی*\n";
        $text .= "────────────────────────\n";
        $text .= "🌍 زبان: {$stats['language_name']}\n";
        $text .= "👥 تعداد: {$totalUsers} کاربر\n";
        $text .= "📱 پلتفرم: {$platform}\n\n";
        
        if (!empty($stats['bots'])) {
            $text .= "📋 *تفکیک ربات‌ها:*\n";
            foreach ($stats['bots'] as $bot) {
                $text .= "└ {$bot['bot_name']}: {$bot['unique_users']} کاربر\n";
            }
            $text .= "\n";
        }
        
        $text .= "📝 *متن پیام:*\n";
        $text .= "```\n{$message}\n```\n\n";
        $text .= "✅ برای تأیید: `/confirm`\n";
        $text .= "❌ برای لغو: هر دستور دیگری\n";
        $text .= "⏰ این درخواست ۵ دقیقه اعتبار دارد.\n";
        
        return $text;
    }
}
