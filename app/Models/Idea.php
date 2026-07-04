<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Idea extends Model
{
    protected $fillable = [
        'tracking_code',
        'endpoint_id',
        'title',
        'description',
        'submitter_name',
        'submitter_contact',
        'phone',
        'email',
        'email_verified_at',
        'notify_by_bot',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'notify_by_bot' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(IdeaMessage::class);
    }

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id', 'endpoint_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeByTrackingCode($query, string $code)
    {
        return $query->where('tracking_code', $code);
    }

    public function scopeByPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public static function generateTrackingCode(): string
    {
        $prefix = 'ID-' . now()->format('ym');
        $last = self::where('tracking_code', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('tracking_code');

        $num = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => '🟡 در انتظار بررسی',
            'reviewing' => '🔵 در حال بررسی',
            'approved' => '🟢 تأیید شده',
            'rejected' => '🔴 رد شده',
            'done' => '✅ انجام شده',
            default => $this->status,
        };
    }
}
