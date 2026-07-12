<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BroadcastLog;
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
     */
    public function getStats(int $botMotherId = 1, int $days = 30): array
    {
        $stats = BotLog::getQuranStatsGrouped($botMotherId, $days);
        
        $result = [];
        foreach ($stats as $stat) {
            $botName = null;
            $botInfo = Bot::where('bot_mother_id', $botMotherId)
                ->where('language_code', $stat['language'])
                ->where('type', $stat['type'])
                ->first();
            
            if ($botInfo) {
                $botName = $stat['type'] == 'telegram' 
                    ? $botInfo->telegram_bot_name 
                    : $botInfo->bale_bot_name;
            }
            
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
     * دریافت آمار یک زبان خاص در تمام پلتفرم‌ها
     */
    public function getStatsByLanguage(string $language, int $botMotherId = 1, int $days = 30): array
    {
        $allPlatforms = ['telegram', 'bale'];
        $allBotsStats = [];
        $totalUsers = 0;
        $totalRequests = 0;
        
        foreach ($allPlatforms as $platform) {
            $botsStats = BotLog::getStatsByLanguageWithBots($language, $platform, $botMotherId, $days);
            foreach ($botsStats as $stat) {
                $allBotsStats[] = $stat;
                $totalUsers += $stat['unique_users'];
                $totalRequests += $stat['total_requests'];
            }
        }
        
        return [
            'language' => $language,
            'language_name' => AdminHelper::getLanguageName($language),
            'total_users' => $totalUsers,
            'total_requests' => $totalRequests,
            'bots' => $allBotsStats,
        ];
    }

    /**
     * آماده‌سازی broadcast و ذخیره در cache برای تأیید ادمین
     */
    public function prepareBroadcast(
        string $language,
        string $message,
        int $botMotherId,
        string $adminChatId
    ): array {
        $stats = $this->getStatsByLanguage($language, $botMotherId);
        
        $cacheKey = self::CACHE_PREFIX . $adminChatId;
        Cache::put($cacheKey, [
            'language' => $language,
            'message' => $message,
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
        int $botMotherId,
        string $adminChatId
    ): array {
        $allStats = $this->getStats($botMotherId);
        $totalUsers = 0;
        
        $languageTotals = [];
        foreach ($allStats as $stat) {
            $lang = $stat['language'];
            if (!isset($languageTotals[$lang])) {
                $languageTotals[$lang] = 0;
            }
            $languageTotals[$lang] += $stat['unique_users'];
            $totalUsers += $stat['unique_users'];
        }
        
        $cacheKey = self::CACHE_PREFIX . $adminChatId;
        Cache::put($cacheKey, [
            'language' => '*',
            'message' => $message,
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
     * ابتدا پیام شروع و تخمین زمان می‌دهد، سپس ارسال می‌کند
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
        
        Cache::forget($cacheKey);
        
        $language = $pending['language'];
        $message = $pending['message'];
        $botMotherId = $pending['bot_mother_id'];
        
        if ($language === '*') {
            return $this->sendBroadcastToAll($message, $botMotherId, $adminChatId);
        }
        
        return $this->sendBroadcast($language, $message, $botMotherId, $adminChatId);
    }

    /**
     * ارسال به کاربران یک زبان در تمام پلتفرم‌ها
     * با تخمین زمان و ذخیره در تاریخچه
     */
    public function sendBroadcast(
        string $language,
        string $message,
        int $botMotherId,
        ?string $adminChatId = null
    ): array {
        // ========== مرحله ۱: تخمین زمان ==========
        $stats = $this->getStatsByLanguage($language, $botMotherId);
        $estimatedSeconds = BroadcastLog::estimateDuration($stats['total_users']);
        $estimatedMinutes = (int) ceil($estimatedSeconds / 60);
        
        // ثبت شروع در دیتابیس
        $broadcastLog = BroadcastLog::create([
            'language' => $language,
            'message' => $message,
            'admin_chat_id' => $adminChatId ?? 'unknown',
            'total_users' => $stats['total_users'],
            'sent_count' => 0,
            'error_count' => 0,
            'status' => 'sending',
            'started_at' => now(),
        ]);
        
        // ========== مرحله ۲: ارسال پیام شروع و تخمین ==========
        if ($adminChatId) {
            $startMessage = "🚀 *ارسال همگانی آغاز شد*\n";
            $startMessage .= "─────────────────────\n";
            $startMessage .= "🌍 زبان: {$stats['language_name']}\n";
            $startMessage .= "👥 کاربران: {$stats['total_users']}\n";
            $startMessage .= "⏱ زمان تخمینی: ";
            
            if ($estimatedMinutes < 1) {
                $startMessage .= "کمتر از یک دقیقه";
            } elseif ($estimatedMinutes < 60) {
                $startMessage .= "حدود {$estimatedMinutes} دقیقه";
            } else {
                $hours = floor($estimatedMinutes / 60);
                $mins = $estimatedMinutes % 60;
                $startMessage .= "حدود {$hours} ساعت و {$mins} دقیقه";
            }
            $startMessage .= "\n\n⏳ در حال ارسال... لطفاً صبر کنید.\n";
            $startMessage .= "📊 پس از اتمام، گزارش کامل ارسال خواهد شد.";
            
            // ارسال پیام شروع به ادمین (از طریق بله)
            try {
                $botBale = new Telegram(env('BOT_MOTHER_TOKEN_BALE'), 'bale');
                BotHelper::sendMessageByChatId($botBale, $adminChatId, $startMessage);
            } catch (Exception $e) {
                Log::error('Failed to send start message to admin', [
                    'admin_chat_id' => $adminChatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // ========== مرحله ۳: ارسال واقعی ==========
        $allPlatforms = ['telegram', 'bale'];
        $totalSent = 0;
        $totalErrors = 0;
        $allBotsReport = [];
        
        $startTime = time();
        
        foreach ($allPlatforms as $platform) {
            $chatIdsByBot = BotLog::getChatIdsByLanguageAndBot($language, $platform, $botMotherId);
            
            if (empty($chatIdsByBot)) {
                continue;
            }
            
            $token = $platform == 'bale' 
                ? env('BOT_MOTHER_TOKEN_BALE') 
                : env('BOT_MOTHER_TOKEN_TELEGRAM');
            
            if (!$token) {
                Log::warning("Token not found for platform: {$platform}");
                continue;
            }
            
            $bot = new Telegram($token, $platform);
            
            foreach ($chatIdsByBot as $botId => $chatIds) {
                $botName = 'ناشناخته';
                $botModel = Bot::find($botId);
                if ($botModel) {
                    $botName = $botModel->telegram_bot_name ?? $botModel->bale_bot_name ?? 'ناشناخته';
                }
                
                $botSent = 0;
                $botErrors = 0;
                
                foreach ($chatIds as $chatId) {
                    try {
                        BotHelper::sendMessageByChatId($bot, $chatId, $message);
                        $botSent++;
                        $totalSent++;
                        usleep(100000);
                    } catch (Exception $e) {
                        $botErrors++;
                        $totalErrors++;
                        Log::error('Broadcast error', [
                            'chat_id' => $chatId,
                            'bot_id' => $botId,
                            'platform' => $platform,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                $allBotsReport[] = [
                    'bot_id' => $botId,
                    'bot_name' => '@' . $botName,
                    'platform' => $platform,
                    'sent' => $botSent,
                    'errors' => $botErrors,
                    'total' => count($chatIds),
                ];
            }
        }
        
        $durationSeconds = time() - $startTime;
        
        // ========== مرحله ۴: به‌روزرسانی لاگ ==========
        $broadcastLog->update([
            'sent_count' => $totalSent,
            'error_count' => $totalErrors,
            'bots_report' => $allBotsReport,
            'completed_at' => now(),
            'duration_seconds' => $durationSeconds,
            'status' => $totalErrors > 0 && $totalSent == 0 ? 'failed' : 'completed',
        ]);
        
        // ========== مرحله ۵: ارسال گزارش به سوپرمین ==========
        $this->sendReportToAdmin($message, $language, $totalSent, $totalErrors, $allBotsReport, $durationSeconds);
        
        return [
            'success' => true,
            'language' => $language,
            'sent_count' => $totalSent,
            'error_count' => $totalErrors,
            'duration_seconds' => $durationSeconds,
            'bots_report' => $allBotsReport,
        ];
    }

    /**
     * ارسال به همه زبان‌ها در تمام پلتفرم‌ها
     */
    public function sendBroadcastToAll(
        string $message,
        int $botMotherId,
        ?string $adminChatId = null
    ): array {
        $allStats = $this->getStats($botMotherId);
        
        // محاسبه مجموع کاربران
        $totalAllUsers = 0;
        $languages = [];
        foreach ($allStats as $stat) {
            $languages[$stat['language']] = $stat['language_name'];
            $totalAllUsers += $stat['unique_users'];
        }
        
        // تخمین زمان
        $estimatedSeconds = BroadcastLog::estimateDuration($totalAllUsers);
        $estimatedMinutes = (int) ceil($estimatedSeconds / 60);
        
        // پیام شروع
        if ($adminChatId) {
            $startMessage = "🚀 *ارسال همگانی به همه زبان‌ها آغاز شد*\n";
            $startMessage .= "─────────────────────────────\n";
            $startMessage .= "👥 مجموع کاربران: {$totalAllUsers}\n";
            $startMessage .= "🌍 تعداد زبان‌ها: " . count($languages) . "\n";
            $startMessage .= "⏱ زمان تخمینی: ";
            
            if ($estimatedMinutes < 1) {
                $startMessage .= "کمتر از یک دقیقه";
            } elseif ($estimatedMinutes < 60) {
                $startMessage .= "حدود {$estimatedMinutes} دقیقه";
            } else {
                $hours = floor($estimatedMinutes / 60);
                $mins = $estimatedMinutes % 60;
                $startMessage .= "حدود {$hours} ساعت و {$mins} دقیقه";
            }
            $startMessage .= "\n\n⏳ در حال ارسال... لطفاً صبر کنید.";
            
            try {
                $botBale = new Telegram(env('BOT_MOTHER_TOKEN_BALE'), 'bale');
                BotHelper::sendMessageByChatId($botBale, $adminChatId, $startMessage);
            } catch (Exception $e) {
                Log::error('Failed to send start message', ['error' => $e->getMessage()]);
            }
        }
        
        $combinedResult = [
            'success' => true,
            'sent_count' => 0,
            'error_count' => 0,
            'duration_seconds' => 0,
            'languages_report' => [],
        ];
        
        $startTime = time();
        
        foreach ($languages as $langCode => $langName) {
            try {
                $result = $this->sendBroadcast($langCode, $message, $botMotherId, null);
                
                $combinedResult['sent_count'] += $result['sent_count'];
                $combinedResult['error_count'] += $result['error_count'];
                $combinedResult['languages_report'][] = [
                    'language' => $langCode,
                    'language_name' => $langName,
                    'sent' => $result['sent_count'],
                    'errors' => $result['error_count'],
                    'duration_seconds' => $result['duration_seconds'] ?? 0,
                    'bots' => $result['bots_report'],
                ];
            } catch (Exception $e) {
                Log::error("Error broadcasting to language {$langCode}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $combinedResult['duration_seconds'] = time() - $startTime;
        
        return $combinedResult;
    }

    /**
     * دریافت تاریخچه ارسال‌های همگانی
     */
    public function getHistory(int $limit = 15): array
    {
        return BroadcastLog::where('status', 'completed')
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * فرمت تاریخچه برای نمایش
     */
    public function formatHistoryMessage(): string
    {
        $history = $this->getHistory();
        
        if (empty($history)) {
            return "📭 هیچ ارسال همگانی‌ای ثبت نشده است.";
        }
        
        $message = "📋 *تاریخچه ارسال‌های همگانی*\n";
        $message .= "────────────────────────\n\n";
        
        $index = 1;
        foreach ($history as $log) {
            $date = \Carbon\Carbon::parse($log['created_at'])->format('Y-m-d H:i');
            $langName = AdminHelper::getLanguageName($log['language']);
            $duration = $log['duration_seconds'] ?? 0;
            
            if ($duration < 60) {
                $timeStr = "{$duration} ثانیه";
            } else {
                $mins = floor($duration / 60);
                $secs = $duration % 60;
                $timeStr = "{$mins} دقیقه و {$secs} ثانیه";
            }
            
            $message .= "{$index}. {$langName}\n";
            $message .= "   📅 {$date}\n";
            $message .= "   👥 {$log['total_users']} هدف | ✅{$log['sent_count']} ارسال";
            if ($log['error_count'] > 0) {
                $message .= " | ❌{$log['error_count']} خطا";
            }
            $message .= "\n   ⏱ {$timeStr}\n\n";
            
            $index++;
        }
        
        $message .= "💡 میانگین سرعت: " . BroadcastLog::getAverageSpeed() . " کاربر/ثانیه\n";
        $message .= "📊 مجموع: " . array_sum(array_column($history, 'sent_count')) . " ارسال موفق";
        
        return $message;
    }

    /**
     * ارسال گزارش به سوپرمین
     */
    private function sendReportToAdmin(
        string $message,
        string $language,
        int $totalSent,
        int $totalErrors,
        array $botsReport,
        int $durationSeconds = 0
    ): void {
        $report = "📊 *گزارش ارسال پیام همگانی*\n";
        $report .= "─────────────────────\n";
        $report .= "🌍 زبان: " . AdminHelper::getLanguageName($language) . "\n";
        $report .= "✅ ارسال موفق: {$totalSent}\n";
        $report .= "❌ خطا: {$totalErrors}\n";
        
        if ($durationSeconds > 0) {
            $report .= "⏱ مدت زمان: ";
            if ($durationSeconds < 60) {
                $report .= "{$durationSeconds} ثانیه";
            } else {
                $mins = floor($durationSeconds / 60);
                $secs = $durationSeconds % 60;
                $report .= "{$mins} دقیقه و {$secs} ثانیه";
            }
            $report .= "\n";
        }
        
        if (!empty($botsReport)) {
            $report .= "\n📋 *تفکیک ربات‌ها:*\n";
            foreach ($botsReport as $botReport) {
                $platformIcon = $botReport['platform'] == 'bale' ? '💬' : '📱';
                $report .= "└ {$platformIcon} {$botReport['bot_name']}: ";
                $report .= "✅{$botReport['sent']} ";
                if ($botReport['errors'] > 0) {
                    $report .= "❌{$botReport['errors']} ";
                }
                $report .= "(کل: {$botReport['total']})\n";
            }
        }
        
        BotHelper::sendMessageToSuperAdmin($report, 'bale');
    }

    /**
     * ساخت متن پیام آمار
     */
    public function formatStatsMessage(array $stats): string
    {
        $message = "📊 *آمار کاربران ربات‌های قرآنی (۳۰ روز اخیر)*\n";
        $message .= "─────────────────────────────\n";
        
        $languages = [];
        foreach ($stats as $stat) {
            $lang = $stat['language'];
            if (!isset($languages[$lang])) {
                $languages[$lang] = [
                    'name' => $stat['language_name'],
                    'total_users' => 0,
                    'total_requests' => 0,
                    'platforms' => [],
                ];
            }
            $languages[$lang]['total_users'] += $stat['unique_users'];
            $languages[$lang]['total_requests'] += $stat['total_requests'];
            $languages[$lang]['platforms'][] = $stat;
        }
        
        $totalUsers = 0;
        $totalRequests = 0;
        
        foreach ($languages as $lang => $data) {
            $users = number_format($data['total_users']);
            $requests = number_format($data['total_requests']);
            
            $platforms = [];
            foreach ($data['platforms'] as $p) {
                $icon = $p['platform'] == 'bale' ? '💬' : '📱';
                $botInfo = $p['bot_name'] ? " @{$p['bot_name']}" : '';
                $platforms[] = "{$icon}{$botInfo}(" . number_format($p['unique_users']) . ")";
            }
            
            $message .= "{$data['name']}: 👥 {$users} | 📨 {$requests}\n";
            $message .= "  └ " . implode(' | ', $platforms) . "\n";
            
            $totalUsers += $data['total_users'];
            $totalRequests += $data['total_requests'];
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
        $message .= "└ `///broadcast-history` ← تاریخچه ارسال‌ها\n";
        
        return $message;
    }

    /**
     * ساخت متن تأیید ارسال با تخمین زمان
     */
    public function formatConfirmationMessage(array $stats, string $message, int $totalUsers): string
    {
        $estimatedSeconds = BroadcastLog::estimateDuration($totalUsers);
        $estimatedMinutes = (int) ceil($estimatedSeconds / 60);
        
        $text = "📋 *تأیید ارسال پیام همگانی*\n";
        $text .= "────────────────────────\n";
        $text .= "🌍 زبان: {$stats['language_name']}\n";
        $text .= "👥 تعداد: {$totalUsers} کاربر\n\n";
        
        if (!empty($stats['bots'])) {
            $text .= "📋 *تفکیک ربات‌ها:*\n";
            foreach ($stats['bots'] as $bot) {
                $text .= "└ {$bot['bot_name']}: {$bot['unique_users']} کاربر\n";
            }
            $text .= "\n";
        }
        
        $text .= "⏱ *تخمین زمان:* ";
        if ($estimatedMinutes < 1) {
            $text .= "کمتر از یک دقیقه\n";
        } elseif ($estimatedMinutes < 60) {
            $text .= "حدود {$estimatedMinutes} دقیقه\n";
        } else {
            $hours = floor($estimatedMinutes / 60);
            $mins = $estimatedMinutes % 60;
            $text .= "حدود {$hours} ساعت و {$mins} دقیقه\n";
        }
        
        $text .= "\n📝 *متن پیام:*\n";
        $text .= "```\n{$message}\n```\n\n";
        $text .= "✅ برای تأیید: `/confirm`\n";
        $text .= "❌ برای لغو: هر دستور دیگری\n";
        $text .= "⏰ این درخواست ۵ دقیقه اعتبار دارد.\n";
        
        return $text;
    }
}
