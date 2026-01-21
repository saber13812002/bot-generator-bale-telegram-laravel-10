<?php

namespace App\Services;

use App\Helpers\EmailAdminHelper;
use App\Models\EmailThresholdSetting;
use Illuminate\Support\Facades\Log;

class EmailThresholdService
{
    /**
     * چک کردن آیا می‌توان ایمیل ارسال کرد
     */
    public function canSendEmail(string $type = 'daily'): bool
    {
        $canSend = EmailThresholdSetting::checkThreshold($type);
        
        if (!$canSend) {
            $currentCount = EmailThresholdSetting::getCurrentCount($type);
            $maxCount = EmailThresholdSetting::getMaxCount($type);
            
            Log::warning('⚠️ [EmailThresholdService] Threshold exceeded', [
                'type' => $type,
                'current' => $currentCount,
                'max' => $maxCount
            ]);

            // ارسال پیام به ادمین‌ها
            $this->notifyAdminsIfThresholdExceeded($type, $currentCount);
        }

        return $canSend;
    }

    /**
     * افزایش تعداد بعد از ارسال موفق
     */
    public function incrementSentCount(string $type = 'daily'): void
    {
        EmailThresholdSetting::incrementCount($type);
        
        $currentCount = EmailThresholdSetting::getCurrentCount($type);
        $maxCount = EmailThresholdSetting::getMaxCount($type);
        
        Log::info('📊 [EmailThresholdService] Count incremented', [
            'type' => $type,
            'current' => $currentCount,
            'max' => $maxCount,
            'remaining' => $maxCount - $currentCount
        ]);
    }

    /**
     * ارسال پیام به ادمین‌ها در صورت رد شدن از Threshold
     */
    public function notifyAdminsIfThresholdExceeded(string $type, int $attemptedCount): void
    {
        $maxCount = EmailThresholdSetting::getMaxCount($type);
        
        $message = "⚠️ هشدار: Threshold ایمیل رد شد\n\n";
        $message .= "📊 نوع: " . $this->getTypeLabel($type) . "\n";
        $message .= "📈 تعداد فعلی: {$attemptedCount}\n";
        $message .= "🔴 حداکثر مجاز: {$maxCount}\n";
        $message .= "⏰ زمان: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $message .= "ایمیل‌های جدید ارسال نخواهند شد تا زمانی که Threshold reset شود.";

        // ارسال به همه ادمین‌ها
        EmailAdminHelper::sendThresholdExceededNotification($type, $attemptedCount, $maxCount);
        
        Log::error('❌ [EmailThresholdService] Threshold exceeded notification sent', [
            'type' => $type,
            'attempted_count' => $attemptedCount,
            'max_count' => $maxCount
        ]);
    }

    /**
     * دریافت برچسب نوع Threshold
     */
    protected function getTypeLabel(string $type): string
    {
        $labels = [
            'daily' => 'روزانه',
            'weekly' => 'هفتگی',
            'monthly' => 'ماهانه',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * دریافت اطلاعات Threshold
     */
    public function getThresholdInfo(string $type = 'daily'): array
    {
        $current = EmailThresholdSetting::getCurrentCount($type);
        $max = EmailThresholdSetting::getMaxCount($type);
        
        return [
            'type' => $type,
            'current' => $current,
            'max' => $max,
            'remaining' => max(0, $max - $current),
            'percentage' => $max > 0 ? round(($current / $max) * 100, 2) : 0,
        ];
    }
}
