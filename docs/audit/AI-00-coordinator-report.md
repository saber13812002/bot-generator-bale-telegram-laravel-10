# AI-00 — گزارش نهایی هماهنگ‌کننده (PARALLEL-AI-ENABLEMENT-DISCOVERY-01)

> این فاز **فقط Discovery/Audit** بود. هیچ کد، Migration، تنظیمات، Commit، Push یا Deploy انجام نشد.

## 0) وضعیت مأموریت‌ها

| # | مأموریت | وضعیت | مانع اصلی |
|---|---|---|---|
| 1 | OCR / LiteLLM Verification | **BLOCKED** | توکن LiteLLM نامعتبر (401) + بدون دسترسی Production + TLS تأییدنشده |
| 2 | Universal Bot Inventory | **READY** (برای طراحی) | DB پراکشن دسترسی‌ناپذیر؛ DB محلی خالی |
| 3 | ASR Discovery | **NEEDS-DESIGN / BLOCKED** | نبود ffmpeg/STT و نامعلوم بودن مدل ASR |
| 4 | OCR Bot Integration | **NEEDS-DESIGN** | وابسته به AI-01 و AI-07 |
| 5 | Personal AI Companion | **NEEDS-DESIGN** | وابسته به AI-01/AI-07 + سیاست Consent |
| 6 | Nova HTTP 500 RCA | **BLOCKED** | 500 بازتولید شد؛ لاگ سرور در دسترس نیست |
| 7 | Queue / Isolation / Resilience | **NEEDS-DESIGN** | `sync`، بدون Worker، بدون Idempotency/Rate-limit |

## 1) نقشهٔ کل زیرساخت AI (وضعیت واقعی)

```
                     ┌───────────── config/ai.php (base, model=qwen38, verify=false) ─────────────┐
Webhook / Command ──►│ AiProviderService (OpenAI-compatible, Bearer, timeout 30/60, NO retry)   │──► LiteLLM
                     └───────────────────────────────────────────────────────────────────────────┘
                                                              │ 401 token_not_found_in_db
   مصرف‌کننده‌ها: LlmTranslationService (RSS) · ActivityReportSummaryService (گزارش قرآن)
                 ChannelMotivationSend (کانال) · HttpGrowthLlmProvider (کلید جدا، Growth)
```
- `https://ai.ismc.ir/api` = **LiteLLM Proxy** (از پیام خطای اختصاصی آن تأیید شد).
- مدل‌ها: فقط alias `qwen38` ثبت شده؛ **لیست واقعی مدل‌ها موجود نیست** (`available_models=NULL`).
- Vision / OCR / ASR / Embedding در کد: **هیچ**. در LiteLLM: **نامعلوم**.

## 2) فهرست ربات‌ها

۳۷ نوع ربات در ۵ گروه (جزئیات: [AI-02](AI-02-bot-inventory.md)). ربات‌های کلیدی برای AI: Quran، Hadith/Nahj، Book Pixel، Book Library/Reader، Growth Companion، Prayer Qadha، Channel Poster.

## 3) ماتریس OCR / ASR / LLM / RAG

| ربات | OCR | ASR | LLM | RAG | اولویت |
|---|---|---|---|---|---|
| Book Pixel | ⭐⭐ | ⭐ | — | — | 1 |
| Quran | ⭐ | — | ✅ | ⭐⭐ | 2 |
| Growth Companion | — | ⭐ | ⭐⭐ | — | 3 |
| Hadith / Nahj | — | — | — | ⭐⭐ | 4 |
| Prayer Qadha | — | ⭐ | ⭐ | — | 5 |
| Book Library | ⭐ | — | — | — | 6 |
| Channel Poster | ⭐ | — | ✅ | — | 7 |

## 4) وضعیت واقعی LiteLLM و مدل‌ها

| مورد | نتیجه |
|---|---|
| Reachability | ✅ (TLS handshake ~41ms) |
| Auth | ❌ 401 `token_not_found_in_db` |
| `/v1/models` | ❌ دسترسی‌ناپذیر |
| Chat «سلام» | ❌ 401 در 40ms |
| آخرین تست DB | `2026-11-05` — failed |
| محدودیت | `verify=false` ⇒ پیش‌نیاز اجرای تست OCR رعایت نشده |

## 5) وضعیت Nova

`GET /nova/login` = **500** (بازتولید شد، `APP_DEBUG=false`، بدون Stack Trace). علل محتمل به‌ترتیب: config/route cache کهنه پس از deploy، Assets منتشرنشده (`public/vendor/nova`)، Migration ناقص، ناسازگاری fork 4.32.16 با PHP 8.2. تأیید نهایی نیازمند `storage/logs/laravel.log` پراکشن است.

## 6) معماری Queue و تاب‌آوری

- `QUEUE_CONNECTION=sync`، بدون Worker، بدون جدول `jobs`، ۱۲ Job بدون `$tries/$timeout/$backoff`.
- AI به‌صورت **همگام** در وب‌هوک و Scheduler اجرا می‌شود ⇒ ریسک Block/timeout/تکرار.
- پیشنهاد: صف جدا `ai`، جدول `ai_jobs` با `idempotency_key` یکتا، Rate-limit توکن‌باکت، Fallback صریح.
- سیاست ۹–۱۴: امروز **وجود ندارد**؛ پیاده‌سازی نیازمند تصمیم دربارهٔ TZ (سرور UTC)، مرزها، استثنای Pro، و رفتار defer (نه fail) است.

## 7) MVPهای قابل انتشار

| MVP | ارزش | پیش‌نیاز | ریسک |
|---|---|---|---|
| **A. عکس‌به‌متن در Book Pixel** | بالا — کاربر امروز عکس می‌فرستد و هیچ پردازشی نمی‌گیرد | AI-01 (توکن+Vision) + AI-07 (صف) | متوسط |
| **B. دستیار رشدیار** | بالا — وضعیت/حافظه/Pro موجود است | AI-01 + AI-07 + Consent | متوسط (حریم خصوصی) |
| **C. صوت به متن در رشدیار** | متوسط-بالا — حذف اصطکاک ورودی | AI-03 + AI-07 | بالا (نبود ffmpeg) |
| **D. RAG قرآن/حدیث** | بالا اما پرهزینه | Embedding + برداری‌سازی + AI-01 | بالا |

## 8) وابستگی‌های بین مأموریت‌ها

```
AI-01 (توکن/TLS) ──► AI-04 (OCR bot) ──► MVP A
        │                  ▲
        ├──► AI-03 (ASR) ──┘         MVP C
        ├──► AI-05 (Companion) ────── MVP B
        └──► AI-07 (Queue)  ◄── پیش‌نیاز هر سه
AI-06 (Nova) مستقل — مانع دسترسی ادمین به مدیریت Providerها
```

## 9) اولویت‌بندی نهایی

| رتبه | اقدام | ارزش مشتری | زمان | ریسک | هزینه |
|---|---|---|---|---|---|
| 1 | رفع Nova 500 (دسترسی ادمین) | بالا (بازگرداندن کنترل) | ۱–۲ ساعت | کم | ۰ |
| 2 | توکن LiteLLM + TLS + کشف مدل‌ها | حیاتی (پیش‌نیاز همه) | ۱ روز | کم | ۰ |
| 3 | صف + `ai_jobs` + Idempotency | حیاتی (پایداری) | ۲–۴ روز | متوسط | ۰ |
| 4 | MVP A: عکس‌به‌متن (Book Pixel) | بالا | ۳–۵ روز | متوسط | کم |
| 5 | MVP B: دستیار رشدیار | بالا | ۵–۸ روز | متوسط | متوسط |
| 6 | ASR / RAG | متوسط | ۱–۲ هفته | بالا | متوسط-بالا |

### پیشنهاد نهایی برای اولین مشتری واقعی
**MVP A — «عکس‌به‌متن» روی Book Pixel** (و در صورت وجود ظرفیت، دکمهٔ «متن‌خوان» در همان ربات). دلیل: کاربر عکس را همین امروز می‌فرستد، خروجی فوراً قابل مشاهده است، وابستگی به حافظه/حریم خصوصی گفتگو ندارد، و شکست AI به‌سادگی به پیام «بعداً تلاش کنید» تنزل می‌یابد. دستیار رشدیار ارزش بالاتری دارد اما ریسک حریم خصوصی و طراحی حافظه آن را به فاز دوم می‌برد.

## 10) ریسک‌های امنیتی فوری (کشف‌شده در این audit)

1. توکن‌های ربات با ظاهر واقعی در [`database/seeders/BotsTableSeeder.php`](../../database/seeders/BotsTableSeeder.php:156) ⇒ مخزن و تاریخ Git، محل افشا است.
2. `storage/logs/laravel.log` محلی، رمز یک ادمین را در stack trace دستور `nova:user` دارد.
3. `verify=false` در `config/ai.php` برای مسیر داده‌های حساس.
4. خطای LiteLLM شامل `Key Hash (Token)` است ⇒ هر لاگ خام، اثر انگشت کلید را ثبت می‌کند.

**پیشنهاد:** چرخش توکن‌های افشاشده، حذف Seeder حاوی Secret، افزودن Redaction به لاگ AI، و فعال‌سازی TLS برای مسیر OCR.

## 11) بازتأیید هماهنگ‌کننده (۲۰۲۶-۱۱-۱۳، ۱۹:۵۲ Tehran) — فقط خواندن

هماهنگ‌کننده، شواهد کلیدی گزارش‌ها را با اجراهای Read-only مستقل بازتأیید کرد (بدون هیچ تغییر):

| ادعای گزارش | بازتأیید زنده | نتیجه |
|---|---|---|
| AI-01: توکن محلی LiteLLM نامعتبر | `GET /v1/models` با اعتبارنامهٔ ذخیره‌شده → **HTTP 401** `token_not_found_in_db` (خطا حاوی `LiteLLM_VerificationTokenTable` و Key Hash) | ✅ تأیید |
| AI-01: تست «سلام» | `POST /v1/chat/completions` → **HTTP 401** در `102ms` | ✅ تأیید |
| AI-06: Nova 500 | `GET https://bots.pardisania.ir/nova/login` → **500** «Server Error»، بدون Stack Trace (`APP_DEBUG=false`) | ✅ هنوز فعال |
| AI-07: `QUEUE_CONNECTION=sync` | [.env](../../.env) و [.env.example](../../.env.example) هر دو `sync` | ✅ تأیید |
| AI-01: `verify=false` | [`config/ai.php:28`](../../config/ai.php:28) | ✅ تأیید |
| AI-01: بدون پشتیبانی تصویر در کلاینت | [`AiProviderService::chat()`](../../app/Services/AiProviderService.php:112) فقط `content` رشته‌ای | ✅ تأیید |
| AI-01 §10: Secret در مخزن | [`BotsTableSeeder.php:158`](../../database/seeders/BotsTableSeeder.php:158) توکن Bale واقعی در repo | ✅ تأیید |
| AI-03: PHP محلی | `php -v` → 8.5.5 (مستند PMM برای release-gate: 8.2.12) | ✅ سازگار |

**نتیجه:** همهٔ شواهد ۷ گزارش با وضعیت زندهٔ امروز مطابقت دارد؛ گزارش‌ها معتبرند. هیچ کد، Migration، Commit، Push یا Deploy در این بازتأیید انجام نشد.

---

**STOP.** منتظر تأیید مالک برای ترتیب اجرای اقدامات بالا هستم؛ هیچ پیاده‌سازی آغاز نشده است.
