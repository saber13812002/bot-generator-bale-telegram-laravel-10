# دروازه‌ی مرکزی قابلیت‌های AI — AiFeatureGate (WS-E)

> نقطه‌ی واحد تصمیم‌گیری: «آیا این قابلیت AI برای این bot / این کاربر، **الان**، مجاز است؟»
>
> اصل بنیادی: **خرابی یا عدم‌فعال بودن AI نباید هیچ functionality عادی bot را بشکند.**
> دروازه هرگز `throw` نمی‌کند؛ هر exception داخلی → `deny` + `Log::warning` (redacted).

## ۱. لایه‌های تصمیم‌گیری (به ترتیب؛ اولین ردکننده برنده است)

| لایه | نام | منبع | نتیجه |
|---|---|---|---|
| L0 | sanity | `AiFeatureGate::FEATURES` | `unknown_feature` → deny |
| L0.5 | **master kill switch** | env `AI_FLAG_GLOBAL` | `global_off` → deny |
| L1 | **flag سراسری capability** | env `AI_FLAG_{LLM,OCR,ASR,RAG,TTS,GROWTH}` | `global_off` → deny |
| L2 | override bot | `bots.ai_settings.ai_feature_flags.{feature}` (Nova) | `bot_disabled` → deny |
| L3 | **test group allowlist** | `bots.ai_settings.ai_test_group` (Nova) | `not_in_test_group` → deny |
| L4 | **time window** (سیاست 09–14 تهران) | `AiJobDispatcher::isRegularDeferredWindow()` | `deferred` → **allowed + deferrable=true** (رد نمی‌کند!) |
| L5 | سلامت provider | `AiProvider::active()` + `AiProviderService::testConnection()` | `provider_unhealthy` → deny (30 ثانیه cache؛ safe-by-default) |
| L6 | ظرفیت per-bot | شمارش `ai_jobs` در حالت‌های open + `ai.queue.max_processing_per_bot` | `capacity_exceeded` → deny |

### معنای kill switch (مهم)
- flag‌های سراسری (L0.5 و L1) **kill switch** هستند: اگر خاموش باشند، **هیچ** override bot نمی‌تواند آن قابلیت را روشن کند (L1 قبل از L2 بررسی می‌شود).
- override bot در لایه‌ی MVP عملاً فقط **opt-out** معنادار دارد (`off` روی bot را می‌بندد)؛ `on` در حالتی که flag سراسری خاموش است، بی‌اثر است.
- این رفتار آگاهانه است و با تست `test_kill_switch_wins_over_bot_override` قفل شده است.

### L4 — time window (به‌جای رد، «deferred» برمی‌گرداند)
- Pro: همیشه فوری (`isPro: true` → L4 را می‌پرشاند).
- کاربر عادی: 09:00–14:00 **Tehran** → `allowed=true, deferrable=true, reason='deferred'`.
  consumer (dispatcher) در این حالت job را با state `DEFERRED` و `due_at = 14:00 تهران` می‌سازد؛ در 14:00 command `ai:queue-flush-deferred` آن‌ها را با priority آزاد می‌کند.
- خارج از پنجره: `allowed=true, reason='ok'` (فوری).

## ۲. env vars (`.env` / `.env.example`)

```env
# همه پیش‌فرض خاموش — rollout تدریجی طبق release gate
AI_FLAG_GLOBAL=false
AI_FLAG_LLM=false
AI_FLAG_OCR=false
AI_FLAG_ASR=false
AI_FLAG_RAG=false
AI_FLAG_TTS=false
AI_FLAG_GROWTH=false
```

نقطه‌ی خواندن: [`config/ai.php`](../config/ai.php) → بخش `flags`. تغییر env در cPanel نیاز به `php artisan config:clear` (یا restart) دارد؛ flag‌ها در هر request از config خوانده می‌شوند.

## ۳. override per-bot (Nova)

ستون `bots.ai_settings` (JSON، nullable) — ساختار:

```json
{
  "ai_feature_flags": { "ocr": true, "llm": false },
  "ai_test_group": [123, 456]
}
```

در Nova (`app/Nova/Bot.php`) → بخش **«AI — قابلیت‌ها (WS-E)»**:
- ۶ Select تری‌стейت: `ai_llm` / `ai_ocr` / `ai_asr` / `ai_rag` / `ai_tts` / `ai_growth_companion` با گزینه‌های:
  - **پیروی از سراسری** (`follow` → بدون کلید در JSON؛ flag env حاکم است)
  - **روشن** (`on`) / **خاموش** (`off`)
- Text `ai_test_group`: comma-separated ids (customer/user). **خالی = همه مجاز** (allowlist غیرفعال).

مکانیسم: `Bot::setAttribute/getAttribute` این attribute‌های مجازی را داخل `ai_settings` در حافظه نگه می‌دارد تا save یکه‌ی Nova (از جمله هنگام create) کار کند.

### API مستقیم (بدون Nova)
```php
$bot->setAiFlag('ocr', true);        // روشن
$bot->setAiFlag('llm', null);        // حذف override → پیروی از سراسری
$bot->setAiTestGroup([123, 456]);    // allowlist
$bot->setAiTestGroup(null);          // allowlist غیرفعال
```

## ۴. استفاده در consumerها

```php
use App\Services\Ai\AiFeatureGate;

$gate = app(AiFeatureGate::class);

// قبل از ساخت job / پاسخ به کاربر:
$r = $gate->check(AiFeatureGate::FEATURE_OCR, $botId, customerId: $customerId, isPro: $botUser->isPro($botId));

match (true) {
    $r['allowed'] && $r['deferrable'] => /* AiJobDispatcher::dispatch() — خودش DEFERRED می‌سازد */,
    $r['allowed']                     => /* dispatch فوری */,
    $r['reason'] === 'not_in_test_group' => /* پیام «در دسترس نیست» (بدون ذکر دلیل فنی) */,
    default                           => /* fallback عادی bot — AI خاموش، flow اصلی خراب نشد */,
};

// شورت‌کات «آیا این capability روی این bot اصلاً فعال است؟»:
$gate->isOpen('ocr', $botId);
```

## ۵. L5 — سلامت provider (WS-J: strict)

- provider فعال در DB (`ai_providers.is_active=1`) پیدا شد → `AiProviderService::testConnection()` (GET `/v1/models`):
  `success=false` → `provider_unhealthy` (deny).
- **بدون provider row فعال در DB:**
  - strict mode (`config('ai.require_active_provider')` — پیش‌فرض **true**، env `AI_REQUIRE_ACTIVE_PROVIDER`) → **deny `provider_missing`** (WS-J: سخت‌گیرانه).
  - non-strict (مثلاً در phpunit.xml `false`) → pass (safe-by-default).
- exception در health check → pass + log (never-throw) — مثلاً نبود جدول `ai_providers` در تست‌های sqlite مختص‌به‌خود.
- نتیجه **30 ثانیه** cache می‌شود (`ai:gate:provider_healthy`)؛ `AiFeatureGate::clearHealthCache()` برای invalidation دستی (مثلاً بعد از `ai:health-check` موفق).
- پوشش: `tests/Feature/AiConsumerHardeningTest.php` (J1).

## ۶. L6 — ظرفیت per-bot

- شمارش `ai_jobs` در حالت‌های `QUEUED/PROCESSING/RETRYING` برای همان bot.
- سقف: `config('ai.queue.max_processing_per_bot')` (پیش‌فرض ۵؛ در `.env` با `AI_QUEUE_MAX_PROCESSING_PER_BOT`).
- `open >= سقف` → `capacity_exceeded`. (بوت‌های شلوغ، صف AI را نمی‌بندند — flow عادی ادامه دارد.)

## ۷. Runbook — rollout تدریجی (4 مرحله‌ی binding owner decision)

| مرحله | اقدام env | اقدام Nova | وضعیت |
|---|---|---|---|
| 0. خاموش | همه flag‌ها `false` | — | همه‌چیز fallback عادی |
| 1. تست (test group) | `AI_FLAG_GLOBAL=true` + flag capability موردنظر `true` | `ai_test_group` = لیست کاربران تست | فقط allowlist |
| 2. بوت‌های انتخابی | همان | روی بوت‌های موردنظر override `on`، بقیه `follow`/`off` | بوت‌به‌بوت |
| 3. عمومی | همان | overrideها حذف (`follow`) + `ai_test_group` خالی | همه |

**خاموش‌سازی فوری (rollback):** `AI_FLAG_GLOBAL=false` + `config:clear` → همه‌چیز در همان لحظه به fallback عادی برمی‌گردد؛ بدون تغییر schema/داده.

### چک‌لیست هر مرحله
1. `php artisan config:clear` بعد از تغییر env
2. `php artisan ai:queue-status` → صف پر نمی‌شود
3. تست دستی capability در یک بوت از allowlist
4. مشاهده‌ی log‌های `AiFeatureGate` (warning فقط در exception/health-fail)

## ۸. تست‌ها

- [`tests/Feature/AiFeatureGateTest.php`](../tests/Feature/AiFeatureGateTest.php) — 18 تست: هر لایه به‌طور جدا، kill-switch-wins، deferral window (Carbon::setTestNow)، Pro bypass، provider unhealthy، safe-by-default، capacity، helperهای model، setter سبک Nova، never-throw.
- اجرای سریع: `vendor\bin\phpunit --filter AiFeatureGateTest`

## ۹. تغییرات WS-E (چک‌لیست merge)

- `app/Services/Ai/AiFeatureGate.php` (جدید)
- `config/ai.php` — بخش `flags`
- `database/migrations/2026_11_14_130000_add_ai_settings_to_bots_table.php` (جدید؛ nullable → additive)
- `app/Models/Bot.php` — `ai_settings` cast + helperها + attribute hooks
- `app/Nova/Bot.php` — Section AI (۶ Select + Text test group)
- `.env.example` — `AI_FLAG_*`
- `docs/AI_FEATURE_FLAGS.md` (این فایل)
