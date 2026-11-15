# گزارش نهایی AI-PRODUCTION-VERIFICATION-07 — Production Smoke Test + Book Pixel Help Fix

**تاریخ:** 2026-11-15 (Tehran: 19:15 / UTC 16:15)
**شاخه:** `15esfand1404` — HEAD قبل از این گزارش: `99292187` (R4 مأموریت 3)
**ایجادشده در این مأموریت:**
- `ad5c1f64` — Task B: Book Pixel /help real newlines + regression tests
- `23f536ae` — Task A2+C: runbook + این گزارش

---

## بخش A — اتصال production به LiteLLM (ai.ismc.ir)

### A.1 Audit معماری (چه چیزی واقعی در کد هست)
| سؤال | پاسخ (مستند در `docs/runbooks/AI_PRODUCTION_CONNECTION_TEST.md` §1) |
|---|---|
| provider اصلی | اولین رکورد فعال `ai_providers` |
| محل کلید | `ai_providers.api_key` با cast `encrypted` |
| ویرایش در Nova | بله — `/nova/ai-providers` → Edit → **API Key** (فقط فرم، required، در لیست/دیتیل نمایش نمی‌شود) |
| نقش `AI_DEFAULT_API_KEY` | فقط `firstOrCreate` رکورد جدید؛ **روی رکورد موجود اثر ندارد** |
| side effect `ai:discover-models` | GET models فقط؛ update `available_models`/`settings.model_capabilities`؛ بدون chat/notify |
| side effect `ai:test` | GET models + **chat واقعی** + update رکورد + **notifyAdmin در هر دو حالت** |
| side effect `ai:health-check` | `ensureDefaultProvider` (ممکن است رکورد بسازد) + connection+chat برای providerهای needsTest + `ai_provider_logs` + **notifyAllAdmins** در fail/recovery |

### A.2 Runbook برای Owner
- **فایل:** [`docs/runbooks/AI_PRODUCTION_CONNECTION_TEST.md`](runbooks/AI_PRODUCTION_CONNECTION_TEST.md)
- شامل: محل دقیق ورود توکن (Nova)، تأیید ذخیره‌شده **بدون نمایش** کلید (tinker one-liner که فقط `key_len`/`prefix_ok` چاپ می‌کند)، ورود به cPanel Terminal، **اولین دستور تشخیص شبکه** (curl با `%{time_connect}`/`%{time_appconnect}`)، ترتیب دقیق تست‌ها (`discover → health-check → ai:test`) با `date -u` برای timestamp، شکل خروجی موفق/ناموفق، جدول تصمیم‌گیری 401/403/404/Timeout/SSL، و چک‌لیست نهایی.
- **TLS هرگز خاموش نشده** (`AI_VERIFY_SSL` پیش‌فرض true؛ CA bundle `storage/certs/ismc-ai-ca-bundle.pem`).

### A.3 نردبان اتصال محلی (شواهد 2026-11-15)
| مرحله | نتیجه |
|---|---|
| DNS | `172.30.0.133` (IP خصوصی — شبکه‌ی داخلی) |
| TCP :443 | ~31ms |
| TLS | ~84ms |
| GET /v1/models (بدون کلید) | **401 در ~120ms** |
| GET /v1/models (کلید placeholder، از طریق کد app) | **401 در 179ms** |
| production | **cURL error 28 بعد از 30001ms** |

**جداسازی شبکه vs احراز هویت (مورد الزامی):** 30001ms = `connect_timeout:30` از `config/ai.php` → خطا در **TCP connect** است و **قبل از** Authorization رخ می‌دهد → **مسئله‌ی authentication نیست، مسیر شبکه‌ی production است** (فایروال/روت/DNS تا `172.30.0.133:443`). از شبکه‌ی داخلی سرویس 401 سالم برمی‌گرداند، پس endpoint کار می‌کند.

### A.4 (بلوکه روی Owner)
بعد از ثبت توکن در Nova و رفع مشکل شبکه: `ai:discover-models` → `ai:health-check` → `ai:test` (chat واقعی «سلام») → ثبت شواهد با timestamp. **هیچ Vision/Embedding بدون تست واقعی verified اعلام نمی‌شود.**

### خلاصه‌ی بخش A
| مورد | وضعیت |
|---|---|
| فایل‌های تغییرکرده | `docs/runbooks/AI_PRODUCTION_CONNECTION_TEST.md` (جدید) + این گزارش |
| commit | `23f536ae` |
| تست اجراشده | suite کامل 1027 passed / 0 failed / 2 skipped / 6054 assertions (exit 0) |
| نتیجه‌ی واقعی | شبکه+TLS از داخلی سالم؛ production TCP connect timeout = مشکل شبکه‌ی سمت production |
| بلوکه | ثبت توکن واقعی + رفع فایروال/روت production (A4) |
| بعدی برای Owner | `cd ~/<project>` → curl تشخیصی §2 → Nova → API Key → §6 |

**Commit (این بخش + C):** `23f536ae` (Task A2+C: LiteLLM production connection runbook + production verification report)

---

## بخش B — رفع /help ربات Book Pixel (raw `\n\n`)

### RCA
متن‌های Book Pixel در `lang/fa/bot.php` و `lang/en/bot.php` با **تیکتک‌های single-quote** PHP نوشته شده بودند → `\n` دو کاراکتر **backslash + n** می‌ماند، نه newline واقعی (0x0A). ربات‌های سالم (مثل BookLibraryReader) از double-quote با escape `\n` استفاده می‌کنند. با hex probe تأیید شد: `HAS_REAL_NEWLINE=false, HAS_LITERAL_BACKSLASH_N=true`.

### تغییرات
- `lang/fa/bot.php` — 3 کلید به double-quote: `book_pixel_help` (L51-60)، `book_pixel_welcome` (L24)، `book_pixel_score_message` (L39) — همان باگ نهفته در welcome و score هم رفع شد.
- `lang/en/bot.php` — همان 3 کلید (L320-329, L293, L308).
- **محتوا تغییر نکرد** — فقط newline واقعی شد؛ 9 دستور دقیقاً مطابق متن الزامی؛ placeholders `{points}/{scans}/{voices}` حفظ شدند.

### تست‌ها (TDD: اول RED، بعد GREEN)
| تست | نوع | اثبات |
|---|---|---|
| `tests/Unit/BookPixelHelpTextTest.php` (9 تست) | Unit | newline **واقعی** (0x0A)؛ **عدم وجود** `\n` خام؛ وجود هر 9 دستور (fa+en)؛ حفظ placeholders؛ default locale fa |
| `tests/Feature/BookPixelHelpTest.php` (4 تست) | Integration | مسیر واقعی webhook: `/help`، alias فارسی `راهنما`، **bale** (سازگارسازی ارسال در پلتفرم‌های پشتیبانی‌شده)، و guard regression (متن عادی → default flow + state `waiting_isbn`، نه help) |

نتیجه‌ی RED قبل از fix: **8 failed** (`expected a real newline (0x0A)`). بعد از fix: GREEN.

### سازگاری ارسال
`BotHelper::sendMessage` → vendor `Telegram::sendMessage` → curl خام (نه Laravel Http)؛ پاسخ 4xx/5xx vendor را بدون exception handle می‌کند → متن چندسطری با `parse_mode=html` در telegram و bale قابل ارسال است (کاراکترهای `<`/`>` فقط داخل `<query>` است و در html parse_mode تگ نیست؛ رفتار production فعلی بدون تغییر).

### خلاصه‌ی بخش B
| مورد | وضعیت |
|---|---|
| فایل‌های تغییرکرده | `lang/fa/bot.php`, `lang/en/bot.php`, `tests/Unit/BookPixelHelpTextTest.php` (جدید), `tests/Feature/BookPixelHelpTest.php` (جدید) |
| commit | **`ad5c1f64`** |
| تست اجراشده | 13 تست جدید (RED→GREEN) + **suite کامل: 1027 passed / 0 failed / 2 skipped / 6054 assertions / exit 0** — بدون regression |
| نتیجه‌ی واقعی | /help حالا با newline واقعی و 9 دستور کامل ارسال می‌شود؛ سایر ربات‌ها دست نخورده |
| بلوکه | هیچ |
| بعدی برای Owner | `git pull` + `php artisan config:clear` در production (lang cache) |

---

## بخش C — عملیاتی بودن AI در production (14 مورد)

Production بدون SSH است؛ امضای واقعی هر دستور + side effect audit شد و زیرمجموعه‌ی قابل‌تأییدمحلی با **خروجی واقعی** اجرا شد. جدول زیر شواهد محلی واقعی (2026-11-15) + دستور دقیق production برای Owner را نشان می‌دهد.

| # | مورد | شواهد محلی واقعی / audit | دستور production برای Owner |
|---|---|---|---|
| 1 | SHA واقعی vs GitHub | محلی: `git log`/`git status` تمیز روی `15esfand1404` | `cd ~/<project> && git fetch && git log -1 --format='%H %ci' && git status -sb` (با SHA این رپو مقایسه کند) |
| 2 | وضعیت migration | محلی: بعد از `migrate` تمام 23 migration جدید (شامل `ai_jobs`, `jobs`, `ai_flags`, `ai_feedback`, usage) **DONE** | `php artisan migrate:status 2>&1 \| grep -iE "ai_\|jobs" \| grep -v Ran` → باید خالی باشد |
| 3 | سلامت provider + تعداد مدل | audit: `ai_providers.active()->first()`؛ محلی بدون کلید واقعی 401 (A3) | `php artisan ai:discover-models` (تعداد + دسته‌بندی مدل‌ها) |
| 4 | وضعیت ai_jobs/queue | محلی (واقعی): `ai:queue-status` → همه stateها 0، `PROCESSING کهنه: 0` | `php artisan ai:queue-status --json` |
| 5 | وضعیت worker و scheduler | worker = `ai:queue-work` در schedule هر دقیقه (خط 30 schedule:list)؛ production: cron cPanel | `ps aux \| grep -E "queue:work\|queue-work" \| grep -v grep` + خروجی `php artisan schedule:list` |
| 6 | cron واقعی + schedule:run | محلی: 8 ورودی AI ثبت‌شده (`ai:health-check` هر 1h، `ai:queue-work` هر 1min، `ai:queue-flush-deferred` 10min+10:30، `ai:queue-recover-stale` 10min، `ai:alerts-check` 5min، `ai:daily-health-report` 9:00) | cPanel → Cron Jobs (باید `* * * * * php artisan schedule:run`) + `php artisan schedule:run` (یک‌باری، امن) |
| 7 | `ai:queue-status` | اجرا شد (خروجی بالا) | همان |
| 8 | `ai:rollout-status` | محلی (واقعی): `Stage سراسری: 0 (Internal)` + همه flags false + precedence | `php artisan ai:rollout-status` |
| 9 | `ai:stage-report --stage=0 --json` | محلی (واقعی): JSON کامل §10 — `stage_name: Internal`, `current_global_stage: 0`, gate criteria با `met` هرکدام | `php artisan ai:stage-report --stage=0 --json` |
| 10 | `ai:alerts-check --dry-run` | محلی (واقعی): `✅ AI alerts: آستانه‌ای فعال نیست.` (dry-run = فقط detect، بدون notify) | `php artisan ai:alerts-check --dry-run` |
| 11 | kill switch | audit + test: `ai_global_enabled` در `ai_flags` (Nova → AI Flags) روی همه bot overrides غلبه می‌کند؛ **بدون re-deploy**؛ تست `test_global_kill_switch_override_denies_without_config_change` سبز | Nova → AI Flags → `ai_global_enabled=false` → `ai:rollout-status` |
| 12 | سیاست ساعت Asia/Tehran 09–14 + Pro | testهای سبز: `test_regular_user_in_deferred_window_is_deferred_until_14_tehran`, `test_pro_user_bypasses_deferral_window`, `test_flush_deferred_releases_due_jobs_once`؛ flush 10:30 UTC (= 14:00 Tehran) | فقط مشاهده: `ai:queue-status` (DEFERRED) بعد از 14:00 Tehran صفر شود |
| 13 | رفتار عادی ربات وقتی AI خاموش است | محلی: rollout-status همه flags **false** + Stage 0 = kill-switch-off؛ core principle مأموریت 2: `AiFeatureGate` هر denial → fall-through به flow عادی (test `test_gate_never_throws` + `test_no_provider_row_is_safe_by_default` سبز)؛ Task B همین الان flow عادی Book Pixel را در production-like test تأیید کرد | با یک ربات تستی: پیام عادی بفرستید → پاسخ عادی بدون AI |
| 14 | retry/dead-letter/recovery | testهای سبز: `test_process_ai_job_retries_on_provider_error_with_backoff`, `test_process_ai_job_becomes_dead_letter_after_max_attempts`, `test_recover_stale_requeues_dead_worker_job`, `test_recover_stale_exhausts_to_failed_after_max_attempts`؛ production: `ai:queue-recover-stale` هر 10min | `php artisan ai:queue-status` (FAILED/RETRYING/EXPIRED) |

**حوزه‌ی فقط-production (بدون SSH قابل‌تأیید نبود — صادقانه):** SHA deployed (1)، cron واقعی cPanel (5/6)، تعداد مدل کشف‌شده (3)، وضعیت queue واقعی (4)، و خروجی 7–10 روی دیتای واقعی. همه‌ی دستورات **read-only یا dry-run** هستند و side effect ندارند (audit §runbook).

### خلاصه‌ی بخش C
| مورد | وضعیت |
|---|---|
| فایل‌های تغییرکرده | این گزارش (بخش C) |
| commit | `23f536ae` |
| تست اجراشده | suite کامل 1027/0/2skipped/6054 (شامل تمام تستهای retry/dead-letter/deferral/kill-switch) |
| نتیجه‌ی واقعی | زیرمجموعه‌ی محلی با خروجی واقعی سبز؛ بقیه = command sheet دقیق برای Owner |
| بلوکه | اجرای command sheet روی production (فقط Owner) |
| بعدی برای Owner | جدول 14 مورد: هر ردیف دستور دقیق دارد؛ همه read-only/dry-run |

---

## بخش D — معیارهای پذیرش (11 مورد)

| # | معیار | وضعیت |
|---|---|---|
| 1 | Owner یک مستند قابل‌اجرا می‌گیرد | ✅ `docs/runbooks/AI_PRODUCTION_CONNECTION_TEST.md` |
| 2 | محل دقیق توکن معلوم است | ✅ Nova → AI Providers → API Key (و شرط env) |
| 3 | CMD نهایی معلوم است | ✅ §3+§6 runbook (cd + date + ترتیب دستورات) |
| 4 | chat completion واقعی | ⏳ بلوکه روی توکن + شبکه (A4) |
| 5 | شواهد ثبت می‌شود | ✅ A3 محلی + `date -u` template برای production |
| 6 | timeout vs auth جدا شد | ✅ 30001ms = TCP connect (قبل از auth) ≠ 401 |
| 7 | /help رفع شد | ✅ `ad5c1f64` + 13 تست RED→GREEN |
| 8 | تست‌های regression سبز | ✅ 1027/0/2skipped/6054 |
| 9 | وضعیت queue/worker معلوم است | ✅ محلی واقعی + command sheet |
| 10 | وضعیت stage/flag معلوم است | ✅ محلی: Stage 0 + همه flags false |
| 11 | رفتار عادی ربات وقتی AI خاموش تأیید شد | ✅ (test + rollout-status) |

### محدودیت‌های رعایت‌شده
- ✅ توکن/secret در هیچ‌کدام git/گزارش/خروجی نیست (فقط `key_len`/`prefix_ok`).
- ✅ تغییر setting مشتری ندیده شد (فقط lang + test + docs).
- ✅ Stage عمومی فعال نشد (هنوز 0)، allowlist دست‌نخورده.
- ✅ migration جدید ساخته نشد (فقط `migrate` روی محلی).
- ✅ production بدون تأیید Owner تغییر نکرد.

---

## تصمیم نهایی

## **GO (مشروط) برای شروع Pilot**

- **GO** — از نظر کد، تست، و معماری: Book Pixel fixed و با 13 تست محصور شد؛ kill switch/deferral/retry/dead-letter همه سبز؛ Stage 0 امن است.
- **مشروط بر 2 مورد فقط-production** (هر دو در اختیار Owner است، نه کد):
  1. **رفع TCP connect timeout** production به `ai.ismc.ir:443` (runbook §2 — فایروال/روت/DNS).
  2. **ثبت توکن واقعی** در Nova و سبز شدن `ai:discover-models` + `ai:health-check` + `ai:test` (runbook §6).
- تا آن دو سبز نشوند: Pilot در **Stage 0** می‌ماند (AI خاموش، رفتار ربات عادی تضمین‌شده) — یعنی GO برای deploy کد + smoke test‌های production، ولی **آغاز exposure مشتریان** (Stage 1) فقط بعد از Sبز شدن runbook §8.

**STOP — منتظر Owner:** هر تغییری در Rollout Stage یا هر customer exposure نیازمند تأیید صریح Owner است.
