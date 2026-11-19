<?php

namespace App\Services\Ai;

use App\Models\AiFlag;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R2): override بدون re-deploy برای flagهای سراسری AI.
 *
 * brief §4: «تغییر Feature flag نباید نیاز به re-deploy داشته باشد».
 * ارزش یک flag (مثلاً ai_global_enabled یا ai_ocr_enabled):
 *  1. row جدول ai_flags (override سراسری، از Nova قابل ویرایش)
 *  2. در غیر این صورت config('ai.flags.*') (env)
 *
 * AiFeatureGate در L0.5 و L1 از value() استفاده می‌کند؛ این سرویس هرگز
 * throw نمی‌کند (جای خرابی جدول → null → fallback به config).
 */
class AiFlagService
{
    /** memo: آیا جدول ai_flags در این فرآیند در دسترس است؟ (null = هنوز بررسی نشده) */
    private static ?bool $tableAvailable = null;

    /**
     * override سراسری یک flag بدون re-deploy.
     *
     * @return bool|null true/false اگر override ثبت شده باشد، وگرنه null
     *                   (caller به config('ai.flags.*') فالبک می‌کند)
     */
    public static function value(string $key): ?bool
    {
        try {
            if (! self::tableAvailable()) {
                return null;
            }

            $flag = AiFlag::where('key', $key)->first();

            return $flag?->value;
        } catch (\Throwable $e) {
            // هرگز throw نمی‌کند — flag override باید قابل اتکا باشد
            Log::debug('AiFlagService: خواندن override flag شکست (fallback config)', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * set/clear override سراسری یک flag + audit log (brief §4).
     *
     * @param bool|null $value true/false = set override؛ null = حذف (به env برمی‌گردد)
     */
    public static function set(string $key, ?bool $value, ?string $actor = null, ?string $reason = null): void
    {
        $existing = self::tableAvailable() ? AiFlag::where('key', $key)->first() : null;
        $old = $existing?->value;

        if ($value === null) {
            $existing?->delete();
        } else {
            AiFlag::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        self::audit($key, $old, $value, $actor, $reason);
    }

    /** لیست کل flagهای شناخته‌شده برای Nova/دسترسی راحت */
    public static function knownKeys(): array
    {
        return array_keys((array) config('ai.flags', []));
    }

    private static function tableAvailable(): bool
    {
        if (self::$tableAvailable === null) {
            try {
                self::$tableAvailable = Schema::hasTable('ai_flags');
            } catch (\Throwable $e) {
                self::$tableAvailable = false;
            }
        }

        return self::$tableAvailable;
    }

    private static function audit(string $key, ?bool $old, ?bool $new, ?string $actor, ?string $reason): void
    {
        if ($old === $new) {
            return;
        }

        try {
            \App\Models\AiSettingsAuditLog::create([
                'bot_id'    => null, // تغییر سراسری (نه bot خاص)
                'key'       => "ai_flags.{$key}",
                'old_value' => $old === null ? null : ($old ? 'true' : 'false'),
                'new_value' => $new === null ? null : ($new ? 'true' : 'false'),
                'actor'     => $actor,
                'reason'    => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiFlagService: نوشتن audit log شکست', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
