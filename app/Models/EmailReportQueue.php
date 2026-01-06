<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailReportQueue extends Model
{
    use HasFactory;

    protected $table = 'email_report_queue';

    protected $fillable = [
        'user_id',
        'chat_id',
        'email',
        'report_period_start',
        'report_period_end',
        'status',
        'sent_at',
        'failed_reason',
        'retry_count',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'report_period_start' => 'date',
        'report_period_end' => 'date',
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * رابطه با کاربر ربات
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'user_id');
    }

    /**
     * Scope: ایمیل‌های در انتظار ارسال
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: ایمیل‌های ارسال شده
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: ایمیل‌های ناموفق
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: ایمیل‌هایی که قابل retry هستند
     */
    public function scopeRetryable(Builder $query, int $maxRetries = 3): Builder
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * علامت‌گذاری به عنوان ارسال شده
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان ناموفق
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failed_reason' => $reason,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
