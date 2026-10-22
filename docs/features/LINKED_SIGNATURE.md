# 🔗 امضای لینک‌دار (Linked Signature)

وقتی امضا (امضا/کانال‌های مرتبط) به مقصدهای Bale / Eita / Telegram / Soroush / Rubika ارسال می‌شود، لینک کانال به‌صورت **لینک قابل‌کلیک** با نگارش (parse) مخصوص همان پیام‌رسان ساخته می‌شود — چون هر پیام‌رسان سِنکس متفاوتی برای لینک دارد.

## 📐 نگارش لینک به تفکیک پلتفرم

| پلتفرم | سِنکس | parse_mode |
|---|---|---|
| Eita (ایتا) | `<a href="https://...">https://...</a>` | `html` |
| Telegram (تلگرام) | `<a href="https://...">https://...</a>` | `html` |
| Bale (بله) | `[https://...](https://...)` | بدون parse_mode (بله مارک‌داون لینک را بومی رندر می‌کند) |
| Soroush (سروش) | `https://...` (لینک ساده) | بدون parse_mode (کلاينت سروش parse_mode را حذف می‌کند — «BLOCKED») |
| Rubika (روبیکا) | `https://...` (لینک ساده) | بدون parse_mode (کلاينت روبیکا parse_mode را حذف می‌کند — «BLOCKED») |

نکته: برای سروش/روبیکا، چون parse_mode در API آن‌ها مستند نیست، کلاینت‌های ما (`SoroushBotClient`/`RubikaBotClient`) parse_mode را قبل از ارسال حذف می‌کنند («BLOCKED»)؛ بنابراین فقط لینک ساده ارسال می‌شود.

## 🏗️ معماری / نقاط تغییر

1. **ساخت امضا — `buildSignature($botId, $tag, ?string $platform = null): array`**
   در [`app/Services/ChannelPosterBotServiceImpl.php`](../app/Services/ChannelPosterBotServiceImpl.php) (اینترفیس: [`app/Interfaces/Services/ChannelPosterBotService.php`](../app/Interfaces/Services/ChannelPosterBotService.php))
   - بدون `$platform` → رفتار قدیمی: همهٔ کانال‌های آن تگ با لینک، ترتیب تصادفی، بدون parse_mode.
   - با `$platform` → **فقط لینک خود همان مقصد** (مثلاً «در بله:» فقط لینک کانال بله)، با نگارش مخصوص پلتفرم.
   - بازگشت: `['text' => "\n\nدر بله:\n...", 'parse_mode' => null|'html']`

2. **ساختار لینک — `ProductLinkMessageHelper::signatureLink(string $url, ?string $platformSlug = null)`**
   در [`app/Helpers/ProductLinkMessageHelper.php`](../app/Helpers/ProductLinkMessageHelper.php)
   - `eitaa/telegram` → `<a href="url">url</a>` (با htmlspecialchars)
   - `bale` → `[url](url)`
   - سایر → `url`
   - `parseModeForPlatform()` هم از همین helper می‌آید (`html` برای ایتا/تلگرام، بقیه `null`).

3. **append امضا به‌جای هر مقصد — در حلقهٔ publish**
   - [`app/Services/ChannelPosterQueuePublishService.php`](../app/Services/ChannelPosterQueuePublishService.php): امضا **داخل حلقهٔ مقصدها** برای هر مقصد جدا ساخته می‌شود (قبلاً یک‌بار قبل از حلقه اضافه می‌شد و همهٔ مقصدها همهٔ کانال‌ها را می‌دیدند).
   - [`app/Http/Controllers/ChannelPosterBotController.php`](../app/Http/Controllers/ChannelPosterBotController.php) → `publishPendingToTag()`: همین‌طور در مسیر immediate publish.
   - ⚠️ **باگِ دوتایی امضا در enqueue رفع شد:** قبلاً کنترلر متن را با امضا ذخیره می‌کرد **و** `signature_enabled=true` می‌گذاشت؛ بعد سرویس queue دوباره امضا اضافه می‌کرد (امضای دو برابر). حالا enqueue متن تمیز ذخیره می‌کند و سرویس queue یک‌بار امضا را اضافه می‌کند.

4. **عبور parse_mode از انترفیس publish**
   - [`app/Interfaces/Services/ChannelPosterPublisher.php`](../app/Interfaces/Services/ChannelPosterPublisher.php): `publish(..., ?string $parseMode = null)` (پارامتر ۱۰ام).
   - [`app/Services/TelegramChannelPosterPublisher.php`](../app/Services/TelegramChannelPosterPublisher.php):
     - مسیر ایتا (text): `BotHelper::sendMessageEitaaSupport(..., $parseMode)`.
     - مسیر telegram/bale (text): `$content['parse_mode'] = $parseMode` فقط وقتی parse_mode دارد.
   - [`app/Services/NativeChannelPosterPublisher.php`](../app/Services/NativeChannelPosterPublisher.php): پاس‌through به delegate.
   - parse_mode فقط وقتی امضای لینک‌دار واقعاً اضافه شده باشد تنظیم می‌شود (وگرنه متن کاربری ممکن است `<` یا `&` داشته باشد).

5. **retry امن بدون parse_mode**
   در `TelegramChannelPosterPublisher::publish` اگر ارسال با parse_mode شکست بخورد (مثلاً متن کاربر HTML نامعتبر دارد)، **بازتلاش بدون parse_mode** انجام می‌شود — یعنی محتوا حتماً می‌رسد، فقط لینک ممکن است plain شود. لاگ: `[ChannelPoster][publish] parse_mode retry`.

## 🧪 تست زنده

- **ایتا (انجام‌شده ✅):** `php artisan app:test-signature --platform=eitaa`
  با `TEST_PREVIEW_PLATFORM=eitaa` / `TEST_PREVIEW_BOT_TOKEN=bot1967:...` / `TEST_PREVIEW_CHAT_ID=8419225` → پیام با `<a href>` و `parse_mode=html` ارسال شد (message_id: 177641559).
- **تلگرام (انجام‌شده ✅):** `php artisan app:test-signature --platform=telegram --token=<QURAN token> --chat-id=151370482`
  (توکن ربات قرآن `QURAN_HEFZ_BOT_TOKEN_TELEGRAM`) → پیام با `<a href>` و `parse_mode=html` ارسال شد (message_id: 169517).
- **بله (انجام‌شده ✅):** `php artisan app:test-signature --platform=bale --token=<BotMother token> --chat-id=485750575`
  (توکن ربات مادر `BOT_MOTHER_TOKEN_BALE`) → پیام با مارک‌داون `[url](url)` و بدون parse_mode ارسال شد (message_id: 1182).

دستور تست در [`app/Console/Commands/TestSignature.php`](../app/Console/Commands/TestSignature.php) — دقیقاً همان شکل production را می‌سازد (`label + ":\n" + signatureLink(...)`) و از متدهای production (`TestPostPreviewService::sendPreview`) برای ارسال استفاده می‌کند. گزینه‌ها: `--platform=` `--token=` `--chat-id=` `--link=` `--base=`.

## 🧩 تست اختصاصی امضای لینک‌دار (دو روش مستقل)

بر اساس مستندات هر پیام‌رسان، «لینک داخل متن» و «دکمه لینک‌دار» **دو قابلیت مستقل** هستند و تا تأیید رسمی هر پلتفرم، به‌صورت مستقل برنامه‌ریزی و گزارش می‌شوند.

### روش‌ها

| روش | توضیح | ساختار |
|---|---|---|
| ۱ — Inline Link (لینک داخل متن) | خود آدرس در متن لینک می‌شود | بسته به پلتفرم: HTML `<a href>`, Markdown `[url](url)` یا لینک ساده |
| ۲ — URL Button (دکمه لینک‌دار) | یک دکمه زیر پیام با label و آدرس | `reply_markup` → `inline_keyboard` → `{"text": label, "url": url}` |

### جدول پشتیبانی به تفکیک پلتفرم (مطابق مستندات)

| پلتفرم | روش ۱ — لینک داخل متن | روش ۲ — دکمه URL |
|---|---|---|
| Telegram | ✅ HTML `<a href>` + `parse_mode=html` — **رسمی** (core.telegram.org) | ✅ InlineKeyboardButton.url — **رسمی** |
| Eita | ⚠️ HTML سازگار با API تلگرام — **تست زنده‌شده** (مستندات رسمی مستقل ندارد) | ❌ کلاینت فعلی (`call_eitaa_api`) reply_markup ارسال نمی‌کند |
| Bale | ⚠️ Markdown `[url](url)` — دیده‌شده در کتابخانه‌ها، **بدون سند رسمی** (تست زنده‌شده) | ⚠️ SDK بله `buildInlineKeyboardButton` را دارد — **بدون تأیید رسمی** |
| Soroush | ⛔ parse_mode مستند نیست؛ کلاینت حذف می‌کند → لینک ساده (خودرسان) | ⛔ inline keyboard مستند نیست |
| Rubika | ⛔ parse_mode مستند نیست؛ کلاینت حذف می‌کند → لینک ساده (خودرسان) | ⛔ inline keyboard مستند نیست |

### پیاده‌سازی مستقل (هر پیام‌رسان متد جداگانه)

در [`app/Helpers/ProductLinkMessageHelper.php`](../app/Helpers/ProductLinkMessageHelper.php) — هر پیام‌رسان متد مستقل خود را دارد (نه یک `match` مشترک):

- `telegramSignatureLink()` — HTML (مستندات رسمی)
- `eitaaSignatureLink()` — HTML (سازگاری تلگرام، تست زنده)
- `baleSignatureLink()` — Markdown (کتابخانه‌ها)
- `soroushSignatureLink()` — لینک ساده (parse_mode BLOCKED)
- `rubikaSignatureLink()` — لینک ساده (parse_mode BLOCKED)
- `signatureButtonSupported()` + `signatureButtonMarkup()` — ماتریس پشتیبانی و ساخت markup دکمه

متد `signatureLink()` فقط dispatcher است؛ منطق هر پلتفرم جدا و مستقل جادامانده است.

### دستور اختصاصی (همیشه قابل اجرا)

```bash
# پیش‌فرض‌ها از .env (TEST_PREVIEW_PLATFORM / TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID)
php artisan app:test-signature-link

# با پارامترهای صریح
php artisan app:test-signature-link \
    --platform=telegram \
    --token=<BOT_TOKEN> \
    --chat-id=<CHAT_ID> \
    --link=https://t.me/s/yourchannel \
    --label="در تلگرام" \
    --base="🔗 تست اختصاصی امضای لینک‌دار"
```

- `--platform=` : `eitaa|bale|telegram` (پیش‌فرض از `.env`)
- `--token=` : توکن ربات (پیش‌فرض `TEST_PREVIEW_BOT_TOKEN`)
- `--chat-id=` : chat id مقصد (پیش‌فرض `TEST_PREVIEW_CHAT_ID`)
- `--link=` : آدرس لینک کانال (پیش‌فرض `https://example.com`)
- `--label=` : متن لیبل/دکمه (پیش‌فرض برچسب پلتفرم از `lang/fa/bot.php`)
- `--base=` : متن پایه بالای لینک

پیام ارسال‌شده **هر دو روش** را یک‌جا نشان می‌دهد (خط لینک داخل متن + دکمه URL زیر پیام در پلتفرم‌های پشتیبانی‌شده) و خروجی، وضعیت هر دو روش را جداگانه چاپ می‌کند. ارسال از همان مسیرهای production انجام می‌شود (ایتا: `BotHelper::sendMessageEitaaSupport`؛ تلگرام/بله: SDK `Telegram`) + بازتلاش امن بدون parse_mode در صورت شکست.

- سرویس: [`app/Services/TestPostPreviewService.php`](../app/Services/TestPostPreviewService.php) → `sendSignatureLinkPreview()`
- دستور: [`app/Console/Commands/TestSignatureLink.php`](../app/Console/Commands/TestSignatureLink.php)

### نتایج تست زنده (دستور اختصاصی — انجام‌شده ✅)

| پلتفرم | chat | message_id | روش ۱ (لینک داخل متن) | روش ۲ (دکمه URL) |
|---|---|---|---|---|
| Telegram | 151370482 (ربات قرآن) | 169519 | ✅ | ✅ دکمه ارسال شد |
| Bale | 485750575 (ربات مادر) | 1184 | ✅ | ✅ دکمه ارسال شد (رندر در کلاینت بله باید چشمی تأیید شود) |
| Eita | 8419225 | 177667926 | ✅ | — کلاینت ایتا reply_markup ندارد |

⚠️ برای **بله** رندر واقعی دکمه/لینک را باید یک‌بار چشمی در اپلیکیشن بررسی کنید (سند رسمی ندارد). برای **تلگرام** هر دو روش رسمی و تأییدشده‌اند.

## ✅ وضعیت تست خودکار

- `php artisan test --filter="ChannelPosterBotServiceTest|ChannelPosterQueueSendTest|ChannelPosterBotWebhookTest|ChannelPosterOriginWebhookTest|ChannelPosterMediaPublishTest|PostOverdueWatchdogTest"` → **69 passed (344 assertions)**.
- تمام فایل‌های تغییرکرده `php -l` clean.
