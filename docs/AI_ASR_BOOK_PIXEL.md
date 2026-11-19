# WS-G — ASR MVP در Book Pixel (صدا به متن، زبان fa)

## خلاصه

کاربر Book Pixel پس از ارسال اسکن صفحه، می‌تواند وویس توضیح صفحه را بفرستد. با
فعال‌شدن flag ASR، وویس به‌صورت **idempotent** وارد صف AI می‌شود و در بک‌گراند
(توسط `ProcessAiJob`) به متن فارسی تبدیل و روی همان رکورد وویس ذخیره می‌شود.

**اصل بنیادین:** شکست ASR هرگز جریان Bot عادی را نمی‌شکند. trigger هرگز throw
نمی‌کند و hook webhook در try/catch مجزا قرار دارد.

## جریان

```
Webhook (voice)
  └─ handleVoice()
       ├─ createVoice()                    ← جریان عادی (همیشه)
       ├─ awardVoicePoints()               ← جریان عادی (همیشه)
       ├─ update BookPublishingQueue       ← جریان عادی (همیشه)
       ├─ sendMessage(voice_sent)          ← جریان عادی (همیشه)
       ├─ try { BookPixelAsrService->triggerForVoice() } catch (Log only)
       │     ├─ AiFeatureGate::check(FEATURE_ASR, ...)   ← 6 لایه + kill switch
       │     └─ AiJobDispatcher::dispatch('asr', ...)    ← idempotent + سیاست تهران
       │           └─ if queued → Queue::push(ProcessAiJob)  (queue 'ai')
       └─ clearState()
```

## اجرا (Executor)

`ProcessAiJob` → match feature `'asr'` → `BookPixelAsrService::execute($job)`:

1. `voice_id` از payload → `BookPageVoice` (نیافتنی → `voice_not_found`)
2. token Bot بر اساس origin (`telegram` / `bale`) (نبود → `no_bot_token`)
3. `GET {platform}/bot{token}/getFile` → `file_path` (خطا → `file_get_failed`)
4. `GET {platform}/file/bot{token}{file_path}` → باینری (خطا → `file_download_failed`)
5. ذخیره در `storage/app/ai-tmp/asr_{id}_{uniqid}.ogg`
6. `TranscriptionAdapter::transcribe($tmp, 'fa')` → `POST /v1/audio/transcriptions`
7. `asr_text` ذخیره می‌شود (فقط وقتی متن غیرخالی باشد) + `result_reference = voice:{id}`

فایل موقت در `finally` حذف می‌شود.

## تضمین «خروجی جعلی نیست»

- اگر مدل متن خالی/blank بدهد: `asr_text` **null می‌ماند** و job با
  `empty_result` retry می‌شود (نه متن پیش‌فرض، نه «…»).
- اگر endpoint ASR در LiteLLM موجود نباشد (404/405): `endpoint_missing` —
  یعنی ASR را «تزئینی» روشن نکرده‌ایم؛ job retry می‌شود تا endpoint اضافه شود.

## جدول error codes

| code | منبع | retry؟ |
|---|---|---|
| `voice_not_found` | executor | بله (تا max_attempts) |
| `no_bot_token` | executor | بله |
| `file_get_failed` | getFile پلتفرم | بله |
| `file_download_failed` | دانلود فایل | بله |
| `endpoint_missing` | 404/405 در LiteLLM | بله |
| `auth_failed` | 401/403 | بله |
| `rate_limited` | 429 | بله |
| `provider_error` | ≥500 | بله |
| `http_error` | سایر status | بله |
| `file_not_found` | فایل محلی خالی/نبود | بله |
| `exception` | هر Throwable در adapter | بله |
| `empty_result` | متن خالی از مدل | بله |

هر کد روی `asr_error` ذخیره می‌شود (قابل رصد در Nova/DB).

## تغییرات Schema

`book_page_voices` (migration `2026_11_14_150000`):

| column | type | توضیح |
|---|---|---|
| `asr_text` | longText NULL | متن تبدیل‌شده (فقط بعد از موفقیت) |
| `asr_error` | varchar(120) NULL | error_code آخرین تلاش ناموفق |
| `asr_at` | timestamp NULL | زمان آخرین تلاش |

## پیکربندی

| env | config | پیش‌فرض |
|---|---|---|
| `AI_FLAG_ASR` | `ai.asr_enabled` / flag gate | `false` |
| `AI_ASR_MODEL` | `ai.asr_model` | `whisper-1` |

سیاست زمان (مشترک با بقیه قابلیت‌ها): کاربران regular در پنجره 09–14 (تهران)
deferred تا flush ساعت 14؛ Pro 24ساعته فوراً.

## Rollback

```env
AI_FLAG_ASR=false
```

بدون deploy کافی است flag خاموش شود؛ hook trigger را نمی‌خواند و job جدیدی
ساخته نمی‌شود. Jobs در صف با flush/expire خودبه‌خود جمع می‌شوند.

## تست‌ها (11 test — tests/Feature/BookPixelAsrTest.php)

| # | تست | وضعیت |
|---|---|---|
| 1 | webhook voice → asr job (gate open) | ✅ |
| 2 | webhook voice flow intact (gate closed) | ✅ |
| 3 | executor persist asr_text on success | ✅ |
| 4 | executor retry on provider 500 | ✅ |
| 5 | executor classifies 404 as endpoint_missing | ✅ |
| 6 | executor retry when getFile fails | ✅ |
| 7 | executor persist error when download fails | ✅ |
| 8 | empty ASR content → empty_result (no fake text) | ✅ |
| 9 | pro user in deferred window → immediate | ✅ |
| 10 | regular user in deferred window → 14:00 Tehran | ✅ |
| 11 | duplicate trigger → single job (idempotency) | ✅ |

## باگ پیشین که در همین workstream اصلاح شد

مدل `BookPublishingQueue` خاصیت `$table` نداشت؛ Eloquent جدول
`book_publishing_queues` (جمع‌شده) را می‌خواست در حالی که migration جدول مفرد
`book_publishing_queue` ساخته بود. نتیجه: **همه‌ی کوئری‌های publishing queue با
«table not found» شکست می‌خوردند** و در `handleVoice` باعث می‌شد جریان عادی
voice (ارسال پیام + پاک‌شدن state) شکسته شود. با افزودن
`protected $table = 'book_publishing_queue';` اصلاح شد.

## فایل‌های تغییرشده

| فایل | تغییر |
|---|---|
| `database/migrations/2026_11_14_150000_add_asr_columns_to_book_page_voices_table.php` | جدید |
| `app/Services/BookPixel/BookPixelAsrService.php` | جدید (trigger + executor) |
| `app/Models/BookPageVoice.php` | fillable + cast `asr_at` |
| `app/Models/BookPublishingQueue.php` | `protected $table` (bugfix) |
| `app/Jobs/ProcessAiJob.php` | arm `'asr'` + `executeAsr()` + import |
| `app/Http/Controllers/BookPixelController.php` | hook ASR در `handleVoice` + import |
| `lang/fa/bot.php` / `lang/en/bot.php` | `book_pixel_asr_started` |
| `tests/Feature/BookPixelAsrTest.php` | جدید (11 test) |
