<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BroadcastLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'language',
        'message',
        'admin_chat_id',
        'total_users',
        'sent_count',
        'error_count',
        'bots_report',
        'started_at',
        'completed_at',
        'duration_seconds',
        'status',
    ];

    protected $casts = [
        'bots_report' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_users' => 'integer',
        'sent_count' => 'integer',
        'error_count' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /**
     * محاسبه سرعت ارسال (کاربر در ثانیه)
     */
    public function getSpeedAttribute(): float
    {
        if ($this->duration_seconds > 0) {
            return round($this->sent_count / $this->duration_seconds, 2);
        }
        return 0;
    }

    /**
     * دریافت میانگین سرعت ارسال از تاریخچه
     */
    public static function getAverageSpeed(int $limit = 10): float
    {
        $logs = self::where('status', 'completed')
            ->where('sent_count', '>', 0)
            ->where('duration_seconds', '>', 0)
            ->latest()
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            return 5; // پیش‌فرض: 5 کاربر در ثانیه
        }

        $totalSpeed = 0;
        $count = 0;
        foreach ($logs as $log) {
            if ($log->duration_seconds > 0) {
                $totalSpeed += $log->sent_count / $log->duration_seconds;
                $count++;
            }
        }

        return $count > 0 ? round($totalSpeed / $count, 2) : 5;
    }

    /**
     * تخمین مدت زمان ارسال بر اساس تعداد کاربران
     */
    public static function estimateDuration(int $userCount): int
    {
        $speed = self::getAverageSpeed();
        if ($speed <= 0) $speed = 5;
        
        return (int) ceil($userCount / $speed);
    }
}
