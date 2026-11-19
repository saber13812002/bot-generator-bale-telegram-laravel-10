# AI-07 — AI Queue, Isolation & Resilience

**وضعیت: NEEDS-DESIGN** (شکاف‌های ساختاری جدی؛ پیش‌نیاز همهٔ مأموریت‌های AI)

## 1) خلاصهٔ مدیریتی

زیرساخت فعلی برای AI **ناکافی و پرخطر** است. کل پروژه روی `QUEUE_CONNECTION=sync` اجرا می‌شود (بدون Worker، بدون جدول `jobs`)، همهٔ ۱۲ Job بدون `$tries`/`$timeout`/`$backoff` تعریف شده‌اند، و فراخوانی‌های AI **به‌صورت HTTP همگام داخل وب‌هوک و داخل Scheduler** انجام می‌شوند. نتیجه: هر کندی/قطعی LiteLLM مستقیماً روی پاسخ ربات اثر می‌گذارد؛ و چون Idempotency و Rate-limit وجود ندارد، Retry پلتفرم می‌تواند منجر به پردازش و هزینهٔ تکراری شود. این مأموریت **پیش‌نیاز** OCR/ASR/Companion است.

## 2) وضعیت فعلی

| مورد | وضعیت واقعی |
|---|---|
| Queue driver | `sync` ([`.env`](../../.env) / [`.env.example`](../../.env.example)) |
| Worker | ❌ هیچ (`queue:work` در deploy/cron نیست) |
| جدول `jobs` | ❌ **ناموجود** (DB محلی) |
| `failed_jobs` | ✅ موجود |
| Jobها | ۱۲ — همه `ShouldQueue`، **هیچ‌کدام `$tries`/`$timeout`/`$backoff` ندارند** |
| Scheduler | ۳۶ entry در [`Kernel.php`](../../app/Console/Kernel.php:26)، اجرا با `schedule:run` هر دقیقه |
| فراخوانی AI | همگام داخل درخواست/کامند (بدون صف) |
| Rate limit برای AI | ❌ |
| Idempotency برای AI | ❌ |
| جداسازی ربات‌ها | فقط در سطح داده؛ در سطح اجرا هیچ (همه inline) |
| ذخیرهٔ نتیجهٔ AI | فقط در چند جدول خاص؛ جدول عمومی نتیجه/خطا برای AI وجود ندارد |

## 3) شواهد کد و دیتابیس

- Jobها: [`app/Jobs`](../../app/Jobs) — نمونه: [`ProcessChannelPosterQueueJob.php:14`](../../app/Jobs/ProcessChannelPosterQueueJob.php:14)، [`RssPostItemTranslationJob.php:15`](../../app/Jobs/RssPostItemTranslationJob.php:15)، [`SendMissionMediaJob.php:20`](../../app/Jobs/SendMissionMediaJob.php:20).
- AI همگام: [`AiProviderService::chat()`](../../app/Services/AiProviderService.php:101) با `timeout` پیش‌فرض ۳۰s؛ ترجمه `20s` ([`LlmTranslationService.php:26`](../../app/Services/LlmTranslationService.php:26)).
- AI در Scheduler: `channel-motivation:send` ۱۱:۰۰ ([`Kernel.php:195`](../../app/Console/Kernel.php:195))، `ai:health-check` ساعتی ([`Kernel.php:132`](../../app/Console/Kernel.php:132))، `ai:daily-health-report` ۰۹:۰۰ ([`Kernel.php:138`](../../app/Console/Kernel.php:138)).
- قفل هم‌زمانی: فقط `withoutOverlapping()`/`onOneServer()` در Scheduler — هیچ قفل per-user یا per-bot در سطح AI.
- Migration `jobs`: در `database/migrations` وجود ندارد (بررسی با `Schema::hasTable('jobs')` = NO).

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | `sync`، `jobs` ناموجود، `failed_jobs` موجود |
| GitHub | `7702b68f` |
| Production | طبق [`PROJECT_MASTER_MAP.md:122`](../PROJECT_MASTER_MAP.md:122): `sync` بدون worker — تأییدنشده با مشاهدهٔ مستقیم |

## 5) تست‌های انجام‌شده و نتایج واقعی

- تست‌های موجود همگی رفتار را با `Http::fake` و SQLite در حافظه می‌سنجند (نمونه: [`ChannelMotivationSendTest`](../../tests/Feature/ChannelMotivationSendTest.php:103)، [`PostOverdueWatchdogTest`](../../tests/Feature/PostOverdueWatchdogTest.php)).
- **هیچ تستی برای: صف واقعی، Retry، Idempotency، Rate-limit یا رفتار در قطعی AI وجود ندارد.**
- تست بار/هم‌زمانی: انجام نشده.

## 6) موارد ناشناخته و محدودیت دسترسی

- نرخ واقعی پیام/دقیقه هر ربات در Production.
- محدودیت‌های hosting برای `queue:work` (cPanel shared hosting معمولاً daemon ندارد ⇒ نیاز به cron-based `queue:work --stop-when-empty`).
- سهمیه/نرخ LiteLLM (RPM/TPM) ⇒ بدون آن، طراحی Rate-limit حدس است.

## 7) مشکلات و ریسک‌ها

1. **Block شدن مسیر عادی ربات:** با `sync`، AI داخل وب‌هوک ⇒ timeout پلتفرم و پیام «ربات جواب نمی‌دهد».
2. **از دست رفتن پیام:** Retry پلتفرم به دلیل کندی؛ و بدون صف، اگر پاسخ نیمه‌کاره بماند کاربر بازخورد نمی‌گیرد.
3. **پردازش تکراری:** نبود Idempotency برای AI (OCR/STT/LLM) ⇒ هزینهٔ مضاعف روی Retry یا ارسال دوبارهٔ کاربر.
4. **عدم جداسازی:** یک ربات پرترافیک می‌تواند منابع/سهمیه را از بقیه بگیرد (بدون صف per-bot و بدون سهمیه).
5. **نبود ردیابی:** هر Job باید شناسه/وضعیت/زمان/خطا/نتیجه داشته باشد؛ امروز فقط چند جدول موردی این را دارند.
6. **Health-check گمراه‌کننده** (نگاه کنید به AI-01 §7).

## 8) قابلیت‌های قابل استفاده مجدد

- `failed_jobs` موجود (پایهٔ Dead-letter).
- الگوی `withoutOverlapping + onOneServer` در Scheduler (پایهٔ کنترل هم‌زمانی).
- `BotLog` / `ai_provider_logs` به‌عنوان پیش‌نمونهٔ ثبت رخداد.
- `pipeline:status` / `pipeline:watch` ([`Kernel.php:183`](../../app/Console/Kernel.php:183)) به‌عنوان الگوی Observability.

## 9) پیشنهاد MVP — معماری

```
Webhook (سریع، ≤1s) ──► AIRequestGate (feature-flag + window + quota) ──► AIJob (queued)
                                                                          │
                                     ┌────────────────────────────────────┴──────────┐
                                     │ ai_jobs: id(UUID), bot_id, chat_id,           │
                                     │ capability(ocr|asr|llm|rag|tts), model,       │
                                     │ status(queued|running|done|failed|skipped),   │
                                     │ attempts, error_code, latency_ms, tokens,     │
                                     │ cost, idempotency_key UNIQUE, payload_path    │
                                     └───────────────────────────────────────────────┘
Worker (cron: queue:work --queue=ai --stop-when-empty --tries=3 --timeout=120)
Fallback: پس از ۳ تلاش → status=failed + پیام ثابت به کاربر (ثبت‌شده، قابل پیگیری)
```

- **صف جدا:** `--queue=ai` تا AI هیچ‌وقت پشت/جلو صف انتشار کانال نایستد.
- **Idempotency:** `sha1(bot_id|chat_id|capability|file_unique_id|message_id)` با UNIQUE.
- **Timeout سلسله‌مراتبی:** وب‌هوک ۱s · Job OCR ۱۲۰s · Job ASR ۱۸۰s · LLM ۴۵s.
- **Rate-limit:** توکن‌باکت در Cache با کلید `ai:rl:{bot_id}`؛ سهمیهٔ سراسری `ai:rl:global`.
- **جداسازی:** هر Job `bot_id` دارد؛ سهمیه per-bot تا ربات پرترافیک بقیه را گرسنه نکند.
- **TTS/RAG:** همان قرارداد، `capability` متفاوت.

### سیاست «غیرفعال بودن AI بین ۹ تا ۱۴»

**امروز چنین تنظیمی در کد وجود ندارد** (بررسی `config/*` برای `quiet|ai_window|rate_limit|ai_enabled` نتیجه‌ای جز `bale-otp` نداد). اگر اعمال شود:

| موضوع | اثر / ابهام |
|---|---|
| Jobs در صف | باید `status=deferred` با `run_after` بگیرند، نه `failed` |
| کاربران | پیام «این قابلیت ساعت ۹ تا ۱۴ غیرفعال است؛ نتیجه بعداً ارسال می‌شود» |
| Scheduler | `channel-motivation:send` ساعت ۱۱ داخل پنجره است ⇒ باید skip یا defer شود |
| Health-check | آیا `ai:health-check` هم باید در پنجره خاموش باشد؟ (پیشنهاد: خیر، فقط مصرف کاربر) |
| منطقهٔ زمانی | ۹–۱۴ به وقت **تهران** یا **UTC**؟ (سرور UTC است) |
| مرزها | ۹:۰۰ شامل، ۱۴:۰۰ شامل؟ جمعه/تعطیلات؟ |
| Pro | آیا مشتری Pro مستثنی است؟ |
| تأخیر انباشت | در ساعت ۱۴ جهش ناگهانی صف ⇒ نیاز به نرخ تخلیه |

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| تصمیم دربارهٔ `database` Queue + worker cron | مالک / hosting |
| جدول `ai_jobs` + قرارداد Capability | تأیید معماری |
| تعریف رسمی پنجرهٔ ۹–۱۴ (TZ، مرز، استثناها) | مالک |
| سهمیه/نرخ LiteLLM | AI-01 |

**Status: NEEDS-DESIGN** — و **پیش‌نیاز** AI-03/04/05.
