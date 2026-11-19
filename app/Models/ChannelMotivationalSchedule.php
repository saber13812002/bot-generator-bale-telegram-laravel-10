<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ChannelMotivationalSchedule extends Model
{
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BIWEEKLY = 'biweekly';
    public const FREQUENCY_DAILY = 'daily';

    public const DAY_SUNDAY = 0;
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;

    public const DEFAULT_DAY = 5;

    public const MAX_SENT_TEXTS = 20;
    public const LLM_TEXT_LIMIT = 200;

    public const DEFAULT_PROMPT = 'تو یک نویسنده‌ی انگیزشی فارسی هستی. یک جمله‌ی کوتاه و دلنشین انگیزشی (حداکثر ۲۰۰ نویسه) بنویس که برای انتشار در یک کانال عمومی مناسب باشد. '
        . 'لحن صمیمی، مثبت و کوتاه داشته باشد؛ از ایموجی زیاد و کلیشه‌های تکراری پرهیز کن. '
        . 'این جمله باید با جمله‌هایی که قبلاً ارسال شده‌اند (در صورت ارائه) تکراری یا هم‌شکل نباشد. '
        . 'فقط خودِ جمله را بنویس، بدون پیشوند، بدون ویرگول‌گذاری اضافی و بدون توضیح.';

    protected $fillable = [
        'destination_id',
        'day_of_week',
        'frequency',
        'prompt',
        'last_sent_at',
        'enabled',
        'sent_texts',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'enabled' => 'boolean',
        'last_sent_at' => 'datetime',
        'sent_texts' => 'array',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(ChannelPosterDestination::class, 'destination_id');
    }

    public function getEffectivePrompt(): string
    {
        return trim((string) $this->prompt) !== '' ? (string) $this->prompt : self::DEFAULT_PROMPT;
    }

    private function dueDays(): int
    {
        return match ($this->frequency) {
            self::FREQUENCY_DAILY => 1,
            self::FREQUENCY_BIWEEKLY => 14,
            default => 7,
        };
    }

    /**
     * آیا امروز نوبت ارسال است؟ (روز هفته + فاصله‌ی از آخرین ارسال)
     */
    public function isDueAt(Carbon $now): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->day_of_week !== $now->dayOfWeek) {
            return false;
        }

        $days = $this->dueDays();
        if ($this->last_sent_at !== null && $this->last_sent_at->copy()->startOfDay()->gte($now->copy()->subDays($days - 1)->startOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * آخرین N متن ارسال‌شده (قدیمی به جدید).
     */
    public function recentSentTexts(int $limit = 10): array
    {
        $texts = array_values(array_filter(array_map(
            static fn ($t) => is_array($t) ? ($t['text'] ?? null) : (is_string($t) ? $t : null),
            (array) ($this->sent_texts ?? [])
        ), static fn ($t) => is_string($t) && $t !== ''));

        return array_slice($texts, max(0, count($texts) - $limit));
    }

    public function rememberSentText(string $text): void
    {
        $this->sent_texts = array_slice(
            array_merge((array) ($this->sent_texts ?? []), [
                ['text' => $text, 'sent_at' => now()->toIso8601String()],
            ]),
            -self::MAX_SENT_TEXTS
        );
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
