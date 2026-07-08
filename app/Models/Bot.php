<?php

namespace App\Models;

use App\Modules\BotOwner\Models\BotAdminPanelUser;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->belongsTo(BotOwner::class, 'bot_owner_id');
    }

    /**
     * دسته‌بندی‌های محتوایی این ربات
     */
    public function contentCategories(): HasMany
    {
        return $this->hasMany(ContentCategory::class, 'bot_id');
    }

    /**
     * آیتم‌های محتوایی این ربات
     */
    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'bot_id');
    }

    /**
     * ادمین‌های پنل برای این ربات (غیر از مالک اصلی)
     */
    public function adminPanelUsers(): HasMany
    {
        return $this->hasMany(BotAdminPanelUser::class, 'bot_id');
    }

    /**
     * آیا این owner می‌تواند این ربات را مدیریت کند؟
     * مالک اصلی یا ادمین تأییدشده
     */
    public function canBeManagedBy(BotOwner $owner): bool
    {
        if ($this->bot_owner_id === $owner->id) {
            return true;
        }

        return $this->adminPanelUsers()
            ->where('bot_owner_id', $owner->id)
            ->exists();
    }

    /**
     * آیا این owner ادمین پنل این ربات است (نه مالک اصلی)؟
     */
    public function isAdminPanelUser(BotOwner $owner): bool
    {
        if ($this->bot_owner_id === $owner->id) {
            return false;
        }

        return $this->adminPanelUsers()
            ->where('bot_owner_id', $owner->id)
            ->exists();
    }
}
