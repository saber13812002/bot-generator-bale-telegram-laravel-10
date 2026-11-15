<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI-PILOT-ROLLOUT-05 (R2): override سراسری flagهای AI — بدون re-deploy.
 *
 * هر row یکی از کلیدهای config('ai.flags.*') (مثلاً ai_global_enabled) را
 * در زمان اجرا override می‌کند. نبود row = config/env حاکم است.
 * تغییر این جدول audit log دارد (ai_settings_audit_logs با bot_id NULL).
 */
class AiFlag extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'boolean',
    ];
}
