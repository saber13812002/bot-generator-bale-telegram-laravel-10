<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WeeklyReadInvitation extends Model
{
    /**
     * day_of_week numbering (same as Carbon::dayOfWeek / JS getDay):
     * 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday.
     * Default 5 => Friday (جمعه).
     */
    public const DAY_SUNDAY = 0;
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;

    public const DEFAULT_DAY = 5;

    protected $fillable = [
        'destination_id',
        'day_of_week',
        'max_post_id',
        'enabled',
        'last_invited_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'max_post_id' => 'integer',
        'enabled' => 'boolean',
        'last_invited_at' => 'datetime',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(ChannelPosterDestination::class, 'destination_id');
    }

    /**
     * Is this invitation due to be sent at $now?
     * - enabled
     * - configured day matches today
     * - not already invited within the last 6 days (once per week)
     */
    public function isDueAt(Carbon $now): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->day_of_week !== $now->dayOfWeek) {
            return false;
        }

        if ($this->last_invited_at !== null
            && $this->last_invited_at->copy()->startOfDay()->gte($now->copy()->subDays(5)->startOfDay())
        ) {
            return false;
        }

        return true;
    }

    public static function dayNameFa(int $day): string
    {
        return match ($day) {
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنجشنبه',
            5 => 'جمعه',
            6 => 'شنبه',
            default => '؟',
        };
    }
}
