<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bot extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * رابطه با WebhookEndpoint
     */
    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id', 'endpoint_id');
    }

    /**
     * رابطه با Language
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_code', 'code');
    }

    /**
     * رابطه با مالک ربات (پنل وب)
     */
    public function botOwner(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\BotOwner\Models\BotOwner::class, 'bot_owner_id');
    }

    /**
     * دسته‌بندی‌های محتوایی این ربات
     */
    public function contentCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContentCategory::class, 'bot_id');
    }

    /**
     * آیتم‌های محتوایی این ربات
     */
    public function contentItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContentItem::class, 'bot_id');
    }
}
