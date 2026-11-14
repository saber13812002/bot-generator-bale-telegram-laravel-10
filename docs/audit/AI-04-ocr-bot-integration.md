# AI-04 — OCR Bot Integration Discovery

**وضعیت: NEEDS-DESIGN** (پیاده‌سازی مسدود تا رفع توکن LiteLLM — AI-01)

## 1) خلاصهٔ مدیریتی

مسیر دریافت عکس/سند در چند ربات **از قبل وجود دارد** (Book Pixel، Channel Poster، Book Library، Mission Media) و دانلود امن فایل هم پیاده شده است. تنها قطعهٔ گمشده، سرویس OCR است. توصیه: **ربات اختصاصی جدید ساخته نشود**؛ قابلیت عکس‌به‌متن به یک ربات موجود که همین امروز عکس می‌گیرد اضافه شود و در صورت نیاز یک `endpoint_id` سبک «عکس‌به‌متن» با همان Bot Mother ساخته شود.

## 2) وضعیت فعلی

| مسیر | فایل | وضعیت |
|---|---|---|
| دریافت Photo (بزرگ‌ترین سایز) | [`BookPixelController.php:448`](../../app/Http/Controllers/BookPixelController.php:448) | ✅ |
| دریافت Document/Photo/Video/Voice | [`ChannelPosterBotServiceImpl::extractMedia()`](../../app/Services/ChannelPosterBotServiceImpl.php:150) | ✅ |
| دریافت عکس در ربات مادر | [`BotMotherController.php:2024`](../../app/Http/Controllers/BotMotherController.php:2024) | ✅ |
| دانلود فایل‌ها | [`ChannelPosterMediaStore`](../../app/Services/ChannelPosterMediaStore.php:30) | ✅ (≤50MB، timeout 30s) |
| ارسال صفحهٔ اسکن به کاربر (خروجی) | [`QuranWordController.php:1164`](../../app/Http/Controllers/QuranWordController.php:1164) | ✅ |
| OCR (تشخیص متن) | — | ❌ |

## 3) شواهد کد و دیتابیس

- الگوی گرفتن عکس و پاسخ به کاربر: [`BookPixelController`](../../app/Http/Controllers/BookPixelController.php:82) (تشخیص `message.photo`/`voice` در ابتدای وب‌هوک).
- ذخیرهٔ ارجاع فایل: `BookPageScan.file_id`, `BotUploadedFile.file_id`, `channel_poster_queue.file_id/media_path`.
- **هیچ ستون یا جدولی برای متن استخراج‌شده وجود ندارد** (نیازمند طراحی، نه Migration در این مرحله).
- ارسال مجدد متن به کاربر: `BotHelper::sendMessage()` ([`BotHelper.php`](../../app/Helpers/BotHelper.php)) با محدودیت طول پیام پلتفرم‌ها.

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | DB خالی (`bots=0`) ⇒ جریان واقعی قابل تست نیست |
| GitHub | `7702b68f` |
| Production | کد `0b8f990d`/`cb5b8423` — ربات‌های فعال، دسترسی‌ناپذیر |

## 5) تست‌های انجام‌شده و نتایج واقعی

- تست‌های موجود: [`ChannelPosterBotServiceTest`](../../tests/Unit/ChannelPosterBotServiceTest.php:141) (استخراج نوع رسانه)، [`UploadingMediaFilesTest`](../../tests/Feature/UploadingMediaFilesTest.php)، [`ChannelPosterMediaPublishTest`](../../tests/Feature/ChannelPosterMediaPublishTest.php) — همه **Mock با `Http::fake`**.
- تست OCR: **انجام نشده** (بدون سرویس/توکن).

## 6) موارد ناشناخته و محدودیت دسترسی

- کیفیت OCR فارسی مدل Vision موجود (نامعلوم).
- سیاست نگهداری تصویر کاربران (نیازمند تصمیم مالک).
- نرخ پیام و حجم تصاویر در پراکشن.

## 7) مشکلات و ریسک‌ها

1. با `sync`، OCR داخل وب‌هوک ⇒ احتمال timeout پلتفرم و **تکرار وب‌هوک** (خطر پردازش دوباره).
2. تصویر کاربر دادهٔ حساس است ⇒ رضایت + عدم ذخیرهٔ دائمی + عدم لاگ Base64.
3. نبود Idempotency ⇒ اگر کاربر همان عکس را دوباره بفرستد، پردازش دوباره و هزینهٔ مضاعف.
4. متن بلند در یک پیام جا نمی‌شود ⇒ نیاز به Chunking یا ارسال به‌صورت فایل متنی.

## 8) قابلیت‌های قابل استفاده مجدد

- `ChannelPosterMediaStore` برای دانلود + سقف حجم.
- `AiProviderService` به‌عنوان Transport.
- `BotUploadedFile` برای کش `file_id` و جلوگیری از دانلود تکراری.
- `webhook_endpoints.features` برای معرفی قابلیت جدید در کاتالوگ.

## 9) پیشنهاد MVP

**قرارداد سرویس (پیشنهادی، بدون پیاده‌سازی):**

```php
OcrService::extract(
    string $absolutePath,
    array  $opts = []   // ['lang' => 'fa', 'structure' => false, 'max_pages' => 1]
): OcrResult
// OcrResult: text, lines[], confidence?, provider, model, latency_ms, tokens, cost, error_code
```

- **ربات پیشنهادی:** Book Pixel (عکس می‌گیرد و مقصدش «متن/صفحهٔ کتاب» است) به‌عنوان اولین مقصد؛ سپس یک `endpoint_id` «عکس‌به‌متن» سبک برای استفادهٔ عمومی.
- **نمایش خروجی:** پیام اول = ۱۰۰۰ کاراکتر اول + دکمهٔ «متن کامل» (فایل `.txt` یا پیام‌های دنباله).
- **رفتار در قطعی AI:** پاسخ ثابت «سرویس متن‌خوان موقتاً در دسترس نیست؛ عکس شما ذخیره شد، بعداً اطلاع می‌دهیم» + ثبت Job با وضعیت `pending_ai` (نیازمند صف).
- **Retry امن:** حداکثر ۲ تلاش با Backoff و کلید Idempotency = `chat_id + file_unique_id`.

**سناریوی پذیرش:** کاربر عکس متن فارسی می‌فرستد → متن در همان گفتگو ظاهر می‌شود؛ برای تصویر بدون متن، پیام «متنی پیدا نشد»؛ برای فایل > سقف، پیام محدودیت حجم.

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| تأیید مدل Vision/OCR | AI-01 (توکن) |
| تعیین ربات میزبان | مالک |
| صف + Idempotency | AI-07 |
| سیاست نگهداری تصویر | مالک |

**Status: NEEDS-DESIGN.**
