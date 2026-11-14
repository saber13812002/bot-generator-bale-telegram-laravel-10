# AI-01 — OCR / LiteLLM Production Verification

**وضعیت: BLOCKED** (اعتبارنامه LiteLLM نامعتبر + عدم دسترسی به Production + TLS تأییدنشده)

> هیچ کد، Migration، تنظیمات یا دیتابیس تغییر نکرد. هیچ توکنی چاپ نشد. هیچ پیام واقعی برای مشتری ارسال نشد.

## 1) خلاصهٔ مدیریتی

زیرساخت AI پروژه از نظر معماری کامل و آماده است: یک کلاینت OpenAI-compatible ([`AiProviderService`](../../app/Services/AiProviderService.php:14)) با دو Endpoint استاندارد (`/v1/models` و `/v1/chat/completions`)، جدول تنظیمات `ai_providers`، Health-check ساعتی، و سه مصرف‌کنندهٔ فعال. Endpoint واقعی `https://ai.ismc.ir/api` یک **LiteLLM Proxy** است (از متن خطای اختصاصی آن تأیید می‌شود).

مانع اصلی، **اعتبارنامه** است: توکن موجود در محیط محلی با پاسخ `401 token_not_found_in_db` رد می‌شود. بنابراین فهرست مدل‌ها، تست «سلام»، شناسایی مدل Vision و تست OCR همه مسدودند. علاوه بر این، طبق قرارداد همین مأموریت، اجرای تست تصویر با `verify=false` (گواهی TLS تأییدنشده) مجاز نیست.

## 2) وضعیت فعلی

| مورد | وضعیت واقعی |
|---|---|
| اتصال شبکه به `ai.ismc.ir` | سالم — DNS `16ms`، TLS `41ms`، total `66ms` |
| پاسخ `GET /api/v1/models` | **HTTP 401** — `token_not_found_in_db` |
| پاسخ `POST /api/v1/chat/completions` با «سلام» | **HTTP 401** در `40ms` — `content` و `usage` خالی |
| آخرین تست ثبت‌شده در DB محلی | `2026-11-05 16:17:38` — `failed` — `ping=107ms` |
| `available_models` | `NULL` (هیچ‌گاه لیست مدل‌ها دریافت نشد) |
| مدل پیش‌فرض | `qwen38` (alias مبهم؛ Vision بودنش اثبات‌نشده) |
| گواهی TLS | در تنظیمات `verify=false` ⇒ **تأییدنشده** |

## 3) شواهد کد و دیتابیس

- Transport: [`testConnection()`](../../app/Services/AiProviderService.php:35) · [`chat()`](../../app/Services/AiProviderService.php:101) · [`ping()`](../../app/Services/AiProviderService.php:152)
- `chat()` فقط `content` رشته‌ای می‌سازد ([`AiProviderService.php:114`](../../app/Services/AiProviderService.php:114)) ⇒ **هیچ پشتیبانی تصویر در کلاینت وجود ندارد**.
- تنظیمات: [`config/ai.php:27`](../../config/ai.php:27) → `verify=false`، `timeout=60`، `connect_timeout=30`، `proxy.no=['*']`.
- DB محلی (read-only): یک رکورد `ai_providers` (`id=1`, `ISMC AI Server`, `qwen38`, `failed`) و یک رکورد `ai_provider_logs` (`failed`, `2026-11-05`).
- خطای LiteLLM: پیام شامل `LiteLLM_VerificationTokenTable` است ⇒ Gateway قطعاً LiteLLM است.

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | branch `15esfand1404`، HEAD `7702b68f`، tree تمیز، `ai_providers` = failed |
| GitHub | `origin/15esfand1404` = `7702b68f` (هم‌سطح) |
| Production | طبق [`PROJECT_MASTER_MAP.md:123`](../PROJECT_MASTER_MAP.md:123) دسترسی مستقیم agent وجود ندارد (SSH در 2026-11-06 timeout)؛ commit پراکشن `0b8f990d`/`cb5b8423` — یعنی **کد پراکشن از local عقب‌تر است** |

## 5) تست‌های انجام‌شده و نتایج واقعی

| تست | نتیجه |
|---|---|
| TLS/شبکه | موفق (handshake `41ms`) |
| `/v1/models` با اعتبارنامهٔ محلی | 401 |
| `chat("سلام")` با اعتبارنامهٔ محلی | 401 در `40ms` |
| تست Mock/Unit (LLM) | `php artisan test --filter="TranslationDriverTest|ActivityReportFallbackTest|ChannelMotivationSendTest"` → **10 passed / 42 assertions** |
| تست OCR/Vision واقعی | انجام نشد (بدون توکن معتبر و بدون تصویر عمومی تأییدشده) |

## 6) موارد ناشناخته و محدودیت دسترسی

- هیچ SSH/دسترسیای به `/home/pardisa2/bots` وجود ندارد ⇒ اجرای اسکریپت ممیزی روی Production ممکن نشد.
- توکن Production در دسترس نیست؛ توکن محلی نامعتبر است.
- نام دقیق و Vision بودن `qwen38`، و وجود مدل‌های Embedding/OCR/ASR، نامعلوم است.

## 7) مشکلات و ریسک‌ها

1. **امنیت TLS:** `verify=false` برای مسیر داده‌های حساس (تصویر/سند) غیرقابل‌قبول است.
2. **افشا در خطا:** پیام خطای LiteLLM شامل `Key Hash (Token)` است ⇒ هر لاگ‌گیری خام می‌تواند اثر انگشت کلید را ثبت کند. Redaction لازم است.
3. **نبود Retry / Rate-limit** در کلاینت.
4. **Health-check گمراه‌کننده:** [`AiHealthCheck.php:71`](../../app/Console/Commands/AiHealthCheck.php:71) وضعیت را با موفقیت «اتصال» روی `success` می‌گذارد حتی اگر Chat شکست بخورد.
5. **Secret در مخزن:** [`BotsTableSeeder.php`](../../database/seeders/BotsTableSeeder.php:156) توکن‌های ربات با ظاهر واقعی دارد و لاگ محلی، رمز عبور ادمین را در stack trace ثبت کرده است ⇒ چرخش/پاک‌سازی لازم است (مقادیر در این گزارش درج نمی‌شود).

## 8) قابلیت‌های قابل استفاده مجدد

- `AiProviderService` به‌عنوان Transport واحد (Auth/timeout/proxy/config).
- الگوی انتخاب Provider سالم در [`LlmTranslationService::selectHealthyProvider()`](../../app/Services/LlmTranslationService.php:95).
- `ai_providers.settings` (JSON) برای Capability Flag.
- گیت امنیتی HTTP در [`ChannelPosterMediaStore`](../../app/Services/ChannelPosterMediaStore.php:66) (timeout 30s، سقف 50MB، `verify_peer=true`) — الگوی بهتر از `config/ai.php`.

## 9) پیشنهاد MVP

فقط پس از رفع توکن و فعال‌سازی TLS:
1. `GET /v1/models` → استخراج و طبقه‌بندی (Vision / OCR / Embedding / Chat) و ذخیره در `available_models`.
2. `chat("سلام")` واقعی + ثبت latency/usage.
3. یک تصویر عمومی فارسی → `content[] = [text, image_url(data URI)]` روی مدل Vision.
4. مقایسه با متن مرجع (CER/WER) و ثبت هزینه.

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| دریافت Virtual Key معتبر LiteLLM | مالک/مدیر LiteLLM |
| اجرای اسکریپت ممیزی روی Production | دسترسی cPanel Terminal |
| تصویر نمونهٔ عمومی فارسی | مالک |
| فعال‌سازی `verify=true` برای مسیر OCR | تأیید مالک |

**Status: BLOCKED** — تا رفع توکن و تأیید TLS، هیچ تست واقعی OCR مجاز نیست.
