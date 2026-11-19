<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Symbolic (demo-only) discount code issued by the milestone reward engine.
 *
 * No real money is involved — the code represents the 100% "free to the
 * end" grant that is applied directly to the user's subscription.
 */
class LibraryDiscountCode extends Model
{
    protected $fillable = [
        'bot_user_id',
        'bot_id',
        'code',
        'percent',
        'display_amount',
        'source',
        'auto_activated',
        'activated_at',
        'status',
    ];

    protected $casts = [
        'auto_activated' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Generate a unique code like LIB100-4F7A2C (prefix from config).
     */
    public static function generateCode(?string $prefix = null): string
    {
        $prefix = $prefix ?: config('book_library.rewards.discount.code_prefix', 'LIB100');

        do {
            $code = strtoupper($prefix) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
