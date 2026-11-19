---
name: Blog to Messenger Broadcast
overview: حذف وابستگی BlogController به API وبلاگ و BlogHelper، اضافه کردن جدول messengers (با migration و مدل)، و تبدیل BlogController به یک نقطه ورود که پیام/فایل دریافتی را بر اساس رکورد messengers به کانال‌های تلگرام، بله و ایتا پخش کند.
todos: []
isProject: false
---

# پلن: تبدیل Blog Bot به پخش در کانال‌های Messenger (تلگرام، بله، ایتا)

## وضعیت فعلی

- **[BlogController](app/Http/Controllers/BlogController.php)**: ترافیک webhook-blog به آن می‌رسد. با `BlogHelper::getBlogInfo()` بر اساس `type` و `ChatID` از جدول `blog_users` مقدار `author_id` و `blog_token` را می‌گیرد، سپس با `BlogHelper::callApiPost()` و `callArtisanQueueWork()` به API وبلاگ (حذف‌شده) درخواست می‌زند.
- **BlogHelper**: فقط برای وبلاگ استفاده می‌شود؛ `callApiPost`, `getBlogInfo`, `callArtisanQueueWork` وابسته به سرویس بلاگ هستند.
- **SmsController**: از `BlogHelper::getBlogInfoByMobileNumber`, `callApiPost`, `callArtisanQueueWork` استفاده می‌کند؛ با حذف سرویس بلاگ این مسیر هم باید اصلاح یا غیرفعال شود (خارج از اسکوپ گام اول: فقط BlogController).
- جدول **messengers** در این پروژه وجود ندارد؛ باید از روی ساختار مشخص‌شده در سپک (فیلدهای بله/تلگرام/ایتا) ایجاد و در صورت نیاز دیتا از پروژه بلاگ import شود.

## هدف

- **گام ۱ (متن):** هر پیام متنی که به ربات وبلاگ (webhook-blog) می‌رسد، بر اساس یک رکورد **messengers** (شناسایی کاربر با `telegram_admin_chat_id` یا `bale_admin_chat_id` مطابق چت فرستنده) به کانال‌های پیکربندی‌شده همان کاربر در **تلگرام، بله و ایتا** ارسال شود.
- **گام ۲ (فایل):** هر فایل (عکس با کپشن، ویدیو، وویس، آدیو، داکیومنت و غیره) که به همان ربات ارسال می‌شود، در همان کانال‌ها با حفظ نوع مدیا و کپشن پخش شود.

ورودی فقط از همان **BlogController** (webhook-blog) است؛ نیازی به API جداگانه از سمت وبلاگ نیست.

---

## ۱. دیتابیس و مدل Messengers

- **Migration جدید** برای جدول `messengers` با فیلدهای (مطابق سپک):
  - `id`, `user_id` (nullable، برای تطابق با سیستم قبلی)
  - بله: `bale_channel_chat_id`, `bale_admin_chat_id`, `bale_bot_token`, `bale_channel_invite_link`
  - تلگرام: `telegram_channel_chat_id`, `telegram_admin_chat_id`, `telegram_bot_token`, `telegram_channel_invite_link`
  - ایتا: `eitaa_channel_chat_id`, `eitaa_admin_chat_id`, `eitaa_bot_token`, `eitaa_channel_invite_link`
  - `created_at`, `updated_at`
- **Model** `App\Models\Messenger` با fillable و هرگونه scope لازم (مثلاً یافتن با `telegram_admin_chat_id` یا `bale_admin_chat_id`).

---

## ۲. شناسایی کاربر (رکورد Messenger) از درخواست وبلاگ

- در `BlogController::index` بعد از ساخت `$bot` و خواندن `LogHelper::log`:
  - `$type = $request->input('origin')` (telegram یا bale)
  - `$chatId = $bot->ChatID()`
- جستجو در `messengers`:
  - اگر `$type === 'telegram'` → `Messenger::where('telegram_admin_chat_id', $chatId)->first()`
  - اگر `$type === 'bale'` → `Messenger::where('bale_admin_chat_id', $chatId)->first()`
- اگر رکوردی نبود: پیام خطا به کاربر (مثلاً «تنظیمات کانال یافت نشد…») و `return`؛ نیازی به فراخوانی BlogHelper نیست.

---

## ۳. استخراج محتوا از پیام (متن یا فایل + کپشن)

- **متن:** از `$bot->Text()` (برای پیام متنی یا کپشن فایل).
- **فایل:** از payload وب‌هوک (معمولاً در `$bot->getData()` یا معادل در request):
  - تشخیص نوع: `photo`, `video`, `voice`, `audio`, `document` و در صورت وجود استخراج `file_id` (برای عکس بزرگ‌ترین سایز را بگیر، مشابه [BookPixelController](app/Http/Controllers/BookPixelController.php) خط ~450 و [MissionMediaBotController](app/Http/Controllers/MissionMediaBotController.php) متد `getFileUrl`).
- خروجی یک ساختار ساده: «فقط متن» یا «نوع فایل + file_id + متن (کپشن)».

---

## ۴. ارسال به هر پلتفرم (تلگرام، بله، ایتا)

- برای **هر** پلتفرمی که در رکورد `messengers` مقدار `*_bot_token` و `*_channel_chat_id` دارد:
  - **تلگرام:** ساخت `new Telegram($messenger->telegram_bot_token)` و ارسال به `telegram_channel_chat_id`: در صورت متن فقط `sendMessage`؛ در صورت فایل استفاده از متد مناسب (مثلاً `sendPhoto`, `sendVideo`, `sendVoice`, `sendAudio`, `sendDocument`) با همان `file_id` اگر مقصد همان پلتفرم است، وگرنه گرفتن فایل از منبع (getFile + دانلود) و ارسال به مقصد با URL/فایل موقت (الگوی مشابه [MissionMediaBotController::getFileUrl](app/Http/Controllers/MissionMediaBotController.php#L495) و ارسال از طریق [BotHelper](app/Helpers/BotHelper.php) یا مستقیم با کلاس Telegram).
  - **بله:** همان منطق با `bale_bot_token` و `bale_channel_chat_id` (API بله سازگار با تلگرام است).
  - **ایتا:** فقط متن با `BotHelper::sendMessageEitaa` (یا `call_eitaa_api` با `sendMessage`). برای فایل: در صورت پشتیبانی ایتا از فایل از همان مسیر `sendFile` در [BotHelper::call_eitaa_api](app/Helpers/BotHelper.php#L306) استفاده شود (با دانلود فایل از منبع و ارسال به ایتا)؛ در غیر این صورت فقط متن/کپشن به ایتا ارسال شود تا ساده بماند.

خطا در یک پلتفرم نباید ارسال به بقیه را متوقف کند؛ هر خطا لاگ شود و در پایان یک پیام خلاصه به کاربر داده شود (موفق/ناموفق برای هر کانال).

---

## ۵. تغییرات مستقیم در BlogController

- حذف تمام استفاده از `BlogHelper` (`getBlogInfo`, `callApiPost`, `callArtisanQueueWork`).
- حذف وابستگی به `author_id`, `blog_token` از request و از دیتابیس بلاگ.
- **دستور /start:** به‌جای لینک RSS و توییت، یک پیام ساده (مثلاً «پیام یا فایل بفرستید تا در کانال‌های شما منتشر شود») و در صورت نبود رکورد messengers پیام «تنظیمات یافت نشد».
- بعد از استخراج محتوا (متن یا فایل+کپشن): فراخوانی یک سرویس/کلاس **Broadcast** که ورودی آن رکورد `Messenger` و محتواست و داخل آن ارسال به تلگرام، بله و ایتا انجام شود (برای خوانایی و تست‌پذیری بهتر است این منطق در یک کلاس جدا باشد، مثلاً `App\Services\BlogMessengerBroadcastService` یا مشابه).
- حذف `sendResultMessageToUser` و `handleCallApiExceptions` مربوط به پاسخ API بلاگ؛ به‌جای آن یک پاسخ ساده به کاربر (مثلاً «ارسال به کانال‌ها انجام شد» یا لیست کانال‌های موفق/ناموفق).

---

## ۶. سرویس پخش (پیشنهاد معماری)

- **کلاس جدید** مثلاً `App\Services\BlogMessengerBroadcastService` با متد اصلی مثلاً `broadcast(Messenger $messenger, string $text, ?string $mediaType = null, ?string $fileId = null, string $caption = '')`.
  - داخل آن: برای تلگرام و بله با token و channel_chat_id از `$messenger` ارسال متن یا مدیا (در صورت مدیا: اگر منبع همان پلتفرم است از file_id استفاده کن، وگرنه با getFile + دانلود و ارسال به پلتفرم دیگر).
  - برای ایتا: متن (و در صورت تصمیم نهایی، فایل با sendFile).
- فراخوانی این سرویس از `BlogController` بعد از پیدا کردن رکورد `Messenger` و استخراج متن/فایل.

---

## ۷. فایل‌های قابل حذف یا تغییر

- **BlogController:** بازنویسی مطابق بالا؛ حذف وابستگی به config بلاگ برای پاسخ به کاربر.
- **BlogHelper:** در این گام فقط از BlogController استفاده نمی‌شود؛ SmsController هنوز وابسته است. دو حالت: (۱) SmsController را موقتاً غیرفعال یا با پیام «سرویس در دسترس نیست» برگردانیم، یا (۲) تا زمانی که SmsController به فلوی جدید منتقل نشده، BlogHelper را نگه داریم و فقط در BlogController استفاده نکنیم. در پلن پیشنهاد: فقط حذف استفاده از BlogHelper در BlogController؛ حذف کامل BlogHelper در صورت نیاز در مرحله بعد.
- **config/blog.php:** می‌توان برای پاسخ‌های قدیمی حذف یا ساده شد؛ در گام اول لازم نیست.

---

## ۸. Import دیتا از جدول messengers پروژه بلاگ

- خروجی از دیتابیس بلاگ (جدول messengers) با همان نام ستون‌ها یا map به ستون‌های migration جدید.
- Import دستی (مثلاً با seeder یا دستور artisan که فایل JSON/CSV می‌خواند و در `messengers` insert می‌کند) برای حداقل یک کاربر (همان «یک کاربر» که قبلاً با ربات وبلاگ کار می‌کرد) تا تست گام اول ممکن شود.

---

## ۹. خلاصه ترتیب پیاده‌سازی پیشنهادی

1. Migration و مدل `Messenger`.
2. سرویس پخش (مثلاً `BlogMessengerBroadcastService`) با پشتیبانی متن و در مرحله دوم فایل (با استفاده از getFile + ارسال به هر پلتفرم).
3. تغییر BlogController: پیدا کردن Messenger از chat_id و type، استخراج متن/فایل، فراخوانی سرویس پخش، پاسخ ساده به کاربر و حذف وابستگی به BlogHelper و API بلاگ.
4. به‌روزرسانی دستور /start و حذف پیام‌های مرتبط با RSS/توییت.
5. (اختیاری) اسکریپت/دستور Import برای جدول messengers و تست با یک رکورد.

---

## وابستگی‌های موجود در کدبیس که استفاده می‌شوند

- **BotHelper::sendMessage**, **sendMessageByChatId**, **sendMessageEitaa** / **call_eitaa_api** (متن و در صورت نیاز sendFile برای ایتا).
- **BotHelper::sendPhoto**, **sendAudio**, **sendDocument**, **sendVideo** و مشابه برای ارسال مدیا (با URL یا path بعد از getFile و دانلود).
- الگوی **getFileUrl** در MissionMediaBotController برای تبدیل `file_id` به URL و سپس ارسال به کانال دیگر یا ایتا.
- کلاس **Telegram** برای ساخت نمونه با token و type و ارسال به `channel_chat_id`.

اگر موافق این پلن هستید، می‌توان مرحله‌ی «فقط متن» را اول پیاده کرد و در مرحله بعد پشتیبانی فایل (با همان سرویس پخش) اضافه کرد.