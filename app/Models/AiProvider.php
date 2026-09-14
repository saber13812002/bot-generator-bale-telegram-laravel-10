<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'api_key',
        'model_name',
        'default_prompt',
        'provider_type',
        'is_active',
        'notify_bot_token',
        'notify_chat_id',
        'notify_platform',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
        'last_ping_ms',
        'available_models',
        'settings',
    ];

    protected $casts = [
        'api_key'          => 'encrypted',
        'settings'         => 'array',
        'available_models' => 'array',
        'is_active'        => 'boolean',
        'last_tested_at'   => 'datetime',
        'last_ping_ms'     => 'integer',
    ];

    /**
     * لاگ‌های تست‌های انجام‌شده
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AiProviderLog::class)->orderByDesc('created_at');
    }

    /**
     * فقط provider های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * رکوردهایی که بیش از 1 ساعت پیش تست شده‌اند یا هنوز تست نشده‌اند
     */
    public function scopeNeedsTest($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_tested_at')
              ->orWhere('last_tested_at', '<', now()->subHour());
        });
    }
}
