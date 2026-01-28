<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_id',
        'name',
        'route',
        'description',
        'detailed_description',
        'requires_bot_mother_id',
        'requires_token',
        'requires_language',
        'supports_multiple_languages',
        'is_active',
        'sample_telegram_link',
        'sample_bale_link',
        'blog_virgool_link',
        'blog_medium_link',
        'icon_emoji',
        'icon_svg',
        'image_path',
        'image_url',
        'features',
        'usage_instructions',
        'technical_details',
    ];

    protected $casts = [
        'requires_bot_mother_id' => 'boolean',
        'requires_token' => 'boolean',
        'requires_language' => 'boolean',
        'supports_multiple_languages' => 'boolean',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    /**
     * رابطه با Bots (بر اساس endpoint_id که string است)
     */
    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class, 'endpoint_id', 'endpoint_id');
    }

    /**
     * ربات‌های مرتبط (این ربات به عنوان ربات اصلی)
     */
    public function relatedBots(): BelongsToMany
    {
        return $this->belongsToMany(
            WebhookEndpoint::class,
            'webhook_endpoint_related_bots',
            'webhook_endpoint_id',
            'related_webhook_endpoint_id'
        )->withPivot('order')
          ->withTimestamps()
          ->orderByPivot('order');
    }

    /**
     * ربات‌هایی که این ربات را به عنوان مرتبط دارند (reverse relationship)
     */
    public function relatedToBots(): BelongsToMany
    {
        return $this->belongsToMany(
            WebhookEndpoint::class,
            'webhook_endpoint_related_bots',
            'related_webhook_endpoint_id',
            'webhook_endpoint_id'
        )->withPivot('order')
          ->withTimestamps()
          ->orderByPivot('order');
    }

    /**
     * دریافت ربات‌های مرتبط با محدودیت
     */
    public function getRelatedBots(int $limit = 3)
    {
        return $this->relatedBots()
            ->where('webhook_endpoints.is_active', true)
            ->limit($limit)
            ->get();
    }

    /**
     * Accessor برای دریافت URL عکس
     * اولویت با image_url، سپس image_path
     */
    public function getImageAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }

        return null;
    }
}
