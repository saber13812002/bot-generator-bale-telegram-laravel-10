<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppLogEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'bot_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if ($entry->created_at === null) {
                $entry->created_at = now();
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

    public static function pruneExcess(int $maxRows): int
    {
        if ($maxRows < 1) {
            return 0;
        }

        $cutoffId = static::query()
            ->orderByDesc('id')
            ->skip($maxRows)
            ->value('id');

        if (!$cutoffId) {
            return 0;
        }

        return static::query()->where('id', '<=', $cutoffId)->delete();
    }
}
