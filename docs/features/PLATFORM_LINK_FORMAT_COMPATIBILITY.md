# سازگاری فرمت لینک در پلتفرم‌ها — Platform Link Format Compatibility

> تاریخ: 2026-10-25 — شاخه `15esfand1404`
> این سند بخشی از «الحاقیهٔ دوم مأموریت» است: استانداردسازی فرمت لینک در پنج پیام‌رسان
> (Telegram / Bale / Eitaa / Soroush / Rubika) برای امضای پیشرفتهٔ Channel Poster.
>
> سندهای مرتبط: [`LINKED_SIGNATURE.md`](LINKED_SIGNATURE.md) · [`SIGNATURE_GAP_ANALYSIS.md`](SIGNATURE_GAP_ANALYSIS.md) · [`platform-api-research.md`](platform-api-research.md) · [`messenger-platform-capability-matrix.md`](messenger-platform-capability-matrix.md) · [`CHANNEL_POSTER_BOT.md`](CHANNEL_POSTER_BOT.md)

---

## ۱. هدف و دامنه

امضای پیشرفتهٔ Channel Poster (فاز جدید `/sign`) باید لینک کانال را **در زمان انتشار** و به‌صورت
**سازگار با هر پلتفرم** رندر کند. این سند مشخص می‌کند:

- هر پنج پلتفرم کدام فرمت لینک/متن را می‌پذیرند (پارامتر دقیق API)؛
- هر ادعا از چه منبعی آمده است (رسمی / جامعه / فقط mock این پروژه)؛
- چه چیزی واقعاً روی API زنده دیده شده و چه چیزی **رندر واقعی در کلاینت** دارد.

**قاعدهٔ طلایی:** «صرفاً موفق بودن پاسخ API را معادل نمایش صحیح لینک نگیرید.»
سه محور جدا داریم و هر کدام وضعیت مستقل دارد:

1. **پارامتر در مستندات** — آیا API اصلاً آن پارامتر/فرمت را تعریف می‌کند؟
2. **پذیرش زنده (Live)** — آیا API زنده payload را پذیرفته و `message_id` برگردانده؟
3. **رندر کلاینت (Client Render)** — آیا کاربر در اپلیکیشن پیام را با لینک قابل‌کلیک/فرمت‌شده دیده؟

موفقیت محور ۲ به‌تنهایی گواهی محور ۳ نیست؛ محور ۳ فقط با مشاهدهٔ بصری (یا مستندات رسمی صریح)
ثبت می‌شود.

---

## ۲. تعریف وضعیت‌ها (Enum)

| وضعیت | معنا |
|---|---|
| `OFFICIAL_CONFIRMED` | در مستندات رسمی پیام‌رسان تأیید شده (لینک منبع موجود) |
| `COMMUNITY_DOCUMENTED` | فقط در مستندات جامعه/SDK غیررسمی (نمونه‌های GitHub و مشابه) |
| `MOCK_TESTED` | فقط در تست mock این پروژه (Http::fake / Fake publisher) — هیچ پاسخ زنده ندیده |
| `LIVE_API_ACCEPTED` | API زنده payload را پذیرفته و `message_id` برگردانده (≠ رندر صحیح کلاینت) |
| `CLIENT_RENDER_VERIFIED` | رندر نهایی در کلاینت به‌صورت بصری/رسمی تأیید شده |
| `UNVERIFIED` | ممکن است باشد اما هیچ منبع معتبری ندارد |
| `UNSUPPORTED` | مستنداً یا عملیاً پشتیبانی نمی‌شود (فرستادن آن بی‌فایده یا آزاردهنده است) |
| `OFFICIAL_DOC_NOT_FOUND` | در مستندات رسمی **نیافت** — نه تأیید و نه رد (برخی ادعاها فقط از طریق جامعه/تجربه) |

---

## ۳. منابع (رسمی در برابر جامعه)

| پلتفرم | منبع رسمی | آدرس | وضعیت سند رسمی |
|---|---|---|---|
| Telegram | مستندات رسمی Bot API | `https://core.telegram.org/bots/api` (بخش‌های Formatting, `sendMessage`, `InlineKeyboardButton`, caption limit) | ✅ رسمی و کامل |
| Bale | **سند رسمی مستقل نیافت** — فقط مستندات جامعه/SDK | `https://balepy.github.io` و نمونه‌های SDK در GitHub | ⛔ `OFFICIAL_DOC_NOT_FOUND` |
| Eitaa | **سند رسمی مستقل نیافت** — سازگاری با API تلگرام از گزارش‌های توسعه‌دهنده | — | ⛔ `OFFICIAL_DOC_NOT_FOUND` |
| Soroush Plus | سند رسمی Bot Platform (دریافت‌شده 2026-10-19) | `https://soroushplus.com/p/documents/bot-platform` (آرتیفکت محلی: `soroush_doc.txt`) | ✅ رسمی (بخش `sendMessage` §1.3) |
| Rubika | سند رسمی Bot API v3 (دریافت‌شده 2026-10-19) | `https://rubika.ir/botapi/methods` + `/models` + `/groupchannel` (آرتیفکت محلی: [`rubika/sources/rubika_methods.html`](rubika/sources/rubika_methods.html)؛ نمونه‌های جامعه: [`rubika/sources/rubika_community.md`](rubika/sources/rubika_community.md)) | ✅ رسمی، **اما** `parse_mode`/Markdown/entities در آن نیست |

**منابع جامعه:**

- Rubika: `AliRn76/rubika-bot` (GitHub) — نمونه‌های `inline_keypad` و فرمت‌های فرستنده.
- Bale: `balepy` (SDK جامعه) — نمونه‌های Markdown و `buildInlineKeyboardButton`.

**یادداشت صریح `OFFICIAL_DOC_NOT_FOUND` برای Rubika:** سند رسمی `botapi.rubika.ir/v3` پارامترهای
`parse_mode`، Markdown، MarkdownV2 و entities را **هیچ‌جای** صفحهٔ `sendMessage` تعریف نکرده است
(فقط `chat_id`, `text`, `chat_keypad_type`, `chat_keypad`, `inline_keypad`, `disable_notification`,
`reply_to_message_id`). این یعنی «فرمت‌دهی متن در روبیکا» نه تأیید و نه رد رسمی دارد — نه می‌توان
آن را `UNSUPPORTED` نامید (شاید کلاینت کاراکترهای خاص را بپرساند) و نه `OFFICIAL_CONFIRMED`.

منابع داخلی: [`platform-api-research.md`](platform-api-research.md) (تحلیل §1 Soroush و §2 Rubika) و
[`LINKED_SIGNATURE.md`](LINKED_SIGNATURE.md) (سوابق تست زنده).

---

## ۴. خلاصهٔ API هر پلتفرم

| پلتفرم | Endpoint ارسال | پارامترهای فرمت‌دهی (مستند) | `reply_markup` | حد متن | حد کپشن |
|---|---|---|---|---|---|
| Telegram | `POST https://api.telegram.org/bot{token}/sendMessage` | `parse_mode` ∈ {html, MarkdownV2, Markdown} | ✅ `inline_keyboard[].url` | 4096 | 1024 |
| Bale | `POST https://tapi.bale.ai/bot{token}/send_message` (SDK) | ❌ `OFFICIAL_DOC_NOT_FOUND` | ⚠️ فقط جامعه (SDK) | مستند نیست | مستند نیست |
| Eitaa | `POST https://eitaayar.ir/api/{token}/sendMessage` | ⚠️ سازگاری تلگرام (`parse_mode=html` تست زنده) | ⛔ کلاینت ایتا نمی‌فرستد | (فرض: 4096) | (فرض: 1024) |
| Soroush | `POST https://api.splus.ir/bot{token}/sendMessage` | ✅ رسمی: `parse_mode` (MarkdownV2/HTML/legacy Markdown) + `reply_markup` — **live-unverified** | ✅ رسمی — live-unverified | 1–4096 (رسمی) | مستند نیست |
| Rubika | `POST https://botapi.rubika.ir/v3/{token}/sendMessage` (JSON) | ❌ `OFFICIAL_DOC_NOT_FOUND` — فقط `text` | ⚠️ `inline_keypad` رسمی است ولی فرمت دکمهٔ url مستند نیست | مستند نیست | مستند نیست |

---

## ۵. ماتریس قابلیت‌ها (۱۱ × ۵)

هر سلول: `STATUS — یادداشت کوتاه`. جزئیات کامل (پارامتر دقیق + نمونه payload) در بخش ۶.

| # | قابلیت | Telegram | Bale | Eitaa | Soroush | Rubika |
|---|---|---|---|---|---|---|
| 1 | **متن ساده** | `OFFICIAL_CONFIRMED` + `MOCK_TESTED` + `LIVE_API_ACCEPTED` | `COMMUNITY_DOCUMENTED` + `MOCK_TESTED` + `LIVE_API_ACCEPTED` (1184) | `MOCK_TESTED` + `LIVE_API_ACCEPTED` (177667926) | `OFFICIAL_CONFIRMED` + `MOCK_TESTED` (live ندارد) | `OFFICIAL_CONFIRMED` + `MOCK_TESTED` + `LIVE_API_ACCEPTED` (call واقعی، 2xx) |
| 2 | **لینک خام** (URL عاری در متن) | `OFFICIAL_CONFIRMED` — کلاینت خودکار URL را لینک‌دار می‌کند | `UNVERIFIED` — رفتار کلاینت مستند نیست | `UNVERIFIED` | `UNVERIFIED` — رفتار کلاینت مستند نیست | `UNVERIFIED` — رفتار کلاینت مستند نیست |
| 3 | **لینک inline با برچسب** (label روی URL) | `OFFICIAL_CONFIRMED` + `LIVE_API_ACCEPTED` (169519) + `CLIENT_RENDER_VERIFIED` | `LIVE_API_ACCEPTED` (1184) — رندر کلاینت **نمی‌دانیم** ⚠️ | `LIVE_API_ACCEPTED` (177667926) + `CLIENT_RENDER_VERIFIED` (سند تست زنده ✅) | `UNVERIFIED` — سند فرمت label-on-URL نشان نمی‌دهد؛ ما plain می‌فرستیم | `OFFICIAL_DOC_NOT_FOUND` — کلاینت را خودکار لینک‌دار می‌کند؟ `UNVERIFIED` |
| 4 | **HTML** `<a href>` + `parse_mode=html` | `OFFICIAL_CONFIRMED` + `LIVE_API_ACCEPTED` + `CLIENT_RENDER_VERIFIED` | `UNSUPPORTED` — `parse_mode` در API بله نیست | `LIVE_API_ACCEPTED` — سند رسمی مستقل ندارد (`OFFICIAL_DOC_NOT_FOUND` در مورد سند) | `OFFICIAL_CONFIRMED` (سند رسمی §1.3) ولی **live-unverified**؛ کلاینت پروژه نمی‌فرستد | `OFFICIAL_DOC_NOT_FOUND` — کلاینت نمی‌فرستد |
| 5 | **Markdown** `[label](url)` / legacy | `OFFICIAL_CONFIRMED` (legacy Markdown) | `COMMUNITY_DOCUMENTED` + `LIVE_API_ACCEPTED` (1184) — رندر کلاینت **نمی‌دانیم** ⚠️ | `UNVERIFIED` — سازگاری تلگرام محتمل، تست‌نشده | `OFFICIAL_CONFIRMED` (legacy Markdown در سند) — live-unverified | `OFFICIAL_DOC_NOT_FOUND` |
| 6 | **MarkdownV2** | `OFFICIAL_CONFIRMED` (escape `\` اجباری) | `OFFICIAL_DOC_NOT_FOUND` → در عمل `UNSUPPORTED` | `UNVERIFIED` | `OFFICIAL_CONFIRMED` (سند رسمی) — live-unverified، استفاده نمی‌کنیم | `OFFICIAL_DOC_NOT_FOUND` |
| 7 | **Entities** (MessageEntity) | `OFFICIAL_CONFIRMED` + `MOCK_TESTED` | `OFFICIAL_DOC_NOT_FOUND` | `UNVERIFIED` | `OFFICIAL_DOC_NOT_FOUND` | `OFFICIAL_DOC_NOT_FOUND` |
| 8 | **دکمهٔ URL** (inline keyboard url) | `OFFICIAL_CONFIRMED` + `LIVE_API_ACCEPTED` (169519) + `CLIENT_RENDER_VERIFIED` | `COMMUNITY_DOCUMENTED` + `LIVE_API_ACCEPTED` (1184 «دکمه ارسال شد») — رندر کلاینت **نمی‌دانیم** ⚠️ | `UNSUPPORTED` — کلاینت ایتا `reply_markup` نمی‌فرستد | `OFFICIAL_CONFIRMED` (پارامتر `reply_markup` در سند رسمی) — live-unverified، پروژه استفاده نمی‌کند | `OFFICIAL_DOC_NOT_FOUND` — `inline_keypad` رسمی است ولی دکمهٔ url در models مستند نیست |
| 9 | **لینک در کپشن رسانه** | `OFFICIAL_CONFIRMED` (caption ≤1024 + parse_mode) + `LIVE_API_ACCEPTED` | `UNVERIFIED` — کپشن بله بدون parse_mode | `LIVE_API_ACCEPTED` — حد 1024 فرض از تلگرام | `OFFICIAL_CONFIRMED` (پارامترهای فرمت در سند) — حد کپشن مستند نیست | `UNVERIFIED` — `sendFile` با `text`، حد مستند نیست |
| 10 | **فارسی/انگلیسی مخلوط** | `OFFICIAL_CONFIRMED` (UTF-8) + `MOCK_TESTED` + `LIVE_API_ACCEPTED` (لیبل فارسی «در بله»/«در تلگرام» در تست زنده) | `MOCK_TESTED` + `LIVE_API_ACCEPTED` (متن فارسی در 1184) | `MOCK_TESTED` + `LIVE_API_ACCEPTED` (متن فارسی در 177667926) | `MOCK_TESTED` — live ندارد | `MOCK_TESTED` + `LIVE_API_ACCEPTED` (متن فارسی در call واقعی) |
| 11 | **URL با query / پرانتز / کاراکتر خاص** (مثلاً `?ref=1&x=(p)`) | `OFFICIAL_CONFIRMED` (قواعد escape در docs) + `MOCK_TESTED` (تست escape injection) | `UNVERIFIED` — پرانتز در URL داخل Markdown ریسک است؛ فقط mock | `MOCK_TESTED` — escape HTML روی URL | `MOCK_TESTED` (plain) | `MOCK_TESTED` (plain) |

---

## ۶. سوابق تفصیلی هر پلتفرم (پارامتر دقیق + نمونه payload)

### 6.1 Telegram

- **پارامترهای دقیق:** `text` (≤4096)، `parse_mode` ∈ {`html`, `MarkdownV2`, `Markdown`}،
  `reply_markup.inline_keyboard` (آرایهٔ آرایه از `{"text","url"}`)، `caption` (≤1024) + همان `parse_mode`.
- **منبع:** `https://core.telegram.org/bots/api` — `sendMessage`، بخش «Formatting Messages»
  (HTML/MarkdownV2/legacy Markdown + قواعد escape)، `InlineKeyboardButton` (فیلد `url`).
- **نمونه payload معتبر (همان‌طور که پروژه می‌فرستد):**

```json
{
  "chat_id": "-1001234567890",
  "text": "متن اصلی\nدر تلگرام:\n<a href=\"https://t.me/mychannel?ref=1&x=(p)\">https://t.me/mychannel</a>",
  "parse_mode": "html",
  "reply_markup": {
    "inline_keyboard": [ [ { "text": "در تلگرام", "url": "https://t.me/mychannel" } ] ]
  }
}
```

- **قواعد escape (رسمی):** در HTML: `&` → `&`، `<` → `<`، `>` → `>` (پروژه با
  `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')` پیاده می‌کند — [`SignatureRenderer::escapeForPlatform()`](../../app/Services/ChannelPoster/SignatureRenderer.php)).
  در MarkdownV2 کاراکترهای `_ * [ ] ( ) ~ \` > # + - = | { } . !` اسکیپ می‌شوند (استفاده نمی‌کنیم).
- **وضعیت‌ها:** html `OFFICIAL_CONFIRMED` + `LIVE_API_ACCEPTED` (169519) + `CLIENT_RENDER_VERIFIED`؛
  Markdown/MarkdownV2 `OFFICIAL_CONFIRMED` (mock فقط)؛ دکمه url `OFFICIAL_CONFIRMED` + `LIVE_API_ACCEPTED`
  (169519 «دکمه ارسال شد») + `CLIENT_RENDER_VERIFIED`؛ entities `OFFICIAL_CONFIRMED` (mock).

### 6.2 Bale

- **پارامترهای دقیق:** `chat_id`، `text` — از طریق SDK vendor
  (`new Telegram($token, 'bale')` → `https://tapi.bale.ai/bot{token}/send_message`).
  **`parse_mode` در هیچ سند رسمی/جامعه‌ای برای بله نیافت** → `OFFICIAL_DOC_NOT_FOUND`؛ کلاینت پروژه
  نمی‌فرستد.
- **منبع جامعه:** `balepy` (SDK جامعه) — نمونه‌های Markdown `[متن](url)` و
  `buildInlineKeyboardButton`.
- **نمونه payload معتبر (همان‌طور که پروژه می‌فرستد):**

```json
{
  "chat_id": "485750575",
  "text": "متن اصلی\nدر بله:\n[https://ble.ir/mychannel](https://ble.ir/mychannel)"
}
```

  (برای label امن از نظر Markdown: `کانال ما:\n[https://ble.ir/mychannel](https://ble.ir/mychannel)`
  — اگر label شامل `[\]()#*_~` `` ` `` یا `\` باشد، رندرر به plain برمی‌گردد —
  [`SignatureRenderer::markdownSafeLabel()`](../../app/Services/ChannelPoster/SignatureRenderer.php)).
- **دکمه (جامعه):** `inline_keyboard` مطابق SDK جامعه؛ نمونه payload زنده در تست 1184 فرستاده شد.
- **وضعیت‌ها:** Markdown `COMMUNITY_DOCUMENTED` + `LIVE_API_ACCEPTED` (1184) — **رندر کلاینت ⚠️
  بررسی چشمی نشده** (صراحتاً در LINKED_SIGNATURE.md: «رندر در کلاینت بله باید چشمی تأیید شود»);
  دکمه url `COMMUNITY_DOCUMENTED` + `LIVE_API_ACCEPTED` (1184 «دکمه ارسال شد») — رندر کلاینت ⚠️؛
  parse_mode_html `UNSUPPORTED`/`OFFICIAL_DOC_NOT_FOUND`.

### 6.3 Eitaa

- **پارامترهای دقیق:** سازگار با API تلگرام (از طریق `BotHelper` — raw HTTP روی
  `https://eitaayar.ir/api/{token}/sendMessage`). `parse_mode=html` **در production تست زنده شده**.
  سند رسمی مستقل **نیست** → خودِ سند `OFFICIAL_DOC_NOT_FOUND` است.
- **`reply_markup`: کلاینت ایتا نمی‌فرستد** (سند تست زنده) → در عمل `UNSUPPORTED`؛ پروژه
  برای ایتا دکمه نمی‌فرستد (`SignatureCapability::supportsUrlButton()` = false).
- **نمونه payload معتبر:**

```json
{
  "chat_id": "8419225",
  "text": "متن اصلی\nدر ایتا:\n<a href=\"https://eitaa.com/mychannel\">https://eitaa.com/mychannel</a>",
  "parse_mode": "html"
}
```

- **وضعیت‌ها:** html `LIVE_API_ACCEPTED` (177667926) + `CLIENT_RENDER_VERIFIED` (سند ✅ تست زنده)؛
  Markdown/entities `UNVERIFIED` (سازگاری تلگرام محتمل، تست‌نشده)؛ دکمه `UNSUPPORTED`؛
  حد کپشن 1024 **فرض** از تلگرام است.

### 6.4 Soroush Plus

- **پارامترهای دقیق (از سند رسمی §1.3):** `chat_id` (عدد یا `@username`)، `text` (1–4096 کاراکتر
  **بعد از** parse)، `parse_mode` ∈ {MarkdownV2, HTML, legacy Markdown}، `reply_markup`،
  `reply_to_message_id`. Base: `https://api.splus.ir/bot{token}/sendMessage`.
- **منبع:** `https://soroushplus.com/p/documents/bot-platform` (دریافت 2026-10-19 —
  `platform-api-research.md` §1).
- **اما (مهم):** تست زنده انجام **نشده** (بدون مجوز/توکن واقعی). کلاینت پروژه
  ([`SoroushBotClient`](../../app/Services/SoroushBotClient.php)) به‌صورت محافظه‌کار `parse_mode` را
  **نمی‌فرستد**؛ امضا روی سروش = plain label + URL.
- **نمونه payload مطابق سند (در پروژه استفاده نمی‌شود):**

```json
{
  "chat_id": "@mychannel",
  "text": "متن اصلی\nدر سروش:\n<a href=\"https://soroush.chat/mychannel\">https://soroush.chat/mychannel</a>",
  "parse_mode": "html"
}
```

- **وضعیت‌ها:** parse_mode_html/Markdown/MarkdownV2 `OFFICIAL_CONFIRMED` (فقط سند — live-unverified)؛
  inline label `UNVERIFIED` (سند فرمت label-on-URL نشان نمی‌دهد؛ کلاینت معمولاً URL ساده را
  خودکار لینک‌دار می‌کند)؛ دکمه `OFFICIAL_CONFIRMED` (پارامتر) ولی `UNVERIFIED` (live)؛
  entities `OFFICIAL_DOC_NOT_FOUND`.

### 6.5 Rubika

- **پارامترهای دقیق (از سند رسمی v3):** فقط این‌ها: `chat_id` (string)، `text` (string)،
  `chat_keypad_type` (None/New/Remove)، `chat_keypad` (Keypad)، `inline_keypad` (Keypad)،
  `disable_notification` (bool)، `reply_to_message_id` (string). خروجی: `message_id` (string).
  Base: `POST https://botapi.rubika.ir/v3/{token}/sendMessage` با `Content-Type: application/json`.
- **`parse_mode` / Markdown / entities: در سند رسمی نیافت → `OFFICIAL_DOC_NOT_FOUND`** —
  کلاینت پروژه ([`RubikaBotClient`](../../app/Services/RubikaBotClient.php)) هیچ‌کدام را نمی‌فرستد.
- **`inline_keypad`: رسمی است** (مدل Keypad = `rows` از دکمه‌های `{id, type, button_text}`)
  ولی «نوع دکمهٔ url» در صفحهٔ models مستند نیست → دکمهٔ url = `OFFICIAL_DOC_NOT_FOUND`/`UNVERIFIED`.
  (نمونه‌های جامعه `AliRn76/rubika-bot` نوع url را نشان می‌دهند → حداکثر `COMMUNITY_DOCUMENTED`،
  ولی ما پیاده نمی‌کنیم.)
- **نمونه payload معتبر (همان‌طور که پروژه می‌فرستد):**

```json
{
  "chat_id": "@mychannel",
  "text": "متن اصلی\nدر روبیکا:\nhttps://rubika.ir/mychannel"
}
```

- **وضعیت‌ها:** متن ساده `OFFICIAL_CONFIRMED` + `MOCK_TESTED` + `LIVE_API_ACCEPTED`
  (call واقعی در production؛ پاسخ 2xx؛ `message_id` بعد از فیکس alias-tolerance استخراج شد —
  جزئیات در [`SIGNATURE_GAP_ANALYSIS.md`](SIGNATURE_GAP_ANALYSIS.md) و بخش عیب‌یابی روبیکا)؛
  همهٔ فرمت‌دهی‌ها `OFFICIAL_DOC_NOT_FOUND`؛ entities `OFFICIAL_DOC_NOT_FOUND`.
- **یادداشت:** envelope خطا در سند رسمی نیست (`OFFICIAL_DOC_NOT_FOUND`) — کلاینت با
  `diagnose()` + `correlation_id` ایمن‌سازی شده است.

---

## ۷. وضعیت رندر واقعی در کلاینت (جدول جدا از API)

| پلتفرم | قابلیت | API | رندر کلاینت | شواهد |
|---|---|---|---|---|
| Telegram | `<a href>` + html | `LIVE_API_ACCEPTED` (169519) | ✅ `CLIENT_RENDER_VERIFIED` | رسمی + سند تست زنده «هر دو روش رسمی و تأییدشده‌اند» |
| Telegram | دکمه url | `LIVE_API_ACCEPTED` (169519) | ✅ `CLIENT_RENDER_VERIFIED` | رسمی + «دکمه ارسال شد» |
| Bale | Markdown لینک | `LIVE_API_ACCEPTED` (1184) | ⚠️ **ناشناخته** — «باید چشمی تأیید شود» | LINKED_SIGNATURE.md §تست زنده |
| Bale | دکمه url | `LIVE_API_ACCEPTED` (1184) | ⚠️ **ناشناخته** — «باید چشمی تأیید شود» | LINKED_SIGNATURE.md §تست زنده |
| Eitaa | `<a href>` + html | `LIVE_API_ACCEPTED` (177667926) | ✅ (سند ✅ تست زنده) | LINKED_SIGNATURE.md |
| Eitaa | دکمه url | فرستاده نمی‌شود | ⛔ `UNSUPPORTED` (کلاینت نمی‌فرستد) | LINKED_SIGNATURE.md |
| Soroush | — | هیچ call زنده‌ای ندارد | n/a | فقط mock |
| Rubika | plain + URL خام | `LIVE_API_ACCEPTED` (call واقعی 2xx) | ⚠️ خودکار لینک‌دار شدن URL در کلاینت روبیکا **تأیید نشده** | عیب‌یابی test-send |

---

## ۸. قواعد فال‌بک پیاده‌شده در کد

پیاده‌سازی: [`SignatureRenderer`](../../app/Services/ChannelPoster/SignatureRenderer.php) +
[`SignatureCapability`](../../app/Services/ChannelPoster/SignatureCapability.php).

1. **`type=inline` (پیش‌فرض):**
   - telegram/eitaa → `<a href="url">url</a>` با `parse_mode=html`؛ label و URL هر دو
     `htmlspecialchars` (ایمن در برابر HTML-injection).
   - bale → `[url](url)`؛ اگر label بفرسته شود و **Markdown-safe** نباشد
     (شامل `[\]()#*_~` `` ` `` یا `\`) → plain `label:\nurl` بدون parse_mode (فال‌بک امن).
   - soroush/rubika → plain `label:\nurl` (هرگز parse_mode/Markdown نمی‌فرستد).
2. **`type=button`:** فقط telegram/bale (`supportsUrlButton` در capability matrix). بقیه →
   **فال‌بک به inline** + پرچم `fallback=true` (شکست خاموش وجود ندارد).
3. **بدون label:** لینک خام به سبک پلتفرم (anchor html برای telegram/eitaa، Markdown برای bale،
   plain برای بقیه) — `parse_mode` فقط وقتی label هست تنظیم می‌شود.
4. **طول:** guard کپشن با `fitsCaption(base, sig, 1024)` — اگر امضا کپشن را از 1024 عبور داد،
   **امضا حذف می‌شود** و متن اصلی هرگز truncate نمی‌شود + `Log::warning`.
5. **retry امن:** در `TestPostPreviewService` اگر ارسال با `parse_mode` شکست خورد، retry بدون
   `parse_mode` انجام می‌شود (هیچ retry خودکار روی فرمت نامعتبر نیست).
6. **Unicode/Persian:** متن UTF-8 بدون تبدیل رد می‌شود؛ label فارسی در همهٔ پلتفرم‌ها mock-تست شده.
7. **legacy (ردیف‌های بدون `signature_enabled`):** `renderLegacy` = `platformLabel . ":\n" . url` —
   بایت‌به‌بایت با فرمت قدیمی `buildSignature` یکسان، پس کانال‌های موجود بدون تغییر رندر می‌شوند.

---

## ۹. نقشهٔ پوشش تست (mock)

| فایل | پوشش |
|---|---|
| [`tests/Unit/SignatureRendererTest.php`](../../tests/Unit/SignatureRendererTest.php) | 33 تست: telegram/eitaa anchor html + escape injection؛ bale Markdown + فال‌بک label ناامن (حلقه بر روی کاراکترهای مخرب)؛ soroush/rubika plain؛ raw/plain/none/empty/composite؛ دکمه در telegram/bale + fallback در بقیه؛ `fitsCaption` شامل مرز 1024؛ `renderLegacy`؛ sanity capability matrix |
| [`tests/Feature/ChannelPosterSignatureManagementTest.php`](../../tests/Feature/ChannelPosterSignatureManagementTest.php) | 15 تست: ویزارد 3 قدم end-to-end (enabled/disabled/بدون‌برچسب/لینک نامعتبر/بازگشت/مسیر متنی بله-خیر)، `buildSignature` برای هر پلتفرم، ردیف legacy با `signature_enabled=null`، guard کپشن در صف، retry بدون امضای دوتایی، ارسال فوری با/بدون امضا |
| [`tests/Unit/RubikaBotClientTest.php`](../../tests/Unit/RubikaBotClientTest.php) | 17 تست: شکل payload روبیکا (بدون parse_mode)، alias `message_id` (id/chat_id)، `diagnose()` بدون نشت secret، `correlation_id` |
| [`tests/Feature/ChannelPosterRubikaTestSendTest.php`](../../tests/Feature/ChannelPosterRubikaTestSendTest.php) | 10 تست: موفقیت، alias، خطای API، HTTP 403، timeout، پاسخ غیر-JSON (هیچ‌کدام secret را لاگ نمی‌کنند) |

**نتیجهٔ regression (2026-10-25):** فیلتر 8 کلاس تست → **129 passed / 654 assertions** (2 deprecated —
فقط هشدار PHP 8.5، بدون تأثیر).

**Skipped with reason:**

| مورد | دلیل |
|---|---|
| soroush parse_mode/Markdown | سند رسمی تأیید می‌کند ولی live-unverified → پیاده/تست نمی‌کنیم تا تست زنده مجاز انجام نشود |
| rubika parse_mode/Markdown/entities | `OFFICIAL_DOC_NOT_FOUND` — بدون سند رسمی پیاده نمی‌کنیم |
| eitaa دکمه url | `UNSUPPORTED` (کلاینت نمی‌فرستد) |
| entities برای غیرتلگرام | `OFFICIAL_DOC_NOT_FOUND` |
| test زندهٔ سروس/رووبیکا برای امضا | بدون مجوز صریح اجرا نمی‌شود |

---

## ۱۰. موارد باز و گام‌های بعدی

1. **بازرسی چشمی بله (اولویت بالا):** یک‌بار در اپلیکیشن بله، لینک Markdown و دکمه URL (message_id 1184
   یا ارسال جدید با `php artisan app:test-signature-link --platform=bale`) را ببینید و نتیجه را
   اینجا ثبت کنید → وضعیت `⚠️` به `CLIENT_RENDER_VERIFIED` یا `UNSUPPORTED` تبدیل می‌شود.
2. **تست زندهٔ سروس:** با توکن واقعی `api.splus.ir`، یک `sendMessage` با `parse_mode=html` بفرستید؛
   اگر پذیرفته شد، `OFFICIAL_CONFIRMED` به `LIVE_API_ACCEPTED` ارتقا می‌یابد و کلاینت پروژه می‌تواند
   `parse_mode` را برای سروس فعال کند (در حال حاضر عمداً خام است).
3. **رووبیکا:** (الف) envelope خطای واقعی را هنگام یک call ناموفق ثبت کنید؛ (ب) تأیید کنید آیا
   کلاینت روبیکا URL ساده را خودکار لینک‌دار می‌کند؛ (ج) اگر نمونهٔ جامعه `AliRn76/rubika-bot`
   دکمهٔ url را در `inline_keypad` نشان داد، با call زنده تأیید کنید قبل از هر پیاده‌سازی.
4. **ایتا:** اگر سند رسمی مستقل پیدا شد، `OFFICIAL_DOC_NOT_FOUND`های مربوطه ارتقا می‌یابند.
5. **قاعدهٔ عمومی:** هیچ `CLIENT_RENDER_VERIFIED` بدون مشاهدهٔ بصری ثبت نمی‌شود؛ هیچ
   `LIVE_API_ACCEPTED` بدون `message_id` واقعی ثبت نمی‌شود.

---

*این سند با کد فعلی (2026-10-25) هم‌خوان است: `SignatureCapability::matrix()` همین وضعیت‌ها را
برنامه‌پذیر نگه می‌دارد و `SignatureRenderer` فقط بر اساس همان capability matrix تصمیم می‌گیرد.*
