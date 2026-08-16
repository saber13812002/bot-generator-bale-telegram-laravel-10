<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotHealthEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'bot_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if ($event->created_at === null) {
                $event->created_at = now();
            }
        });
    }

    public function scopeForBot($query, ?int $botId)
    {
        if ($botId) {
            $query->where('bot_id', $botId);
        }

        return $query;
    }

    public static function pruneOlderThan(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        return static::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
