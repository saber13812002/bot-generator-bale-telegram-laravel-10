# 🧪 پست تستی / پیش‌نمایش (Test Preview Post)

ارسال پست **تستی/پیش‌نمایش** (متن، عکس، صوت، ویدیو — با کپشن) به یک چت/کانال تستی،
**از طریق دقیقاً همان متدهایی که در production استفاده می‌شوند**؛ تا قبل از انتشار واقعی،
خروجی نهایی در محیط تست دیده شود و تأیید گردد.

---

## 🎯 کاربرد

وقتی تغییری در خروجی یک پست اعمال می‌کنی (متن، کپشن، رسانه، امضا و…)، به‌جای انتشار در کانال واقعی،
اول خروجی را با همین فیچر به چت تستی خودت می‌فرستی، بررسی می‌کنی و سپس همان مسیر production
به کانال مقصد منتشر می‌شود. چیزی که در تست می‌بینی = چیزی که در production ارسال می‌شود.

---

## 📁 فایل‌های فیچر

| فایل | نقش |
|---|---|
| [`app/Services/TestPostPreviewService.php`](../../app/Services/TestPostPreviewService.php) | سرویس اصلی: انتخاب مسیر production + نرمال‌سازی پاسخ |
| [`app/Console/Commands/SendTestPreview.php`](../../app/Console/Commands/SendTestPreview.php) | کاماند `php artisan app:send-test-preview` |

## 🔗 مسیرهای production که استفاده می‌شود

| نوع پست | مسیر ارسال (production) |
|---|---|
| **text** (ایتا) | `BotHelper::sendMessageEitaaSupport` → `call_eitaa_api` → `/sendMessage` |
| **text** (تلگرام/بله) | کلاینت `Telegram` → `sendMessage` |
| **photo / video / voice / audio** (هر ۳ پلتفرم) | [`ChannelPosterMediaSender::send`](../../app/Services/ChannelPosterMediaSender.php) — همان کلاسی که انتشار چندکاناله واقعی با آن آپلود/ارسال می‌کند (ایتا → `sendFile` multipart، تلگرام/بله → `sendPhoto`/`sendVideo`/`sendAudio`/`sendVoice`) |

> نکته: برای endpoint ایتا (`eitaayar.ir`) گزینه `verify => false` در
> `ChannelPosterMediaSender` فعال است — گواهی self-signed دارد، هم‌تراز با رفتار
> `BotHelper::call_eitaa_api` (curl با `CURLOPT_SSL_VERIFYPEER=false`).

---

## ⚙️ تنظیمات (.env)

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `TEST_PREVIEW_PLATFORM` | `eitaa` | پلتفرم مقصد: `eitaa` \| `bale` \| `telegram` |
| `TEST_PREVIEW_BOT_TOKEN` | — | توکن ربات (باید عضو کانال/چت تستی باشد) |
| `TEST_PREVIEW_CHAT_ID` | — | chat id کانال/چت تستی |
| `TEST_PREVIEW_MEDIA_DIR` | `storage/app/test-media` | دایرکتوری فایل‌های رسانه نمونه |

> ⚠️ برای کانال‌های ایتا ربات باید **admin/creator** کانال باشد.

---

## 🚀 طرز استفاده

```bash
# ۱) متن
php artisan app:send-test-preview --type=text --caption="پیام تستی"
php artisan app:send-test-preview --type=text --caption="<b>بولد</b>" --parse-mode=HTML

# ۲) رسانه با کپشن (فایل از دیسک یا URL)
php artisan app:send-test-preview --type=photo  --caption="کپشن عکس"
php artisan app:send-test-preview --type=video  --file="C:\media\clip.mp4" --caption="کپشن ویدیو"
php artisan app:send-test-preview --type=audio  --file=track.mp3 --caption="کپشن صوت"
php artisan app:send-test-preview --type=voice  --file=voice.ogg
php artisan app:send-test-preview --type=photo  --file="https://example.com/img.jpg"

# ۳) ساخت فایل‌های نمونه (عکس PNG با GD + صوت WAV بدون نیاز به ابزار خارجی)
php artisan app:send-test-preview --samples

# ۴) override پلتفرم/توکن/چت بدون تغییر .env
php artisan app:send-test-preview --type=text --platform=bale --token=... --chat-id=...
```

### انواع `--type`

`text` · `photo` (یا `image`) · `video` · `audio` (یا `music`) · `voice`

اگر `--file` داده نشود، فایل از دایرکتوری `TEST_PREVIEW_MEDIA_DIR` با نام‌های
`photo.jpg/png`، `video.mp4`، `audio.mp3`، `voice.wav/ogg` انتخاب می‌شود.

---

## ✅ سناریوی تست (اجراشده — 1405/07/30)

تست روی **ایتا** (توکن `TEST_PREVIEW_BOT_TOKEN` + چت تستی):

| پست | نتیجه |
|---|---|
| متن (text) | ✅ ارسال شد — `message_id: 177635041` |
| عکس + کپشن (photo) | ✅ ارسال شد — `message_id: 177635382` |
| صوت + کپشن (voice) | ✅ ارسال شد — `message_id: 177635403` |
| ویدیو / موزیک | همان مسیر `ChannelPosterMediaSender` (`sendVideo`/`sendAudio`) — کافی است فایل نمونه در دایرکتوری رسانه‌ها باشد |

### عیب‌یابی

- **`missing TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID`** → تنظیمات .env را کامل کن.
- **`cURL error 60: SSL certificate ... self-signed`** → فقط برای aیتا؛ با `withOptions(['verify' => false])` در `ChannelPosterMediaSender` حل شده.
- **`API ok=false: ...`** → خروجی API نمایش داده می‌شود (معمولاً: ربات عضو کانال نیست یا admin نیست).
- خروجی خطاها از `TokenHelper::redactTokens` رد می‌شود تا توکن در لاگ نچسبد.
