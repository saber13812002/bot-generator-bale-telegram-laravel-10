<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — هشدارهای production (brief §7).
 *
 * آستانه‌ها (config/ai.php → 'alerts'):
 *  - queue_backlog    — بیش از N job در صف (PENDING/QUEUED/RETRYING/DEFERRED)
 *  - dead_letter      — هر error_code با ≥1 FAILED در window
 *  - error_rate       — نسبت FAILED/(COMPLETED+FAILED) در window ≥ threshold (حجم کافی)
 *
 * dedup (brief §7): کلید هر alert در cache با cooldown ثانیه نگه می‌شود؛
 * تا cooldown تمام نشود alert تکرار نمی‌شود. ارسال از مسیر امن موجود
 * (AiProviderService::notifyAllAdmins — mother bot + Quran admin bot).
 *
 * never-throw: هر خطای داخلی فقط log می‌شود (AI failure نباید چیزی را بشکند).
 */
class AiAlertService
{
    /**
     * بررسی آستانه‌ها + dedup + ارسال.
     *
     * @param bool $dryRun true = فقط detect (ارسال/notify نمی‌شود)
     * @return array list of ['key' => string, 'message' => string] (فقط alertهای فعالِ جدید)
     */
    public static function runWithDedup(bool $dryRun = false): array
    {
        try {
            if (! config('ai.alerts.enabled', true)) {
                return [];
            }
            if (! Schema::hasTable('ai_jobs')) {
                return [];
            }

            $alerts  = self::detect();
            $sent    = [];
            $cooldown = (int) config('ai.alerts.cooldown_seconds', 3600);

            foreach ($alerts as $alert) {
                $key = 'ai_alert:' . $alert['key'];

                if (Cache::has($key)) {
                    continue; // dedup: در cooldown است
                }

                if (! $dryRun) {
                    Cache::put($key, 1, $cooldown);
                    self::dispatch($alert['message']);
                }

                $sent[] = $alert;
            }

            return $sent;
        } catch (Throwable $e) {
            Log::warning('[AiAlerts] runWithDedup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * آستانه‌ها → list of ['key' => string, 'message' => string].
     * بدون dedup (dedup در runWithDedup).
     *
     * @return array
     */
    public static function detect(): array
    {
        if (! Schema::hasTable('ai_jobs')) {
            return [];
        }

        $alerts = [];
        $now    = Carbon::now();

        // ── 1) صف انباشته (brief §6: queue length) ──────────────────────
        $backlog = AiJob::whereIn('state', [
            AiJob::STATE_PENDING, AiJob::STATE_QUEUED,
            AiJob::STATE_RETRYING, AiJob::STATE_DEFERRED,
        ])->count();
        $backlogThreshold = (int) config('ai.alerts.queue_backlog_threshold', 100);

        if ($backlog > $backlogThreshold) {
            $alerts[] = [
                'key'     => 'queue_backlog',
                'message' => "🟠 AI Queue Backlog: {$backlog} job در صف (سقف: {$backlogThreshold}). cron queue:work فعال است؟",
            ];
        }

        // ── 2) Dead letter (brief §6/§9: dead letters) ──────────────────
        $dlWindow = (int) config('ai.alerts.dead_letter_window_hours', 24);
        $byError  = AiJob::byState(AiJob::STATE_FAILED)
            ->where('completed_at', '>=', $now->copy()->subHours($dlWindow))
            ->whereNotNull('error_code')
            ->selectRaw('error_code, COUNT(*) as c')
            ->groupBy('error_code')
            ->pluck('c', 'error_code');

        foreach ($byError as $errorCode => $count) {
            $alerts[] = [
                'key'     => "dead_letter:{$errorCode}",
                'message' => "🟠 AI DEAD LETTER: {$count} job با خطای «{$errorCode}» در {$dlWindow}h اخیر. (ai:queue-status)",
            ];
        }

        // ── 3) Error rate (brief §6: error rate) ────────────────────────
        $erWindow   = (int) config('ai.alerts.error_rate_window_hours', 24);
        $minVolume  = (int) config('ai.alerts.error_rate_min_volume', 20);
        $threshold  = (float) config('ai.alerts.error_rate_threshold_percent', 25);
        $since      = $now->copy()->subHours($erWindow);

        $failed   = AiJob::byState(AiJob::STATE_FAILED)->where('completed_at', '>=', $since)->count();
        $completed = AiJob::byState(AiJob::STATE_COMPLETED)->where('completed_at', '>=', $since)->count();
        $volume   = $failed + $completed;

        if ($volume >= $minVolume) {
            $rate = round($failed * 100 / $volume, 1);
            if ($rate >= $threshold) {
                $alerts[] = [
                    'key'     => 'error_rate',
                    'message' => "🟠 AI Error Rate: {$rate}٪ از {$volume} job در {$erWindow}h اخیر ناموفق (سقف: {$threshold}٪).",
                ];
            }
        }

        return $alerts;
    }

    /**
     * ارسال از مسیر امن admin channel (brief §7). never-throw.
     */
    private static function dispatch(string $message): void
    {
        try {
            \App\Services\AiProviderService::notifyAllAdmins($message);
        } catch (Throwable $e) {
            Log::warning('[AiAlerts] dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
