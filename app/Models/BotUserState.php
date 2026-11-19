<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotUserState extends Model
{
    protected $fillable = [
        'bot_user_id',
        'bot_mother_id',
        'state',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Relation to BotUsers
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    /**
     * Scope: فقط state های منقضی نشده
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: state های منقضی شده
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
    }

    /**
     * چک کردن اینکه state منقضی شده یا نه
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * دریافت data به صورت مقدار خاص
     */
    public function getData(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * ست کردن data
     */
    public function setData(string $key, $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->data = $data;
        $this->save();
    }
}
