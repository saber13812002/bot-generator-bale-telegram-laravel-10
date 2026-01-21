<?php

namespace App\Helpers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use Telegram;
use Illuminate\Support\Facades\Log;

class EmailAdminHelper
{
    /**
     * ارسال پیام به همه ادمین‌ها
     */
    public static function sendToAllAdmins(string $message, string $type = 'telegram'): void
    {
        $admins = AdminHelper::getAdmins();
        $token = $type === 'telegram' 
            ? env('BOT_MOTHER_TOKEN_TELEGRAM') 
            : env('BOT_MOTHER_TOKEN_BALE');

        if (!$token) {
            Log::error('❌ [EmailAdminHelper] Bot token not found', ['type' => $type]);
            return;
        }

        $bot = new Telegram($token, $type);
        $sentCount = 0;
        $failedCount = 0;

        foreach ($admins as $chatId) {
            if (!$chatId) {
                continue;
            }

            try {
                BotHelper::sendMessageByChatId($bot, $chatId, $message);
                $sentCount++;
                
                Log::info('✅ [EmailAdminHelper] Message sent to admin', [
                    'chat_id' => $chatId,
                    'type' => $type
                ]);
            } catch (\Exception $e) {
                $failedCount++;
                
                Log::error('❌ [EmailAdminHelper] Failed to send message to admin', [
                    'chat_id' => $chatId,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('📊 [EmailAdminHelper] Notification summary', [
            'type' => $type,
            'total_admins' => count($admins),
            'sent' => $sentCount,
            'failed' => $failedCount
        ]);
    }

    /**
     * ارسال پیام هشدار Threshold به ادمین‌ها
     */
    public static function sendThresholdExceededNotification(string $thresholdType, int $attemptedCount, int $maxCount): void
    {
        $typeLabels = [
            'daily' => 'روزانه',
            'weekly' => 'هفتگی',
            'monthly' => 'ماهانه',
        ];

        $typeLabel = $typeLabels[$thresholdType] ?? $thresholdType;

        $message = "⚠️ هشدار: Threshold ایمیل رد شد\n\n";
        $message .= "📊 نوع: {$typeLabel}\n";
        $message .= "📈 تعداد فعلی: {$attemptedCount}\n";
        $message .= "🔴 حداکثر مجاز: {$maxCount}\n";
        $message .= "⏰ زمان: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $message .= "⚠️ ایمیل‌های جدید ارسال نخواهند شد تا زمانی که Threshold reset شود.";

        // ارسال به Telegram و Bale
        self::sendToAllAdmins($message, 'telegram');
        self::sendToAllAdmins($message, 'bale');
    }

    /**
     * ارسال پیام تست به ادمین‌ها
     */
    public static function sendTestNotification(string $testMessage): void
    {
        $message = "🧪 تست ارسال پیام به ادمین\n\n";
        $message .= $testMessage . "\n\n";
        $message .= "⏰ زمان: " . now()->format('Y-m-d H:i:s');

        self::sendToAllAdmins($message, 'telegram');
        self::sendToAllAdmins($message, 'bale');
    }
}
