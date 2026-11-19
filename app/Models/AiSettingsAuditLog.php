<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI-PILOT-ROLLOUT-05 (R1): audit log تغییرات تنظیمات AI.
 *
 * هر تغییر کلیدهای مدیریت‌شده bots.ai_settings (stage rollout، allowlist،
 * pro-override، feature flags) یک row در این جدول می‌سازد.
 * bot_id NULL = تغییر سراسری (R2: ai_flags).
 *
 * این جدول read-mostly است: نوشتن آن هرگز نباید save اصلی bot را بشکند
 * (caller try/catch دارد).
 */
class AiSettingsAuditLog extends Model
{
    protected $fillable = [
        'bot_id',
        'key',
        'old_value',
        'new_value',
        'actor',
        'reason',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }
}
