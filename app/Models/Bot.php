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

    protected $casts = [
        'ai_settings' => 'array',
    ];

    /**
     * WS-E: flag این bot برای یک capability AI (override لایه‌ی 2).
     * null = بدون override (مقدار سراسری حاکم است).
     * مقادیر قابل قبول: bool واقعی، یا 'on'/'off' (مقدار Select در Nova)، 'follow' = null.
     */
    public function aiFlag(string $feature): ?bool
    {
        $flags = $this->ai_settings['ai_feature_flags'] ?? [];

        if (! array_key_exists($feature, $flags)) {
            return null;
        }

        $v = $flags[$feature];

        if (is_bool($v)) {
            return $v;
        }

        return match ((string) $v) {
            'on', 'true', '1', '1.0'   => true,
            'off', 'false', '0', '0.0' => false,
            default                     => null,
        };
    }

    /** WS-E: set/remove override یک capability (null = حذف override) */
    public function setAiFlag(string $feature, ?bool $value): self
    {
        $settings = $this->ai_settings ?? [];
        $flags    = $settings['ai_feature_flags'] ?? [];

        if ($value === null) {
            unset($flags[$feature]);
        } else {
            $flags[$feature] = $value;
        }

        $settings['ai_feature_flags'] = $flags;
        $this->forceFill(['ai_settings' => $settings])->save();

        return $this;
    }

    /** WS-E: test group allowlist (null = بدون allowlist) */
    public function aiTestGroup(): ?array
    {
        $raw = $this->ai_settings['ai_test_group'] ?? null;

        return is_array($raw) && $raw !== []
            ? array_values(array_filter(array_map('intval', $raw), fn ($v) => $v > 0))
            : null;
    }

    /** WS-E: set/remove test group allowlist */
    public function setAiTestGroup(?array $userIds): self
    {
        $settings = $this->ai_settings ?? [];

        if ($userIds === null) {
            unset($settings['ai_test_group']);
        } else {
            $settings['ai_test_group'] = array_values(array_filter(array_map('intval', $userIds), fn ($v) => $v > 0));
        }

        $this->forceFill(['ai_settings' => $settings])->save();

        return $this;
    }

    /** WS-E: map از attribute‌های فیک Nova به capability */
    private const AI_CAP_ATTRS = [
        'ai_llm'               => 'llm',
        'ai_ocr'               => 'ocr',
        'ai_asr'               => 'asr',
        'ai_rag'               => 'rag',
        'ai_tts'               => 'tts',
        'ai_growth_companion'  => 'growth_companion',
    ];

    /**
     * WS-E: نگاشت فیلدهای Nova (Select/Text با attribute غیر-ستونی) به ai_settings.
     * فقط در memory می‌نویسد؛ persistence با save() معمولی (Nova یا caller).
     * مقادیر Select: 'follow'|'on'|'off' → null|true|false
     * Text ai_test_group: '123, 456' → [123,456] (خالی → حذف)
     */
    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, self::AI_CAP_ATTRS)) {
            $cap = self::AI_CAP_ATTRS[$key];
            $v   = match ((string) $value) {
                'on', 'true', '1', '1.0'   => true,
                'off', 'false', '0', '0.0' => false,
                default                    => null,
            };

            $settings = $this->ai_settings ?? [];
            $flags    = $settings['ai_feature_flags'] ?? [];
            if ($v === null) {
                unset($flags[$cap]);
            } else {
                $flags[$cap] = $v;
            }
            $settings['ai_feature_flags'] = $flags;
            $this->ai_settings = $settings;
            return;
        }

        if ($key === 'ai_test_group') {
            $ids = array_values(array_filter(array_map(
                'intval',
                array_map('trim', explode(',', (string) $value))
            ), fn ($n) => $n > 0));

            $settings = $this->ai_settings ?? [];
            if ($ids === []) {
                unset($settings['ai_test_group']);
            } else {
                $settings['ai_test_group'] = $ids;
            }
            $this->ai_settings = $settings;
            return;
        }

        parent::setAttribute($key, $value);
    }

    /**
     * WS-E: مقادیر نمایشی فیلدهای Nova ('follow'|'on'|'off' و متن test group)
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, self::AI_CAP_ATTRS)) {
            return match (true) {
                $this->aiFlag(self::AI_CAP_ATTRS[$key]) === true  => 'on',
                $this->aiFlag(self::AI_CAP_ATTRS[$key]) === false => 'off',
                default                                           => 'follow',
            };
        }

        if ($key === 'ai_test_group') {
            $ids = $this->aiTestGroup();

            return $ids === null ? '' : implode(', ', $ids);
        }

        return parent::getAttribute($key);
    }

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
