<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WS-H — ردیاب مکالمه دستیار رشدیار (متن آزاد کاربر + پاسخ LLM).
 * هر پیام (user/assistant) یک ردیف؛ usage و خطا روی ردیف ذخیره می‌شود.
 */
class GrowthAssistantMessage extends Model
{
    protected $table = 'growth_assistant_messages';

    protected $fillable = [
        'growth_profile_id',
        'bot_user_id',
        'role',
        'content',
        'usage',
        'error',
        'ai_job_id',
    ];

    protected $casts = [
        'usage' => 'array',
        'ai_job_id' => 'integer',
        'growth_profile_id' => 'integer',
        'bot_user_id' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GrowthProfile::class, 'growth_profile_id');
    }
}
