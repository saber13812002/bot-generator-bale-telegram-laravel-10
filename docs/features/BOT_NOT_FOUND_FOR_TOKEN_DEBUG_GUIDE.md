# راهنمای خطای `Bot not found for token` و تشخیص ربات از لاگ‌ها

## 📝 توضیحات

این سند یک **راهنمای سریع دیباگ** برای خطای لاگ `Bot not found for token` و همچنین تشخیص این است که **هر لاگ برای کدام ربات است**.  
هدف این است که **انسان** و **هوش مصنوعی** با یک جستجوی ساده (مثلاً عبارت `Bot not found for token` یا نام این فایل) سریعاً این راهنما را پیدا کنند.

## 🎯 اهداف

- توضیح منطق تشخیص `bot_id` از `token` در `LogHelper`
- مستندسازی رفتار زمانی که هیچ رباتی با آن `token` در جدول `bots` پیدا نشود
- نشان دادن چند سناریوی نمونه (QuranBot, Blog Bot, Get Chat ID Bot) برای تشخیص ربات از روی لاگ‌ها

## 📂 مسیر فایل‌ها

### Helpers
- `app/Helpers/LogHelper.php`
  - متد `log()`
  - متد `findBotIdFromToken()` (و منطق جدید تشخیص bot بر اساس لاگ‌های قبلی و webhook_endpoint_uri)

### Models
- `app/Models/Bot.php`
- `app/Models/BotLog.php`

### Controllers
- `app/Http/Controllers/QuranWordController.php` – مثال QuranBot
- `app/Http/Controllers/BlogController.php` – مثال Blog Bot (`webhook-blog`)
- `app/Http/Controllers/BotMotherController.php` – مثال Get Chat ID Bot (`webhook-bot-get-id`)

### Routes
- `routes/api.php`
  - Route: `/api/webhook-blog` → `BlogController@index`
  - Route: `/api/webhook-bot-get-id` → `BotMotherController@getIdMother`

### مستندات مرتبط
- `docs/features/BOT_LOGS_BOT_ID_TRACKING.md`
- `docs/LOGGING-GUIDE.md`
- `docs/BOT_TYPES_GUIDE.md`
- `docs/features/GET_CHAT_ID_BOT.md`

---

## 🔍 منطق تشخیص bot از token در LogHelper

**فایل:** `app/Helpers/LogHelper.php`

خلاصه منطق:

1. ابتدا تلاش می‌شود `bot_id` از خود `request` (پارامتر `bot_id`) خوانده شود.  
2. اگر `bot_id` در request نبود، از متد `findBotIdFromToken()` استفاده می‌شود:
   - استفاده از ترکیب `webhook_endpoint_uri`, `bot_mother_id`, `language`, `type` روی جدول `bot_logs` برای پیدا کردن `bot_id` معتبر (غیر از ۱).
   - در صورت شکست، تلاش برای پیدا کردن ربات از جدول `bots` بر اساس `bot_mother_id`, `type`, `language` و وجود لاگ قبلی.
   - در نهایت اگر هیچ‌چیز پیدا نشود، از `env` برای Bot Mother (توکن اصلی) استفاده می‌کند و اگر همان هم منجر به یافتن ربات نشود:
3. **در صورت عدم موفقیت نهایی، لاگ زیر ثبت می‌شود و `bot_id = 1` (Bot Mother) برمی‌گردد:**

```php
Log::warning('Bot not found for token', [
    'type' => $type,
    'token_prefix' => substr($token, 0, 10) . '...',
    'webhook_endpoint_uri' => $webhookEndpointUri,
    'bot_mother_id' => $botMotherId,
    'language' => $language,
]);
```

اگر این لاگ را دیدید، یعنی:

- توکنی که برای webhook استفاده شده **در جدول `bots` ثبت نشده** است،  
یا
- منطق تشخیص بر اساس `bot_logs` و `webhook_endpoint_uri` نتوانسته ربات مناسبی پیدا کند.

---

## 📊 سناریوهای نمونه از لاگ واقعی

### 1. QuranBot (Telegram) – اجرای موفق `/report`

نمونه لاگ:

```text
🔔 [QuranBot] Webhook received {"origin":"telegram","bot_mother_id":"1","language":"en","bot_id":"18","has_token":true}
🌐 [QuranBot] Locale set {"locale":"en"}
🤖 [QuranBot] Bot instance created {"type":"telegram","has_custom_token":true}
🔍 QuranWordController - Processing request {"chat_id":...,"bot_mother_id":"1","type":"telegram","bot_text":"/report"}
📊 [Command] Generating user report {"chat_id":...,"type":"telegram"}
✅ [QuranBot] Request processed successfully {"chat_id":...,"command_type":"commands","type":"telegram","processing_time_ms":...}
```

**نکات تشخیصی:**

- برچسب `[QuranBot]` در پیام‌های لاگ
- `controller`: کلاس `QuranWordController`
- `type: telegram`
- `bot_id: 18` در کانتکست لاگ (یعنی ربات با شناسه ۱۸ در جدول `bots`)

### 2. Blog Bot (Bale) – خطای `Bot not found for token` + 404 از Blog API

نمونه لاگ:

```text
local.WARNING: Bot not found for token {"type":"bale","token_prefix":"1609462556...","webhook_endpoint_uri":"webhook-blog","bot_mother_id":"1","language":"fa"}
local.ERROR: Blog API ClientException {"url":"https://blog.pardisania.ir/api/v1/artisan","status":404,"message":"Client error: `POST https://blog.pardisania.ir/api/v1/artisan` resulted in a `404 Not Found` response: ..."}
```

**این لاگ دقیقاً چه می‌گوید؟**

- `type: bale` → این یک **ربات بله** است.
- `webhook_endpoint_uri: webhook-blog` → endpoint متناظر در جدول `webhook_endpoints` برابر `"webhook-blog"` است که در `BOT_TYPES_GUIDE.md` به عنوان **Blog Bot** تعریف شده.
- `token_prefix: 1609462556...` → شروع توکنی که برای webhook روی Bale ست شده.
- `Bot not found for token` → **هیچ ردیفی در جدول `bots` با این `bale_bot_token` پیدا نشده است.**
- سپس درخواست به آدرس `https://blog.pardisania.ir/api/v1/artisan` با status `404` برمی‌گردد که یعنی **endpoint در سمت سیستم بلاگ هم درست تنظیم نشده یا وجود ندارد**.

### 3. Get Chat ID Bot (Bale) – چند بار خطای `Bot not found for token`

نمونه لاگ:

```text
local.WARNING: Bot not found for token {"type":"bale","token_prefix":"84584990:R...","webhook_endpoint_uri":"webhook-bot-get-id","bot_mother_id":"1","language":"fa"}
```

**نکات تشخیصی:**

- `webhook_endpoint_uri: webhook-bot-get-id` → این به ربات **Get Chat ID Bot** مربوط است (مستند کامل: `docs/features/GET_CHAT_ID_BOT.md`).
- `type: bale` → باز هم ربات بله.
- توکن با پیشوند `84584990:R...` در جدول `bots` ثبت نشده است، پس LogHelper نتوانسته `bot_id` را پیدا کند و به `1` (Bot Mother) برگشته است.

---

## 🔧 چک‌لیست دیباگ وقتی `Bot not found for token` را می‌بینیم

۱. **وبهوک کدام ربات است را تشخیص بدهید:**
   - از فیلد `webhook_endpoint_uri` در لاگ استفاده کنید (`webhook-blog`, `webhook-bot-get-id`, `webhook-quran-word`, …).
   - اگر لازم بود، به `docs/BOT_TYPES_GUIDE.md` مراجعه کنید و `route` متناظر را ببینید.

۲. **پلتفرم ربات را تشخیص بدهید:**
   - فیلد `type` در لاگ (`telegram` یا `bale`).
   - بر اساس آن، در جدول `bots` یا ستون `telegram_bot_token` را بررسی کنید یا `bale_bot_token` را.

۳. **وجود توکن در جدول `bots` را چک کنید:**
   - توکن واقعی را (با سرچ در تنظیمات webhook یا `.env`) پیدا کنید.
   - در جدول `bots` بگردید:
     - اگر `type = telegram` → ستون `telegram_bot_token`
     - اگر `type = bale` → ستون `bale_bot_token`
   - اگر ردیفی نیست → **باید ربات جدید را بسازید یا توکن را اصلاح کنید.**

۴. **اگر ربات Blog Bot است:**
   - علاوه بر ثبت ربات در `bots`، endpoint سمت Blog را هم چک کنید:
     - `https://blog.pardisania.ir/api/v1/artisan` نباید 404 برگرداند.
   - تنظیمات API Blog (توکن‌ها، routeها) را در پروژه بلاگ اصلاح کنید.

۵. **اگر ربات Get Chat ID Bot است:**
   - مطمئن شوید رباتی با این توکن (مثلاً `84584990:R...`) در جدول `bots` به عنوان ربات Bale تعریف شده.
   - راهنمای کامل: `docs/features/GET_CHAT_ID_BOT.md`

---

## 📝 Logging – نکات مهم برای تشخیص سریع

- همیشه در لاگ‌ها:
  - **`webhook_endpoint_uri`** را برای تشخیص نوع ربات چک کنید.
  - **`bot_mother_id`** را برای تشخیص ربات مادر در نظر بگیرید (معمولاً `1`).
  - **`language`** را برای تشخیص زبان کانتکست استفاده کنید (مثلاً `fa`, `en`).
  - **`type`** را برای تشخیص پلتفرم (`telegram`, `bale`, …) بررسی کنید.
- برای دیباگ‌های پیچیده‌تر مربوط به `bot_id`, سند `BOT_LOGS_BOT_ID_TRACKING.md` را مطالعه کنید.

---

## 📚 مستندات مرتبط

- `docs/features/BOT_LOGS_BOT_ID_TRACKING.md` – منطق کامل ردیابی `bot_id` و `bot_mother_id`
- `docs/LOGGING-GUIDE.md` – راهنمای عمومی لاگینگ در پروژه
- `docs/BOT_TYPES_GUIDE.md` – لیست انواع ربات‌ها و `route` هر کدام
- `docs/features/GET_CHAT_ID_BOT.md` – جزئیات ربات دریافت Chat ID

---

**آخرین بروزرسانی**: 2026-02-27

