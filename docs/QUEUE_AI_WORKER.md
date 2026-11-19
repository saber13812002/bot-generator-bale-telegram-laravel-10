# صف AI — Worker و مدیریت عملیاتی (WS-D)

> این مستند، راهنمای عملیاتی صف مستقل AI است. تمام اجزای WS-D (جدول‌های `jobs`/`ai_jobs`،
> `ProcessAiJob`، `AiJobDispatcher`، دستورات `ai:queue-*` و entries اسکدولر) به این فایل ارجاع دارند.

## ۱. معماری

```
Request (controller/job)
   │  AiJobDispatcher::dispatch('llm_chat', $payload, $options)
   ▼
ai_jobs  (business source-of-truth: state, priority, due_at, expires_at,
          attempt_number, error_code, result_reference, idempotency_key)
   │  ProcessAiJob::dispatch($job_id)
   │     ->onConnection('database')->onQueue('ai')
   ▼
jobs     (Laravel transport — technical row)
   │  worker: php artisan queue:work --queue=ai --connection=database
   ▼
ProcessAiJob::handle() → AiProviderService / adapters → state update
```

**قید مستقل بودن:** jobهای AI همیشه صریحاً روی connection `database` و queue `ai`
رسیدگی می‌شوند، فارغ از `QUEUE_CONNECTION=sync` سراسری. بنابراین bot functionality
عادی (که روی sync اجرا می‌شود) هرگز پشت صف AI نمی‌ماند و خرابی provider فقط state
job را تغییر می‌دهد — نه پاسخ‌های عادی بات را.

## ۲. چرخه‌ی وضعیت (State Machine)

```
PENDING ──> QUEUED ──> PROCESSING ──> COMPLETED
   │           │            │
   │           │            └──> RETRYING ──(due_at)──> PROCESSING ...
   │           │
   │           └──(09:00–14:00 Tehran, regular)──> DEFERRED ──(14:00 flush)──> QUEUED
   │
   └──> FAILED (dead letter بعد از max_attempts=5)
       CANCELLED / EXPIRED (expires_at، پیش‌فرض ۲۴ ساعت)
```

- **Pro** هم‌یشه فوری (priority `pro`، rank 0)
- **Regular 00–09 و 14–24 Tehran**: فوری
- **Regular 09–14 Tehran**: `DEFERRED` تا فلش ۱۴:۰۰ (job در‌حالت-execution قطع نمی‌شود)
- **Flus 14:00 Tehran (= `dailyAt('10:30')` UTC):** deferredهای سررسید شده با اولویت
  (pro > regular > deferred > low) به QUEUED برمی‌گردند؛ هر job فقط یک‌بار (lock +
  re-check state داخل transaction).

## ۳. cPanel — cron لازم (بدون Supervisor)

سرور shared cPanel فقط cron دارد. **سطح ۱ (حیاتی)** همان cron فعلی است:

```
* * * * * php /home/pardisa2/bots/artisan schedule:run
```

entries جدید اسکدولر (داخل `app/Console/Kernel.php`) را همین cron پوشش می‌دهد:

| Command | زمان‌بندی (UTC سرور) | نقش |
|---|---|---|
| `ai:queue-work` | هر دقیقه، `withoutOverlapping(5)` | worker واقعی — `queue:work --queue=ai --stop-when-empty` با `--max-time ≤ 55s` |
| `ai:queue-flush-deferred` | `10:30` روزانه + هر ۱۰ دقیقه | آزادسازی DEFERREDهای سررسید (۱۴:۰۰ تهران = ۱۰:۳۰ UTC) |
| `ai:queue-recover-stale` | هر ۱۰ دقیقه | بازیابی restart/restart recovery |

> `withoutOverlapping` + `max-time 50 < 60s` تضمین می‌کند دو worker هم‌زمان `queue:work`
> نباشند و هر اجرای cron قبل از دقیقه بعدی تمام شود.

**سطح ۲ (اختیاری، فقط اگر عمق صف زیاد شود):** cron اختصاصی worker با max-jobs محدود
برای پردازش سریع‌تر — در حالت عادی لازم نیست چون هر دقیقه یک round کامل گرفته می‌شود.

## ۴. Monitring — دستورات

```bash
# وضعیت کلی صف (شمارش stateها، قدیمی‌ترین pending، stale، آخرین dead letterها)
php artisan ai:queue-status

# خروجی JSON برای اسکریپت/مونیتورینگ
php artisan ai:queue-status --json

# بررسی سلامت providerها
php artisan ai:health-check
```

هشدارهای داخلی `ai:queue-status`:
- `QUEUED > 100` → worker فعال نیست؟ cron چک شود.
- `stale PROCESSING > 0` → `ai:queue-recover-stale` اجرا شود.

## ۵. Recovery semantics (restart / crash)

`ai:queue-recover-stale` (هر ۱۰ دقیقه) سه بخش دارد:

1. **PROCESSING کهنه** (heartbeat/started بیشتر از `stale_after_minutes`=20 دقیقه):
   - اگر `attempt_number >= max_attempts` → `FAILED` با `stale_exhausted`
   - وگرنه: purge سطر فنی `jobs` (برای آزاد شدن unique lock `ShouldBeUnique`) →
     `RETRYING` با `stale_worker_lost`، due_at = +30s → push دوباره
2. **jobهای سررسیدشده** (expires_at گذشته و غیرترمینال) → `EXPIRED`
3. **QUEUED که سطر فنی‌شان از بین رفته** (بدون row در `jobs`) → push دوباره
   (تشخیص با UUID در payload؛ UUID یکتا است → بدون false positive)

ناتجه‌ی عملیاتی: **بعد از هر deploy/restart، jobی گم نمی‌شود و دو‌بار اجرا نمی‌شود.**

## ۶. Rate limit و Concurrency

| مکانیزم | مقدار (config `ai.queue`) | توضیح |
|---|---|---|
| timeout هر job | `timeout` = 180s | `ProcessAiJob::$timeout` |
| تلاش‌های فنی | `tries` = 3 | backoff فنی `[30, 120, 300]` |
| سقف کلی attempts | `max_attempts` = 5 | بعد از آن → FAILED (dead letter) + اعلان admin |
| worker round | `max_time` = 50s / `max_jobs` = 200 | هر cron round |
| concurrent processing | 1 worker (cron everyMinute) | `withoutOverlapping` |
| per-bot cap | `max_processing_per_bot` = 5 | `AiJob::nextRunnable()` — botی که ۵ job دارد، job بعدی‌اش نوبت می‌گیرد |
| عمر کلی job | `expires_after_hours` = 24 | `expires_at` روی job |

همه‌ی مقادیر در بخش `queue` فایل [`config/ai.php`](../config/ai.php) قابل تغییر است
(در حال حاضر hardcoded default؛ اگر نیاز به env-based باشد، همان pattern
`env('AI_QUEUE_TRIES', 3)` اضافه می‌شود).

## ۷. Dead letter

وقتی `attempt_number` به `max_attempts` برسد (یا job با `expires_at` بگذرد):

- state → `FAILED`، `error_code` تنظیم، `completed_at` stamp
- اعلان `AiProviderService::notifyAdmin(...)` با پیام redacted (بدون token/secret)
- سطر فنی شکست‌خورده (اگر باشد) در `failed_jobs` هم می‌ماند (backstop فریمورک)

**خطاها دسته‌بندی‌شده‌اند:** `auth_failed` / `model_not_found` / `rate_limited` /
`provider_unavailable` / `provider_error` / `timeout` / `tls_error` /
`connection_error` / `exception` / `stale_worker_lost` / `stale_exhausted` /
`feature_not_supported`

## ۸. Idempotency و Cancellation

- `idempotency_key` UNIQUE: `feature:bot_id:entity:sha1(payload)[:24]`
  (entity = customer_id یا user_id). dispatch تکراری → `created=false` + همان job.
- `ShouldBeUnique` روی UUID `job_id` → حتی push هم‌زمان job فنی را یک‌بار نگه می‌دارد.
- `AiJobDispatcher::cancel($jobId)` → `CANCELLED`؛ `ProcessAiJob` job ترمینال را
  skip می‌کند (بدون call به provider).

## ۹. Deploy / Rollback

**Deploy (cPanel):**
1. Commit/tag بزنید، فایل‌ها را deploy کنید.
2. `php artisan migrate --force` (جدول‌های `jobs` و `ai_jobs` — هر دو idempotent).
3. Cron موجود `schedule:run` entries جدید را خودکار فعال می‌کند (کد جدید لازم نیست).
4. چک: `php artisan ai:queue-status` → همه صفر، بدون جدول‌غیرموجود (exit 0).

**Rollback:**
- کد: revert commit — worker/entries ناپدید می‌شوند؛ jobهای در صف فنی تا مصرف شدن
  پردازش می‌شوند (harmless).
- DB: جدول‌ها additive-only هستند؛ rollback نیاز به drop ندارد.
  (در صورت نیاز: `php artisan migrate:rollback --step=2` فقط در همین batch.)
- flag سطح بالاست: تا `AiFeatureGate` (WS-E) flag را on نکند، هیچ consumerی job
  نمی‌سازد — یعنی حتی بدون rollback، صف AI خالی می‌ماند.

## ۱۰. Checklist cPanel (copy-paste)

```
[ ] cron موجود:  * * * * * php /home/pardisa2/bots/artisan schedule:run
[ ] بعد از deploy:  php /home/pardisa2/bots/artisan migrate --force
[ ] چک دستی:       php /home/pardisa2/bots/artisan ai:queue-status
[ ] چک صبح/عصر:    خروجی --json را log بگیرید (اختیاری)
[ ] در صورت هشدار stale:  php /home/pardisa2/bots/artisan ai:queue-recover-stale
```
