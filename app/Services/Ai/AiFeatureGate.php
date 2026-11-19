<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use App\Services\AiProviderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * دروازه‌ی مرکزی قابلیت‌های AI (WS-E) — نقطه‌ی واحد تصمیم‌گیری «آیا این قابلیت
 * برای این bot/کاربر الان مجاز است؟».
 *
 * لایه‌ها (به ترتیب؛ اولین لایه‌ی ردکننده برنده است):
 *  1. FLAG GLOBAL   — override بدون re-deploy (ai_flags, R2) ← config('ai.flags.*') از env
 *  2. FLAG BOT      — override در bots.ai_settings (مستقلال‌سازی bot به bot)
 *  2.5. ROLLOUT STAGE — (R1) stage 0/1 fail-closed allowlist؛ 2 فقط Pro؛ 3 public
 *  3. TEST GROUP    — allowlist user/customer ids (اختیاری؛ در stage 0/1 اجباری)
 *  4. TIME WINDOW   — سیاست 09–14 تهران: Pro فوری؛ عادی در پنجره → deferrable
 *  5. PROVIDER      — سلامت provider (lazy + cache؛ safe-by-default)
 *  6. CAPACITY      — سقف jobهای open (QUEUED/PROCESSING/RETRYING) بر حسب bot
 *
 * اصول:
 *  - هرگز throw نمی‌کند (exception → deny + log)؛ خرابی AI نمی‌تواند
 *    functionality عادی bot را بشکند.
 *  - L4 برعکس بقیه رد نمی‌کند؛ result deferrable=true و reason 'deferred'
 *    برمی‌گرداند تا dispatcher job را DEFERRED کند (acceptance scenario 1).
 *  - L5 strict (WS-J): اگر provider فعال در DB نباشد → deny 'provider_missing'
 *    (قابلی تنظیم با config('ai.require_active_provider') — پیش‌فرض true).
 *    exception در health check → باز هم pass ایمن (never-throw).
 *  - L6 برای MVP «safe-by-default» است.
 */
class AiFeatureGate
{
    public const FEATURE_LLM    = 'llm';
    public const FEATURE_OCR    = 'ocr';
    public const FEATURE_ASR    = 'asr';
    public const FEATURE_RAG    = 'rag';
    public const FEATURE_TTS    = 'tts';
    public const FEATURE_GROWTH = 'growth_companion';

    /**
     * AI-PILOT-ROLLOUT-05 (R1): stageهای rollout تدریجی (L2.5).
     * 0 = internal  — فقط حساب‌های داخلی/تستی (fail-closed)
     * 1 = pilot     — فقط allowlist صریح (fail-closed)
     * 2 = all-Pro   — فقط کاربران Pro (یا pro-override ثبت‌شده)
     * 3 = public    — عمومی (سیاست زمان + allowlist اختیاری حفظ می‌شود)
     */
    public const STAGE_INTERNAL = 0;
    public const STAGE_PILOT    = 1;
    public const STAGE_PRO_ONLY = 2;
    public const STAGE_PUBLIC   = 3;

    /** @var string[] */
    public const FEATURES = [
        self::FEATURE_LLM, self::FEATURE_OCR, self::FEATURE_ASR,
        self::FEATURE_RAG, self::FEATURE_TTS, self::FEATURE_GROWTH,
    ];

    /** cache key برای health provider — 30 ثانیه (L5 نباید هر request DB بزند) */
    private const HEALTH_CACHE_KEY = 'ai:gate:provider_healthy';
    private const HEALTH_CACHE_TTL = 30;

    /**
     * ارزیابی دروازه.
     *
     * @param string      $feature     یکی از FEATURES
     * @param int|null    $botId
     * @param int|null    $customerId  برای test group + isolation
     * @param int|null    $userId
     * @param bool        $isPro       Pro 24 ساعته (L4 را می‌پرشاند)
     *
     * @return array{
     *   allowed: bool,      // آیا اصلاً اجازه‌ی ساخت job هست؟
     *   deferrable: bool,   // allowed ولی فقط در پنجره‌ی deferral (L4)
     *   reason: string,     // ok|global_off|bot_disabled|not_in_test_group|
     *                      // deferred|provider_unhealthy|capacity_exceeded|
     *                      // unknown_feature|error
     *   details: ?string,   // توضیح log/monitoring (اختیاری، بدون secret)
     * }
     */
    public function check(
        string $feature,
        ?int $botId,
        ?int $customerId = null,
        ?int $userId = null,
        bool $isPro = false,
    ): array {
        try {
            // L0: sanity
            if (! in_array($feature, self::FEATURES, true)) {
                return $this->result(false, 'unknown_feature', "feature نامعتبر: {$feature}");
            }

            // L0.5: master kill switch — ai_global_enabled
            if (! (bool) $this->globalFlag('ai_global_enabled')) {
                return $this->result(false, 'global_off', 'master switch ai_global_enabled خاموش است');
            }

            // L1: capability flag (کلید config: ai_{feature}_enabled)
            $flagKey = "ai_{$feature}_enabled";
            $globalOn = (bool) $this->globalFlag($flagKey);
            if (! $globalOn) {
                return $this->result(false, 'global_off', "flag capability ai.flags.{$flagKey} خاموش است");
            }

            // L2: bot override (null = بدون override → global؛ bool = جایگزین)
            $botOverride = $this->botFlag($botId, $feature);
            $effective = $botOverride ?? $globalOn;
            if (! $effective) {
                return $this->result(false, 'bot_disabled', "flag bot {$botId} برای {$feature} خاموش است");
            }

            // L2.5: ROLLOUT STAGE (AI-PILOT-ROLLOUT-05) — کنترل دسترسی بر اساس
            // stage rollout: 0/1 = fail-closed (فقط allowlist)؛ 2 = فقط Pro؛
            // 3 = public. بدون تغییر این لایه، لایه‌های بعد (L3+L4) اعمال می‌شوند.
            $stage = $this->rolloutStage($botId);
           $subject = $customerId ?? $userId ?? null;

           if ($stage === self::STAGE_INTERNAL || $stage === self::STAGE_PILOT) {
               $allowlist = $this->botTestGroup($botId);
               if ($allowlist === null
                   || $subject === null
                   || ! in_array((int) $subject, array_map('intval', $allowlist), true)) {
                   return $this->result(
                       false,
                       'not_in_allowlist',
                       "stage {$stage} (fail-closed): user/customer {$subject} در allowlist bot {$botId} نیست"
                           . ' — allowlist خالی/غیرموجود دسترسی نمی‌دهد'
                   );
               }
               // ادامه به L4 (time window) — allowlist فقط مجوز ورود است (§2)
           } elseif ($stage === self::STAGE_PRO_ONLY) {
               $proOverride = $this->botProOverride($botId);
               $isProNow = $isPro
                   || ($subject !== null && in_array((int) $subject, array_map('intval', (array) $proOverride), true));
               if (! $isProNow) {
                   return $this->result(
                       false,
                       'pro_only_stage',
                       "stage 2 (All Pro): user/customer {$subject} Pro نیست (و pro-override هم ندارد)"
                   );
               }
               // ادامه به L4: Pro در deferral window فوری است
           }
           // STAGE_PUBLIC (3): ادامه عادی — L3 اختیاری + L4 time window

            // L3: test group (allowlist) — فقط وقتی set شده
            $testGroup = $this->botTestGroup($botId);
            if ($testGroup !== null) {
                $subject = $customerId ?? $userId ?? null;
                if ($subject === null || ! in_array((int) $subject, array_map('intval', $testGroup), true)) {
                    return $this->result(
                        false,
                        'not_in_test_group',
                        "user/customer {$subject} در allowlist test group bot {$botId} نیست"
                    );
                }
            }

            // L4: time window — Pro فوری؛ عادی 09–14 تهران → deferrable
            if (! $isPro && AiJobDispatcher::isRegularDeferredWindow()) {
                return $this->result(true, 'deferred', 'در پنجره‌ی deferral (09–14 تهران) — job با DEFERRED می‌رود', true);
            }

            // L5: provider health (WS-J: strict — بدون provider فعال → provider_missing)
            if ($this->providerHealthy() === false) {
                $missing = \App\Models\AiProvider::active()->count() === 0;

                return $this->result(
                    false,
                    $missing ? 'provider_missing' : 'provider_unhealthy',
                    $missing
                        ? 'هیچ provider فعال در ai_providers نیست (strict mode)'
                        : 'health check provider ناموفق بود'
                );
            }

            // L6: per-bot capacity
            $capacity = (int) config('ai.queue.max_processing_per_bot', 5);
            if ($botId !== null && $capacity > 0) {
                $open = AiJob::where('bot_id', $botId)
                    ->whereIn('state', [
                        AiJob::STATE_QUEUED,
                        AiJob::STATE_PROCESSING,
                        AiJob::STATE_RETRYING,
                    ])
                    ->count();
                if ($open >= $capacity) {
                    return $this->result(
                        false,
                        'capacity_exceeded',
                        "bot {$botId}: {$open} job در صف/در حال پردازش (سقف {$capacity})"
                    );
                }
            }

            return $this->result(true, 'ok');
        } catch (\Throwable $e) {
            // دروازه هرگز throw نمی‌کند — خطای داخلی → deny + log
            Log::warning('AiFeatureGate: exception در ارزیابی (deny)', [
                'feature' => $feature,
                'bot_id'  => $botId,
                'error'   => AiErrorRedactor::redact($e->getMessage()),
            ]);

            return $this->result(false, 'error', 'exception در ارزیابی دروازه — به‌صورت ایمن رد شد');
        }
    }

    /**
     * شورت‌کات بولینی برای consumerها: $gate->isOpen('ocr', $botId, ...).
     * فقط L1+L2+L3 را می‌سنجد (بدون deferral/health/capacity) — مناسب
     * برای «آیا این قابلیت اصلاً روی این bot فعال است؟».
     */
    public function isOpen(string $feature, ?int $botId, ?int $customerId = null, ?int $userId = null): bool
    {
        $full = $this->check($feature, $botId, $customerId, $userId);
        // 'deferred' یعنی allowed (فقط زمان‌بندی‌شده)
        return $full['allowed'];
    }

    /**
     * آیا user/customer در test group allowlist این bot است؟
     * (null = allowlist تعریف نشده → همه pass)
     */
    public function inTestGroup(?int $botId, ?int $customerId = null, ?int $userId = null): bool
    {
        $group = $this->botTestGroup($botId);
        if ($group === null) {
            return true;
        }
        $subject = $customerId ?? $userId ?? null;

        return $subject !== null && in_array((int) $subject, array_map('intval', $group), true);
    }

    /** حذف cache health (بعد از ai:health-check موفق) */
    public static function clearHealthCache(): void
    {
        Cache::forget(self::HEALTH_CACHE_KEY);
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function result(bool $allowed, string $reason, ?string $details = null, bool $deferrable = false): array
    {
        return [
            'allowed'    => $allowed,
            'deferrable' => $deferrable,
            'reason'     => $reason,
            'details'    => $details,
        ];
    }

    /** L2: override flag این bot (null = بدون override) — دلیگیت به Bot::aiFlag() */
    private function botFlag(?int $botId, string $feature): ?bool
    {
        if ($botId === null) {
            return null;
        }

        $bot = \App\Models\Bot::find($botId);

        return $bot?->aiFlag($feature);
    }

    /** L3: test group allowlist این bot (null = بدون allowlist) — دلیگیت به Bot::aiTestGroup() */
    private function botTestGroup(?int $botId): ?array
    {
        if ($botId === null) {
            return null;
        }

        $bot = \App\Models\Bot::find($botId);

        return $bot?->aiTestGroup();
    }

    /**
     * L0.5/L1: مقدار یک flag سراسری — override بدون re-deploy (R2: ai_flags)
     * در اولویت، سپس config('ai.flags.*') (env). هرگز throw نمی‌کند.
     */
    private function globalFlag(string $key): bool
    {
        try {
           $override = AiFlagService::value($key);
           if (is_bool($override)) {
               return $override;
           }
       } catch (\Throwable $e) {
           Log::warning('AiFeatureGate: override flag exception (safe config fallback)', [
               'key'   => $key,
               'error' => AiErrorRedactor::redact($e->getMessage()),
           ]);
       }

       return (bool) config("ai.flags.{$key}", false);
   }

   /**
    * L2.5: stage rollout — override bot (bots.ai_settings['ai_rollout_stage'])
    * در اولویت، سپس config('ai.rollout.stage') (پیش‌فرض 0 = fail-safe).
    */
    private function rolloutStage(?int $botId): int
    {
        if ($botId !== null) {
           $bot = \App\Models\Bot::find($botId);
           if ($bot?->aiRolloutStage() !== null) {
               return (int) $bot->aiRolloutStage();
           }
       }

       return max(0, min(3, (int) config('ai.rollout.stage', self::STAGE_INTERNAL)));
   }

   /** L2.5: pro-override pilot این bot (null = تعریف نشده) — دلیگیت به Bot::aiProOverride() */
    private function botProOverride(?int $botId): ?array
    {
        if ($botId === null) {
           return null;
        }

        $bot = \App\Models\Bot::find($botId);

        return $bot?->aiProOverride();
    }

    /**
     * L5: آیا provider healthy است؟
     * - strict (ai.require_active_provider، پیش‌فرض true): بدون provider row
     *   فعال → false (deny با 'provider_missing') — WS-J
     * - non-strict: بدون provider row → true (safe-by-default)
     * - testConnection() (GET /v1/models) ناموفق → false (ولی هرگز throw نمی‌کند)
     * - exception در health check → safe-by-default pass + log
     * - نتیجه 30 ثانیه cache می‌شود
     */
    private function providerHealthy(): ?bool
    {
        $cached = Cache::get(self::HEALTH_CACHE_KEY);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $healthy = true;
        try {
            $provider = \App\Models\AiProvider::active()->first();
            if ($provider === null) {
                $healthy = ! (bool) config('ai.require_active_provider', true);
            } else {
                $test = (new AiProviderService($provider))->testConnection();
                $healthy = (bool) ($test['success'] ?? false);
            }
        } catch (\Throwable $e) {
            // خطای health check → safe-by-default (pass) + log
            Log::warning('AiFeatureGate: health check exception (safe-by-default pass)', [
                'error' => AiErrorRedactor::redact($e->getMessage()),
            ]);
            $healthy = true;
        }

        Cache::put(self::HEALTH_CACHE_KEY, $healthy, self::HEALTH_CACHE_TTL);

        return $healthy;
    }
}
