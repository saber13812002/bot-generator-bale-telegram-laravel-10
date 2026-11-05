# ربات تشخیصی روبیکا (BM-RUBIKA-DIAGNOSTIC-BOT)

ربات جدید BotMother با شناسهٔ نوع **`rubika-diagnostic`** — هم‌خانواده با
GetChatID (نوع #2) اما اختصاصی برای **عیب‌یابی و تست زندهٔ API روبیکا**.
این ربات به‌جای «فقط شناسه چت»، گزارش کامل تشخیصی از هر پیام و رویداد می‌دهد
تا ساختار واقعی آپدیت‌ها و پاسخ‌های API روبیکا (که در مستندات ناقص است)
قابل مشاهده باشد.

> ⚠️ همهٔ قابلیت‌ها در حال حاضر **MOCK_TESTED** (تست با `Http::fake`) هستند.
> هیچ تست زنده‌ای انجام **نشده** است (طبق سیاست مأموریت، تست زنده و دپلوی
> بدون تأیید صریح کاربر انجام نمی‌شود).

## ۱. شناسهٔ نوع جدید و مسیر

| مورد | مقدار |
|---|---|
| endpoint_id (نوع جدید) | `rubika-diagnostic` |
| نام نمایشی | ربات تشخیصی روبیکا |
| مسیر webhook | `POST /api/webhook-rubika-diagnostic` |
| فرم URL کامل | `/api/webhook-rubika-diagnostic?origin=rubika&token={توکن}&bot_mother_id=1&bot_id={id}` |
| کانتroller | `app/Http/Controllers/RubikaDiagnosticBotController.php` |
| نرمال‌ساز آپدیت | `app/Services/Rubika/RubikaUpdateNormalizer.php` |
| کلاینت API | `app/Services/RubikaBotClient.php` (مشارکتی با Phase 3/4/5) |
| دستور پُلیнг | `app/Console/Commands/RubikaDiagnosticPollCommand.php` |
| Route | `routes/api.php:56` |
| Seeder | `database/seeders/RubikaDiagnosticWebhookEndpointSeeder.php` |
| ورودی importer | `app/Services/WebhookEndpointDefaultImporter.php:259-266` |
| Migration ستون‌ها | `database/migrations/2026_10_20_000001_add_soroush_rubika_columns_to_bots_table.php` |

هیچ نوع قبلی تغییر نام یا حذف **نشد**؛ `rubika-diagnostic` صرفاً یک endpoint
جدول `webhook_endpoints` و یک route جدید است.

## ۲. قابلیت‌ها (چه چیزی نشان می‌دهد)

- **پیام خصوصی**: Chat ID، User ID (Sender ID)، نام و نام‌خانوادگی، Username،
  Message ID، نوع فرستنده، Time، متن پیام.
- **پیام گروه**: شناسه و عنوان گروه، Sender ID، متن، Message ID.
- **پیام کانال**: شناسه و عنوان کانال، Username، Sender ID (اگر API بدهد)،
  Message ID، نوع رویداد.
- **پیام فورواردشده**: جدا از چت گیرنده — نوع مبدأ (User/Channel/Bot)،
  Chat ID مبدأ، Sender ID اصلی، Message ID اصلی (از دو مکانیزم
  `forwarded_from` یا فیلدهای مستقیم `type_from`/`from_chat_id`/
  `from_sender_id`/`from_message_id`). گزارش صریحاً هشدار می‌دهد:
  «مبدأ فوروارد ≠ چت گیرنده».
- **BotJoined به کانال/گروه**: بررسی صلاحیت‌ها؛ اگر `SendMessages` فعال باشد
  خوش‌آمدگویی فارسی با Channel ID / نوع چت / Username / دسترسی ربات ارسال
  می‌شود؛ اگر غیرفعال باشد **صادقانه** گزارش می‌دهد و اصلاً تلاش نمی‌کند.
- **BotPermissionsChanged**: گزارش کلیدهای صلاحیت (SendMessages/Admin) به مالک.
- **BotRemoved**: پاک‌کردن کلید dedup رویداد BotJoined + گزارش به مالک.
- **رویداد ناشناخته**: گزارش به مالک با برچسب «BLOCKED تا تست زنده».
- **حفاظت در برابر تکرار**: `Cache::add` اتمیک (TTL ۱ ساعت) — آپدیت دوتایی
  (دوبارهای) فقط یک‌بار پردازش می‌شود و خوش‌آمدگویی تکراری نمی‌شود.

## ۳. دستورات

| دستور | کار |
|---|---|
| `/start` | منو و راهنما |
| `/id` | شناسه‌های چت و فرستنده |
| `/chat` | اطلاعات کامل چت از طریق `getChat` |
| `/forward` | جزئیات پیام فورواردشده |
| (هر متن معمولی) | خودکار «گزارش تشخیصی» کامل |

دستورات فقط در **چت خصوصی** یا برای **مالک/ادمین** در گروه/کانال پاسخ داده
می‌شوند؛ پیام عادیِ افراد غریبه **فقط** به مالک گزارش می‌شود (نه در چت).

## ۴. پیش‌نیازها و راه‌اندازی (Setup)

### ۴.۱ مهاجرت‌ها و Seeder

```bash
php artisan migrate
php artisan db:seed --class=RubikaDiagnosticWebhookEndpointSeeder
# یا در دپلوی‌های بعدی، importer خودکار endpoint را ثبت می‌کند:
# WebhookEndpointDefaultImporter (ورودی rubika-diagnostic)
```

### ۴.۲ متغیرهای `.env`

| متغیر | نقش |
|---|---|
| `RUBIKA_BOT_TOKEN` | توکن ربات **مادر** روبیکا (برای ساخت ربات فرزند از طریق Mother) |
| `CHAT_ID_ACCOUNT_1_SABER` … (۶ کلید ادمین) | شناسه‌های ادمین‌ها؛ گزارش‌ها فقط به مالک ربات + ادمین‌ها می‌رود |
| `APP_URL` | دامنهٔ عمومی‌رسیدنی (در webhook URL استفاده می‌شود) |

### ۴.۳ ساخت ربات فرزند از طریق BotFather روبیکا

1. در اپ روبیکا، ربات رسمی **BotFather** (ربات‌ساز روبیکا) را باز کنید و
   یک ربات جدید بسازید؛ **توکن** ربات را کپی کنید.
   - فرمت توکن روبیکا شفاف (opaque) است: رشته‌ای ۸ تا ۲۰۰ کاراکتری از
     `[a-zA-Z0-9_-]` (برخلاف تلگرام که `id:secret` است).
   - اعتبارسنجی: `TokenHelper::isToken($token, 'rubika')`.
2. ⚙️ **تنظیم «دریافت همهٔ پیام‌های گروه/کانال»**: در تنظیمات ربات در
   BotFather روبیکا، گزینهٔ دریافت تمام پیام‌های گروه و کانال را فعال کنید.
   در عمل، برای اینکه ربات پیام‌های گروه/کانال را ببیند باید **ادمین** آن
   چت باشد (معمولاً با صلاحیت SendMessages و بالاتر).
3. در چت خصوصی با **ربات مادر روبیکا**:
   - `/start` بزنید
   - از لیست، شمارهٔ endpoint **ربات تشخیصی روبیکا** را انتخاب کنید
   - توکن ربات جدید را بفرستید
4. Mother به‌صورت خودکار: `getMe` → ثبت/به‌روزرسانی رکورد `bots` (ستون‌های
   `rubika_*`) → ساخت URL webhook → `updateBotEndpoints($url, 'ReceiveUpdate')`
   → ذخیرهٔ `rubika_webhook_is_set=1`.
   - ⚠️ API روبیکا **خوانش (read-back) webhook ندارد**؛ تأیید ثبت فقط از
     طریق HTTP 2xx (کلاینت روی ≥400 خطا می‌دهد) ممکن است. این وضعیت
     `BLOCKED` است و حدس زده نمی‌شود.

### ۴.۴ ساخت ربات را در گروه/کانال ادمین کنید (برای تست رویدادها)

- ربات را به گروه اضافه کنید → `BotJoined` → خوش‌آمدگویی (اگر SendMessages
  فعال باشد) یا گزارش 🚫 (اگر غیرفعال).
- ربات را در کانال با صلاحیت «ارسال پیام» ادمین کنید → `BotJoined` +
  خوش‌آمدگویی با Channel ID.
- صلاحیت‌ها را تغییر دهید → `BotPermissionsChanged` گزارش می‌شود.
- ربات را از چت حذف کنید → `BotRemoved`.

## ۵. روش تست (Test Method)

### ۵.۱ تست بدون webhook (پُلینگ محلی)

قبل از تنظیم webhook، می‌توان آپدیت‌ها را مستقیم گرفت:

```bash
php artisan rubika:diagnostic-poll {bot_id}            # بدون offset (اولین اجرا)
php artisan rubika:diagnostic-poll {bot_id} --limit=10
php artisan rubika:diagnostic-poll {bot_id} --offset=offset_123
php artisan rubika:diagnostic-poll {bot_id} --force    # با وجود webhook فعال (توصیه نمی‌شود)
```

قوانین:
- **تعارض Polling/Webhook**: اگر `rubika_webhook_is_set=1` باشد، دستور
  بدون `--force` **رد** می‌شود (polling و webhook هم‌زمان برای یک توکن
  تعارض دارند).
- **قرارداد offset**: offset قبلی در کش
  (`rubika_diag_poll_offset:{bot_id}`، TTL ۲۴ ساعت) ذخیره می‌شود؛
  `next_offset_id` پاسخ `getUpdates` offset اجرا بعدی است.
  ⚠️ `start_id` که در `aux_data` دیدید **شناسهٔ دکمهٔ کیپد است و offset
  نیست** — هرگز از آن به‌عنوان offset استفاده نکنید.
- اگر `next_offset_id` در پاسخ نبود، offset کش تغییر نمی‌کند (اخطار چاپ می‌شود).

### ۵.۲ تست با webhook (سیناریوهای زنده)

| # | سناریو | انتظار |
|---|---|---|
| 1 | پیام خصوصی عادی | پاسخ «🩺 گزارش تشخیصی روبیکا» با همهٔ شناسه‌ها |
| 2 | `/id` در چت خصوصی | «🆔 شناسه‌ها» |
| 3 | `/chat` در چت خصوصی | «💬 اطلاعات چت (getChat)» |
| 4 | `/forward` روی پیام فورواردشده | «↪️ اطلاعات فورواردشدهٔ این پیام» با مبدأ جدا از گیرنده |
| 5 | `/forward` روی پیام عادی | «این پیام فورواردشده نیست» |
| 6 | پیام گروه از غیرمالک | بدون پاسخ در گروه؛ گزارش فقط به مالک |
| 7 | پیام کانال | بدون پاسخ در کانال؛ گزارش فقط به مالک (با عنوان/Username) |
| 8 | ربات به کانال اضافه شود (با SendMessages) | خوش‌آمدگویی در کانال + «✅» به مالک |
| 9 | ربات به گروه اضافه شود (بدون SendMessages) | بدون ارسال؛ «🚫» + دلیل به مالک |
| 10 | آپدیت تکراری (دوبارها) | فقط یک‌بار پردازش (dedup) |
| 11 | رویداد ناشناخته | «❓ رویداد ناشناخته از روبیکا» + خام رویداد (redacted) |

### ۵.۳ تست‌های خودکار (MOCK_TESTED)

```bash
php artisan test tests/Unit/RubikaUpdateNormalizerTest.php
php artisan test tests/Feature/Modules/BotMother/RubikaDiagnosticBotWebhookTest.php
```

- Unit: ۲۳ تست (نرمال‌ساز: تشخیص آپدیت/پیام/رویداد، فوروارد، صلاحیت‌ها،
  برچسب نوع چت).
- Feature: ۳۲ تست (تمام سناریوهای جدول بالا + پُلینگ + عدم تداخل
  پلتفرم‌های دیگر + آگاه‌سازی مالک).

## ۶. امنیت

- **توکن هرگز لاگ نمی‌شود**: همهٔ رشته‌های خطا/لاگ از
  `TokenHelper::redactTokens()` می‌گذرند (پترن اختصاصی
  `botapi.rubika.ir/v3/<token>/` قبل از پترن‌های عمومی اجرا می‌شود).
- **عدم‌تداخل توکن‌های خارجی**: ربات فقط با ستون `rubika_bot_token`
  پیدا می‌شود؛ توکن تلگرام/بله/سروش روی این route هیچ اثری ندارد (تست‌شده).
- **اطلاعات خصوصی کاربر فقط به مالک/ادمین**: فرستندهٔ غریبه هیچ پاسخی نمی‌گیرد.
- **دسترسی اضافه نمی‌گیرد**: ربات فقط از `getMe`، `getChat`، `sendMessage`،
  `getUpdates`، `updateBotEndpoints` استفاده می‌کند.
- **بدون خوش‌آمدگویی تکراری**: dedup اتمیک با `Cache::add`.

## ۷. لاگ‌ها

- همهٔ آپدیت‌های پردازش‌شده در `bot_logs` با
  `webhook_endpoint_uri='webhook-rubika-diagnostic'` و `type='rubika'` ثبت
  می‌شوند (chat_id های opaque روبیکا که عدد ۱۸ رقمی نباشند، `0` لاگ می‌شوند
  — مقدار واقعی در متن گزارش و لاگ‌های Laravel).
- خطاهای webhook با `Log::error` (redacted) ثبت می‌شوند و همیشه
  `{"ok":true}` به روبیکا برمی‌گردد تا retry طوفانی نشود.

## ۸. وضعیت‌ها (دقیق و بدون حدس)

| قابلیت | وضعیت | شواهد |
|---|---|---|
| ثبت ربات + webhook (`updateBotEndpoints`) | MOCK_TESTED | `BotMotherSoroushRubikaWebhookTest` (Phase 4) |
| پردازش پیام خصوصی/گروه/کانال | MOCK_TESTED | ۳۲ تست Feature این مأموریت |
| فوروارد (دو مکانیزم) | MOCK_TESTED | Feature + Unit |
| BotJoined/BotRemoved/Permissions | MOCK_TESTED | Feature |
| پاسخ‌های API (envelope) | ⛔ BLOCKED | مستندات پاسخ را تعریف نکرده؛ موفقیت = HTTP 2xx + `message_id` (پیام‌ها) |
| خوانش webhook (read-back) | ⛔ BLOCKED | API ندارد |
| `parse_mode` | ⛔ BLOCKED | مستند نشده؛ حذف می‌شود |
| رسانهٔ ورودی (عکس/ویدیو در آپدیت) | ⛔ BLOCKED | ساختار `file` مستند نشده؛ فقط `text` کپی می‌شود |
| تست زنده | ❌ NOT_LIVE_TESTED | نیازمند توکن واقعی + URL عمومی — **در انتظار تأیید کاربر** |

## ۹. مراحل تست زنده (فقط پس از تأیید صریح)

1. `RUBIKA_BOT_TOKEN` (توکن مادر) و ادمین‌ها در `.env` تنظیم شود.
2. `php artisan migrate` + Seeder endpoint اجرا شود.
3. با BotFather روبیکا یک ربات بسازید و از طریق Mother روی روبیکا
   (انتخاب `rubika-diagnostic` + توکن) ثبت کنید.
4. سناریوهای جدول ۵.۲ را به‌ترتیب در چت خصوصی/گروه/کانال انجام دهید.
5. `php artisan rubika:diagnostic-poll {bot_id}` را (قبل از webhook) برای
   مشاهدهٔ خام `getUpdates` اجرا کنید.
6. خروجی‌ها را با `bot_logs` و `storage/logs/laravel.log` (redacted) تطبیق دهید.
7. هر تفاوت با مستندات → مستندات را بر اساس واقعیت API به‌روزرسانی کنید
   (نه برعکس).

## ۱۰. محدودیت‌های شناخته‌شده

1. **`CleanEmptyBots` خطر حذف ربات‌های rubika-only**: معیار ۱ این دستور
   (`app/Console/Commands/CleanEmptyBots.php:46-57`) فقط `telegram_bot_token`
   و `bale_bot_token` را می‌بیند؛ رباتی که فقط `rubika_bot_token` داشته باشد
   «خالی» تلقی و **حذف می‌شود**. تا رفع این باگ، از اجرای
   `clean-empty-bots` خودداری کنید یا ربات‌های روبیکایی را مانیتور کنید.
2. **وایزارد بومی Mother فقط متن**: آپدیت‌های غیر `NewMessage` (مثل
   BotJoined) در **ربات مادر** ack-only هستند (ساختار `update` متفاوت است)؛
   اما در **ربات تشخیصی فرزند** تمام رویدادها پردازش می‌شوند.
3. **رویدادها فقط برای فرزند**: BotJoined و هم‌خانواده فقط روی webhook خود
   ربات تشخیصی فرزند ثبت می‌شود، نه روی مادر.

## ۱۱. پیوست اجباری: ماتریس پوشش روبیکا روی همهٔ انواع ربات BotMother

👉 [`RUBIKA_COVERAGE_MATRIX.md`](RUBIKA_COVERAGE_MATRIX.md)
