<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerEstimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_id',
        'total_missed_prayers',
        'total_missed_rakats',
        'start_date',
        'notes',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'total_missed_prayers' => 'integer',
        'total_missed_rakats' => 'integer',
        'start_date' => 'date',
    ];

    /**
     * رابطه با کاربر ربات
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }

    /**
     * محاسبه رکعات باقی‌مانده
     * 
     * @param string $origin پلتفرم (telegram, bale)
     * @return int
     */
    public function calculateRemaining(string $origin): int
    {
        // دریافت مجموع رکعات ثبت شده
        $completedRakats = PrayerRecord::where('chat_id', $this->chat_id)
            ->where('origin', $origin)
            ->sum('rakats');

        $remaining = $this->total_missed_rakats - $completedRakats;
        
        return max(0, $remaining); // حداقل 0
    }

    /**
     * محاسبه درصد پیشرفت
     * 
     * @param string $origin پلتفرم (telegram, bale)
     * @return float
     */
    public function getProgressPercentage(string $origin): float
    {
        if ($this->total_missed_rakats == 0) {
            return 0;
        }

        $completedRakats = PrayerRecord::where('chat_id', $this->chat_id)
            ->where('origin', $origin)
            ->sum('rakats');

        $percentage = ($completedRakats / $this->total_missed_rakats) * 100;
        
        return min(100, round($percentage, 2)); // حداکثر 100%
    }

    /**
     * دریافت تعداد نمازهای کامل شده
     * 
     * @param string $origin پلتفرم (telegram, bale)
     * @return int
     */
    public function getCompletedPrayers(string $origin): int
    {
        $completedRakats = PrayerRecord::where('chat_id', $this->chat_id)
            ->where('origin', $origin)
            ->sum('rakats');

        // تخمین تعداد نماز بر اساس میانگین 3.5 رکعت به ازای هر نماز
        // (2+4+4+3+4) / 5 = 3.4 ~ 3.5
        return (int) floor($completedRakats / 3.5);
    }
}
