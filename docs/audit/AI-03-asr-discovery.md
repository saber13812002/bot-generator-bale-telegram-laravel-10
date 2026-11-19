# AI-03 — ASR Discovery (Speech-to-Text)

**وضعیت: NEEDS-DESIGN** (وابسته به تأیید مدل ASR در LiteLLM — فعلاً BLOCKED)

## 1) خلاصهٔ مدیریتی

دریافت فایل صوتی از کاربران **هم‌اکنون در چند ربات پیاده شده** (`voice`, `audio`, `document.mp3`) و مسیر دانلود فایل از Telegram/Bale هم موجود است. اما **هیچ قابلیت تبدیل صوت به متن وجود ندارد**: نه FFmpeg، نه کتابخانهٔ صوتی، نه فراخوانی STT، نه اجرای Process. بنابراین ASR یک قابلیت کاملاً جدید است که می‌تواند روی همان زیرساخت دانلود + LiteLLM سوار شود.

## 2) وضعیت فعلی

| قابلیت | وضعیت |
|---|---|
| دریافت `voice`/`audio` از وب‌هوک | ✅ در ۸+ کنترلر |
| دریافت `document` با MIME صوتی | ✅ (Book Library / Book Pixel) |
| دانلود فایل با `getFile` → `file_path` | ✅ سه پیاده‌سازی مستقل |
| تبدیل فرمت (ogg/opus → wav/mp3) | ❌ |
| FFmpeg / Process / `shell_exec` | ❌ (جست‌وجوی کل `app/` فقط `curl_exec` برگرداند) |
| STT محلی یا ابری | ❌ |
| مدل ASR در LiteLLM | ❓ نامعلوم (401) |

## 3) شواهد کد و دیتابیس

- دریافت صوت: [`BookPixelController::handleVoice()`](../../app/Http/Controllers/BookPixelController.php:575)، [`BookLibraryReaderController.php:797`](../../app/Http/Controllers/BookLibraryReaderController.php:797)، [`ContentBotMotherService.php:168`](../../app/Services/ContentBotMotherService.php:168)، [`ChannelPosterBotServiceImpl::extractMedia()`](../../app/Services/ChannelPosterBotServiceImpl.php:185).
- دانلود (بهترین الگو): [`ChannelPosterMediaStore::fetchAndStore()`](../../app/Services/ChannelPosterMediaStore.php:30) — `getFile` → `file_path` → `file_get_contents` با `timeout=30`، `verify_peer=true`، سقف `50MB` ([`MAX_BYTES`](../../app/Services/ChannelPosterMediaStore.php:21)).
- دانلود جایگزین: [`FileUploadHelper::downloadFile()`](../../app/Helpers/FileUploadHelper.php:448)، [`MissionMediaBotController::getFileUrl()`](../../app/Http/Controllers/MissionMediaBotController.php:495)، [`BlogMessengerBroadcastService::getFileUrl()`](../../app/Services/BlogMessengerBroadcastService.php:163).
- ذخیره‌سازی صوت: جداول `book_page_voices` (فقط `file_id`)، `media_queue_items`، `content_assets` — هیچ ستونی برای متن پیاده‌شده وجود ندارد.
- نقشهٔ نوع رسانه و MIME: [`ChannelPosterMediaSender.php:394`](../../app/Services/ChannelPosterMediaSender.php:394) (`voice => audio/ogg`).
- تست ساخت WAV دستی (بدون ffmpeg): [`TestPostPreviewService::generateWavTone()`](../../app/Services/TestPostPreviewService.php:284) — تنها کدِ مرتبط با تولید صدا در پروژه.

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | PHP 8.5 محلی؛ افزونهٔ صوتی نصب‌نشده؛ هیچ ابزار تبدیل |
| GitHub | `7702b68f` — بدون کد ASR |
| Production | PHP 8.2.34 در shared hosting؛ **وجود ffmpeg تأییدنشده** (به‌احتمال زیاد ندارد) |

## 5) تست‌های انجام‌شده و نتایج واقعی

- هیچ تست ASR وجود ندارد (نه Unit، نه Feature).
- تست واقعی روی پراکشن انجام نشد (بدون دسترسی/توکن).

## 6) موارد ناشناخته و محدودیت دسترسی

- وجود `whisper`/`whisper-1`/`gpt-4o-transcribe` یا مدل فارسی روی LiteLLM.
- محدودیت حجم واقعی Telegram/Bale برای `getFile` (مستندات: Bot API تا ۲۰MB دانلود؛ Bale مشابه).
- کیفیت ASR فارسی روی صوت تلفنی/نویزی.

## 7) مشکلات و ریسک‌ها

- **سه پیاده‌سازی موازی دانلود** ⇒ باید یکی به‌عنوان مرجع انتخاب شود (پیشنهاد: `ChannelPosterMediaStore`).
- فایل `voice` معمولاً **Opus/Ogg** است؛ اگر مدل انتخابی فقط WAV/MP3 بپذیرد، تبدیل اجباری است ⇒ نیاز به ffmpeg یا کتابخانهٔ PHP.
- با `QUEUE_CONNECTION=sync`، STT داخل وب‌هوک اجرا می‌شود ⇒ تایم‌اوت و از دست رفتن پیام.
- حریم خصوصی: صوت کاربر دادهٔ حساس است؛ ارسال به Provider خارجی نیازمند رضایت و سیاست نگهداری.

## 8) قابلیت‌های قابل استفاده مجدد

- `ChannelPosterMediaStore` (دانلود امن + سقف حجم + Redaction).
- الگوی `AiProviderService` برای فراخوانی OpenAI-compatible (`/v1/audio/transcriptions`).
- بازخورد ویزاردی موجود در `BookPixelController` برای گرفتن فایل از کاربر.

## 9) پیشنهاد MVP

**سناریو:** کاربر Voice می‌فرستد → دانلود → (در صورت نیاز تبدیل) → STT → متن وارد همان Handler متنی موجود می‌شود.

```
VoiceWebhook → VoiceIngestService (validate mime/size/≤60s)
             → MediaFetcher (ChannelPosterMediaStore)
             → AsrClient (LiteLLM /v1/audio/transcriptions, timeout 60s)
             → TextPipeline (همان مسیر پیام متنی)
Fallback: اگر AsrClient شکست خورد → پیام «فعلاً امکان تبدیل صوت نیست؛ متن را بفرستید»
```

- هزینهٔ تقریبی: Whisper API ≈ $0.006/دقیقه؛ ۶۰ ثانیه ≈ $0.006 (≈ ۳۰۰ تومان در نرخ‌های ۱۴۰۴) — نیازمند تأیید نرخ واقعی LiteLLM.
- زمان: STT ابری ۲–۸ ثانیه برای ۶۰ ثانیه صوت؛ محلی (faster-whisper) ۱–۳ ثانیه با CPU مناسب ولی نیازمند نصب.

**تست‌های پذیرش:** صوت فارسی کوتاه (۱۰s) · صوت طولانی (۹۰s) · نویز · اعداد/نام‌های خاص · فایل ناقص/نامعتبر.

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| تأیید وجود مدل ASR | توکن معتبر LiteLLM |
| انتخاب ffmpeg یا کتابخانه | دسترسی سرور / سیاست hosting |
| صف اختصاصی ASR | تأیید معماری Queue (AI-07) |
| تصویب سیاست حریم خصوصی صوت | مالک |

**Status: NEEDS-DESIGN / BLOCKED** روی دسترسی مدل.
