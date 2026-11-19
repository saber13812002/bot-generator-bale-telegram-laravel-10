<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmailThresholdSetting extends Model
{
    use HasFactory;

    protected $table = 'email_threshold_settings';

    protected $fillable = [
        'threshold_type',
        'max_emails',
        'current_count',
        'reset_at',
    ];

    protected $casts = [
        'max_emails' => 'integer',
        'current_count' => 'integer',
        'reset_at' => 'datetime',
    ];

    /**
     * چک کردن آیا می‌توان ایمیل ارسال کرد
     */
    public static function checkThreshold(string $type = 'daily'): bool
    {
        $setting = self::where('threshold_type', $type)->first();
        
        if (!$setting) {
            // اگر وجود نداشت، ایجاد می‌کنیم با مقادیر پیش‌فرض
            $setting = self::createDefault($type);
        }

        // چک کردن reset
        self::checkAndReset($setting);

        return $setting->current_count < $setting->max_emails;
    }

    /**
     * افزایش تعداد
     */
    public static function incrementCount(string $type = 'daily'): void
    {
        $setting = self::where('threshold_type', $type)->first();
        
        if (!$setting) {
            $setting = self::createDefault($type);
        }

        // چک کردن reset
        self::checkAndReset($setting);

        $setting->increment('current_count');
    }

    /**
     * Reset کردن در ابتدای دوره جدید
     */
    public static function resetCount(string $type = 'daily'): void
    {
        $setting = self::where('threshold_type', $type)->first();
        
        if (!$setting) {
            return;
        }

        $setting->update([
            'current_count' => 0,
            'reset_at' => now(),
        ]);
    }

    /**
     * دریافت تعداد فعلی
     */
    public static function getCurrentCount(string $type = 'daily'): int
    {
        $setting = self::where('threshold_type', $type)->first();
        
        if (!$setting) {
            return 0;
        }

        // چک کردن reset
        self::checkAndReset($setting);

        return $setting->current_count;
    }

    /**
     * دریافت حداکثر تعداد
     */
    public static function getMaxCount(string $type = 'daily'): int
    {
        $setting = self::where('threshold_type', $type)->first();
        
        if (!$setting) {
            $setting = self::createDefault($type);
        }

        return $setting->max_emails;
    }

    /**
     * چک کردن و reset کردن در صورت نیاز
     */
    protected static function checkAndReset(self $setting): void
    {
        $now = now();
        $shouldReset = false;

        switch ($setting->threshold_type) {
            case 'daily':
                // اگر reset_at وجود ندارد یا امروز نیست
                if (!$setting->reset_at || !$setting->reset_at->isToday()) {
                    $shouldReset = true;
                }
                break;

            case 'weekly':
                // اگر reset_at وجود ندارد یا این هفته نیست
                if (!$setting->reset_at || !$setting->reset_at->isCurrentWeek()) {
                    $shouldReset = true;
                }
                break;

            case 'monthly':
                // اگر reset_at وجود ندارد یا این ماه نیست
                if (!$setting->reset_at || !$setting->reset_at->isCurrentMonth()) {
                    $shouldReset = true;
                }
                break;
        }

        if ($shouldReset) {
            $setting->update([
                'current_count' => 0,
                'reset_at' => $now,
            ]);
        }
    }

    /**
     * ایجاد تنظیمات پیش‌فرض
     */
    protected static function createDefault(string $type): self
    {
        $defaults = [
            'daily' => 100,
            'weekly' => 500,
            'monthly' => 2000,
        ];

        return self::create([
            'threshold_type' => $type,
            'max_emails' => $defaults[$type] ?? 100,
            'current_count' => 0,
            'reset_at' => now(),
        ]);
    }
}
