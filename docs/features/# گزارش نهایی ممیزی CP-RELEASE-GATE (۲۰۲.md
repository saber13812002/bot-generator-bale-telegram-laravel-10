# گزارش نهایی ممیزی CP-RELEASE-GATE (۲۰۲۶-۱۰-۲۵)

## ۱) وضعیت Git و شاخه
- **کامیت ممیزی‌شده (فاز ۱):** `5ecc2ef11f0237fc19f5e3a2cff5ea76e183d796` — ۲۹ فایل، +9493/−61، درخت کاری هنگام ممیزی تمیز.
- **کامیت اصلاح (فاز ۹):** `8f4f7f90325d6dd29636a7dbd1fcef99dfcfe9af` — ۶ فایل، +218/−24 (اصلاح `extractMessageId` + guard پاکت خطا + تست‌ها + مستندات + `scripts/cp_gate_schema_probe.php`).
- **شاخه:** `15esfand1404`. درخت کاری فعلی: فایل‌های ردیفی بدون ردگیری که باید حذف شوند (آرتیفکت‌های پروب این ممیزی + چند فایل پیشین): `a1.txt`, `implode(`, `p1.txt`, `t1.txt`, `t2.txt`, `tests_cp_gate_cmd.txt`, `tests_cp_gate_full.txt`, `tests_cp_gate_out.txt`, `tests_stderr_probe.txt`.
- **عدم‌انطباق کد/گزارش:** شمارش‌های قدیمی تست در دو مستند (۱۷/۱۰) با واقعیت (۲۴/۱۱) نخواند؛ هنگام اصلاح فاز ۹ به ۲۶/۱۲ اصلاح و با `Select-String` تایید شد.

## ۲) مشکلات اثبات‌شده (و اصلاح‌شده)
1. **پذیرش `chat_id` به‌عنوان شناسه پیام** در `app/Services/RubikaBotClient.php:extractMessageId()` — مستندات رسمی روبیکا (rubika_methods.html) می‌گوید `chat_id` شناسه **چت** است (پارامتر ورودی، جدا از `message_id`) و خروجی `sendMessage` فقط `message_id` است → **خطای «موفقیت کاذب» و ذخیره شناسه اشتباه در لاگ‌های publish**. اصلاح: زنجیره هم‌معنا فقط `['message_id','id']` + docblock فارسی.
2. **پذیرش پاکت‌های خطای 2xx** (`code`/`error`) که `id` یا `message_id` می‌داشتند (شکل خطا در Production واقعاً مشاهده شده بود). اصلاح: helper جدید `hasErrorEnvelope()` — اگر **هر کلید** حاوی `error` یا `code` باشد، `extractMessageId` حتی با وجود `message_id` مقدار `null` برمی‌گرداند (سخت‌گیرانه‌ترین حالت «بدون موفقیت کاذب از خطا»). خطرات بازگشت تو در تو (`diagnose→hasMessageId→extractMessageId→diagnose`) با جدا نگه‌داشتن guard حل شد.
- هر دو با **روش تست‌محور**: ۴ تست شکست‌خورده روی کد قدیم → ۳۸/۳۸ سبز پس از اصلاح.

## ۳) موارد مشکوک / بدون شواهد کافی (صحه‌ای نشده‌اند، به‌عنوان فرض مستند)
- هم‌معنای `id` — مستندات رسمی روبیکا خروجی `sendMessage` را فقط `message_id` اعلام می‌کند؛ `id` به‌عنوان **فرض محافظه‌کارانه** نگه داشته شد (تست‌دار).
- رندر بصری لینک در بله (فقط پذیرش API `1184` زنده؛ رندر مارک‌داون/دکمه در اپ بله تأیید نشده — ⚠️).
- سقف کپشن در سروش/روبیکا/بله — رسمی نیست (فقط ۱۰۲۴ تلگرام رسمی است) → در `docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md` به‌عنوان **ASSUMPTION** مستند شد.
- `parse_mode` زنده سروش: در مستندات رسمی تأیید، ولی بلافاصله client به‌صورت conservative بدون آن ارسال می‌کند.
- بسته‌بندی واقعی پاسخ زنده روبیکا: یک پاسخ 2xx مشاهده شد ولی `message_id` مستند نداشت → طبق قاعده جدید صحیح‌به‌درستی «شکست + لاگ تشخیصی» گزارش می‌شود؛ برای درک نهایی بسته‌بندی واقعی، **یک تست زنده پس از Deploy** لازم است (شرط C4).

## ۴) تست‌های جدید و نتایج
- `tests/Unit/RubikaBotClientTest.php` (۲۴→۲۶): `test_extract_message_id_rejects_chat_id_as_message_identifier`، `test_extract_message_id_ignores_ids_in_error_envelope`، `test_extract_message_id_prefers_documented_message_id_over_alias` + به‌روزرسانی تست‌های قبلی.
- `tests/Feature/ChannelPosterRubikaTestSendTest.php` (۱۱→۱۲): `test_rubika_test_send_chat_id_only_is_not_a_message_success` (تأیید UUID correlation_id، http_status=200، absence of token در لاگ) و `test_rubika_test_send_http_429_fails_with_safe_exception_log` (HTTP 429 → شکست + لاگ امن با class استثنای).
- **نتیجه:** ۴ شکست روی کد قدیم → **۱۳۲ passed / 661 assertions** روی ۸ کلاس بازگشتی (قبلاً ۱۲۹/۶۵۴).

## ۵) سازگاری با نسخه قدیم (Legacy)
- `SignatureRenderer::renderLegacy()` با خروجی قدیمی `build_signature` **بایت‌به‌بایت یکسان** (تست‌دار: `test_render_legacy_matches_old_build_signature_format`).
- ردیف‌های قدیم با `signature_enabled=NULL` → رندر با مقدار پیش‌فرض پلتفرم + label پیش‌فرض (تست: `test_legacy_row_with_null_enabled_renders_platform_default_label`).
- ستون‌های جدید **nullable/اضافی**؛ رفتار پیش‌فرض ردیف‌های قدیم تغییر نکرده است؛ ارسال دوباره بدون امضای دوبل (فاز ۴: `test_queue_retry_publishes_only_failed_destination_without_double_signature`).

## ۶) رأی مایگریشن
**PASS.** `2026_10_24_000001_add_signature_settings_to_channel_poster_destinations.php` روی دیتابیس محلی توسعه (MySQL `berimbasket_bot_generator_promoter`) آزمایش شد: `migrate` → `rollback` → `migrate` همه تمیز؛ ستون‌ها با `SHOW FULL COLUMNS` تأیید شد: `signature_label varchar(255) NULL`، `signature_type varchar(20) NOT NULL default 'inline'`، `signature_enabled tinyint(1) NULL` (هر سه بعد از `channel_link`). ۰ ردیف محلی؛ حفظ ردیف‌های قدیم با تست پوشش داده شده. **نکته مهم:** در Production **۳ مایگریشن** باید اجرا شود (این یکی + ۲ مایگریشن media که قبلاً pending بودند: `2026_10_16_210000_add_media_path...` و `2026_10_17_100000_add_file_name_and_mime...`). `down()` = حذف ۳ ستون (داده‌های تنظیمات امضا حذف می‌شود) → **قبل از اجرا Backup الزامی** (شرط C1).

## ۷) رأی message_id روبیکا
- قرارداد رسمی: خروجی `sendMessage` = فقط `message_id`؛ `chat_id` = شناسه چت (ورودی) → **هم‌معنای `chat_id` حذف شد** (اثبات‌شده از مستندات رسمی).
- تعریف موفقیت پس از اصلاح: **HTTP 2xx + (message_id | id) + نبود کلیدهای error/code** در کل پاکت.
- **تأیید تحویل (delivery confirmation) از طریق API روبیکا وجود ندارد**؛ وجود `message_id` صرفاً پذیرش پیام توسط API را نشان می‌دهد.
- 2xx + JSON ناقص/بسته‌بندی خطا → **هرگز موفقیت قطعی نیست** (guard کل‌پاکتی + لاگ تشخیصی با `diagnose()` بدون نشت مقدار).

## ۸) وضعیت Exit Code — «Tests Passed, Process Failed»
- **۱۳۲ تست pass (۶۶۱ assertion) اما exit code = 1** → طبق قانون فاز ۶: **«Tests Passed, Process Failed»**.
- ریشه exit=1 (پیشین، نه از این کامیت‌ها): ۲ هشدار runner PHPUnit از `cc34d011` — (الف) `tests/Unit/QuranTest.php` کلاس `QuranTest` بدون extend از `PHPUnit\Framework\TestCase` است، (ب) `StringHelperTest` هیچ تستی ندارد. ۵۸ deprecated خام فقط نویز vendor روی PHP 8.5 است و **سبب exit نیست**؛ `phpunit.xml` فاقد `failOnDeprecation` است.
- در CI به‌عنوان **Failure** شمرده می‌شود (مشکل پیشین). اصلاح پیشنهادی (به‌دلیل out-of-scope اجرا **نشده**): extend یک خطی برای `QuranTest`؛ افزودن یک تست به `StringHelperTest`؛ یا `--migrate-configuration`.

## ۹) ماتریس کامل شواهد (۵ پلتفرم × قابلیت‌ها) — فقط شواهد واقعی
| قابلیت | تلگرام | بله | ایتا | سروش | روبیکا |
|---|---|---|---|---|---|
| Webhook/Update parsing | LIVE VERIFIED | LIVE VERIFIED | LIVE VERIFIED | MOCK PASS | MOCK PASS |
| ارسال متن به کانال | LIVE VERIFIED (169519) | LIVE VERIFIED (1184) | LIVE VERIFIED (177667926) | MOCK PASS | NOT TESTED (زنده: 2xx بدون message_id → طبق قاعده جدید = شکست + diagnostic) |
| ارسال عکس | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS |
| ارسال ویدیو | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS |
| ارسال Document | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS |
| کپشن + امضای inline | IMPLEMENTED (رسمی HTML) | IMPLEMENTED (مارک‌داون — رندر ⚠️) | IMPLEMENTED (HTML) | IMPLEMENTED (plain) | IMPLEMENTED (plain) |
| دکمه URL | IMPLEMENTED (رسمی) | IMPLEMENTED (رسمی) | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED |
| parse_mode | LIVE VERIFIED | MOCK PASS (رندر ⚠️) | MOCK PASS | MOCK PASS (client conservative) | MOCK PASS (plain فقط) |
| retry/جلوگیری از تکرار (queue) | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS (سرویس queue مستقل از پلتفرم: PENDING-only + skip log-publish موفق + FAILED ترمینال) |
| لاگ امن (بدون نشت secret) | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test (token redact + 429/403/timeout) |
| test-send از ربات | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test | IMPLEMENTED + test (12 تست) |
| امضای پیشرفته (3-step /sign) | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS | MOCK PASS |
| حد کپشن 1024 | رسمی (Telegram) | ASSUMPTION | ASSUMPTION | ASSUMPTION | ASSUMPTION |

## ۱۰) ریسک‌های Production
1. **`storage/logs/laravel.log` بدون چرخش + `LOG_LEVEL=debug`** (`.env`: `LOG_CHANNEL=stack` = `single`+`database`) — محلی ۷٫۶۸MB؛ **مشخصه‌ی اصلی فراتر رفتن از ۵GB کوتا** (پیشین، نه از این کامیت؛ هیچ دستور rotate در app وجود ندارد). لاگ DB (DatabaseLogHandler) سقف ۵۰۰۰ ردیف + prune روزانه دارد؛ مدیا با prune-stale روزانه (04:00) محدود است.
2. **۳ مایگریشن pending در Production** (C1) — بدون Backup اجرا نکنید.
3. **تغییر رفتار روبیکا** (C4): پس از Deploy، پاسخ‌های زنده بدون `message_id`/`id` یا با کلید `code`/`error` از «موفقیت کاذب» به «شکست + diagnostic» تبدیل می‌شوند — آگاهانه و امن‌تر، ولی تعداد item FAILED در گزارش‌ها ممکن است موقتاً بالا برود (retry دستی از طریق /queue).
4. **بدون backoff برای 429**: استثنای 429 → item FAILED ترمینال؛ retry فقط دستی. محدودیت مستند.
5. بلاک‌کننده غیرحتمی: رندر بصری بله، parse_mode زنده سروش، link-سازی خودکار روبیکا.

## ۱۱) Deploy و Rollback ایمن
**Deploy (اجرا با تأیید مالک — من هیچ اقدامی روی Production انجام ندادم):**
1. `mysqldump` کل دیتابیس (Backup قبل از هر چیزی).
2. `git pull` تا `8f4f7f90` (روی شاخه `15esfand1404`).
3. `php artisan migrate` (۳ مایگریشن pending).
4. `php artisan config:clear && php artisan route:clear` (تغییر config/route نیست، ولی عادت ایمن).
5. `php artisan queue:restart` (workerهای در حال اجرا کد جدید را بگیرند) + reload FPM (opcache).
6. تایید: `php artisan channel-poster:diagnose` و `php artisan channel-poster:watchdog --dry-run`.
7. **C4:** یک test-send زنده روبیکا از ربات → بسته‌بندی واقعی را با `correlation_id` در لاگ‌ها ثبت کنید.
**Rollback ایمن:**
1. `git revert 8f4f7f90 5ecc2ef` (یا checkout به `85e1ce54`).
2. `php artisan migrate:rollback --step=3` — ⚠️ ۳ ستون امضا حذف می‌شوند → داده‌های تنظیمات امضا از بین می‌روند (در صورت نیاز، قبل از rollback از `channel_poster_destinations` Backup بگیرید).
3. `php artisan queue:restart` + reload FPM.

**دستورهای عملیاتی (اثر + ریسک):** `channel-poster:process` (ارسال آیتم‌های PENDING — خطر تکرار ندارد: log-publish موفق skip می‌شود)؛ `channel-poster:watchdog --dry-run` (فقط گزارش)؛ `channel-poster:prune-stale --days=7` (حذف آیتم‌های پیر + مدیای یتیم — **ویرانگر**، با `--dry-run` اول)؛ `observability:prune` (prune لاگ/health DB — سقف ۵۰۰۰)؛ برای laravel.log: `LOG_LEVEL=info` در `.env` + چرخش (logrotate یا rename+restart) — **اجرا به مالک واگذار شد** (فاز ۸).

## ۱۲) رأی نهایی: **CONDITIONAL GO** ✅

**بلوک واقعی Deploy:** چیزی مطلقاً بلوک‌کننده نیست؛ Deploy مجاز است **فقط با رعایت ۴ شرط:**
- **C1:** Backup کامل DB + اجرای ۳ مایگریشن pending در Production (تأیید مالک).
- **C2:** پذیرش اینکه CI به‌دلیل ۲ هشدار runner پیشین قرمز است (اصلاح پیشنهادی در بخش ۸ — یک خطی).
- **C3:** تدبیر عملیاتی لاگ بلافاصله بعد از Deploy: `LOG_LEVEL=info` + rotate برای `laravel.log` (مخاطره ۵GB).
- **C4:** یک test-send زنده روبیکا پس از Deploy برای ثبت بسته‌بندی واقعی (با correlation_id) — اگر بدون message_id باشد، شکست امن + diagnostic گزارش می‌شود.

این پروژه می‌تواند به فاز بعدی (Deploy) برود؛ موارد «شکاکت/بدون شواهد» (بخش ۳) بلاک نیستند و بعداً با تست‌های زنده صاحب‌محترم قابل رفع‌اند. هیچ اقدامی بدون تأیید صریح شما روی Production انجام نشده است.