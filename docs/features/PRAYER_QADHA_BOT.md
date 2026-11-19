# ربات نماز قضا (Prayer Qadha Bot)

## خلاصه

ربات نماز قضا یک سیستم جامع برای ثبت، ردیابی و گزارش‌دهی نمازهای قضا است. این ربات به کاربران امکان می‌دهد تا به راحتی رکعات نماز قضای خود را ثبت کنند و گزارش‌های دوره‌ای از پیشرفت خود دریافت کنند.

## ویژگی‌ها

### 1. ثبت آسان رکعات
- ارسال ساده اعداد 2، 3 یا 4 برای ثبت رکعات
- تشخیص هوشمند نوع نماز (صبح، ظهر، عصر، مغرب، عشا)
- پشتیبانی از پلتفرم‌های تلگرام و بله
- ارسال پیام تایید با شناسه منحصر به فرد برای هر رکعت

### 2. مدیریت رکعات
- حذف رکعات ثبت شده با دستور `/remove_<id>`
- مشاهده آمار کامل با دستور `/stats`
- ثبت تخمین نمازهای قضا با `/estimate`

### 3. گزارش‌دهی ایمیلی
- ارسال خودکار گزارش‌های هفتگی به ایمیل
- 5 تمپلیت متنوع برای جلوگیری از اسپم
- قابلیت لغو اشتراک (Unsubscribe) استاندارد
- محدودیت ارسال: 2 ایمیل در ساعت

### 4. تشخیص هوشمند

#### الگوریتم تشخیص:
```
عدد 2 → نماز صبح (fajr)
عدد 3 → نماز مغرب (maghrib)  
عدد 4 → بر اساس زمان روز:
  - صبح تا ظهر → ظهر (dhuhr)
  - ظهر تا غروب → عصر (asr)
  - بعد از غروب → عشا (isha)
```

#### تشخیص از متن:
کاربر می‌تواند کلمات کلیدی مانند "صبح"، "ظهر"، "عصر"، "مغرب" یا "عشا" را همراه با عدد ارسال کند.

## معماری سیستم

```
کاربر (تلگرام/بله)
    ↓
PrayerBotController
    ↓
PrayerBotService
    ↓
PrayerRecordRepository → Database (prayer_records)
```

### دیتابیس

#### جدول `prayer_records`
| ستون | نوع | توضیحات |
|------|-----|---------|
| id | bigint | شناسه یکتا |
| chat_id | bigint | شناسه چت کاربر |
| rakats | int | تعداد رکعات (2، 3، 4) |
| prayer_type | enum | نوع نماز |
| origin | enum | پلتفرم (telegram/bale) |
| created_at | timestamp | زمان ثبت |

#### جدول `prayer_estimates`
| ستون | نوع | توضیحات |
|------|-----|---------|
| id | bigint | شناسه یکتا |
| chat_id | bigint | شناسه چت کاربر |
| total_missed_prayers | int | تعداد نمازهای قضا (تخمینی) |
| total_missed_rakats | int | تعداد رکعات (محاسبه شده) |

#### جدول `email_report_queue`
| ستون | نوع | توضیحات |
|------|-----|---------|
| id | bigint | شناسه یکتا |
| email | string | ایمیل مقصد |
| status | enum | وضعیت (pending/sent/failed) |
| sent_at | timestamp | زمان ارسال |

## نحوه استفاده

### دستورات ربات

#### `/start`
شروع تعامل با ربات و نمایش راهنما

#### ثبت رکعات
فقط عدد بفرستید:
```
2       → ثبت 2 رکعت صبح
3       → ثبت 3 رکعت مغرب  
4       → ثبت 4 رکعت (ظهر/عصر/عشا بر اساس زمان)
```

یا با توضیح:
```
2 صبح
4 ظهر
3 مغرب
```

#### `/stats`
مشاهده آمار کامل:
- تعداد کل رکعات
- آمار هفته گذشته
- تفکیک به نوع نماز
- درصد پیشرفت (در صورت ثبت تخمین)

#### `/estimate <عدد>`
ثبت تخمین نمازهای قضا:
```
/estimate 1000
```

#### `/remove_<id>`
حذف رکعت ثبت شده:
```
/remove_123
```

#### `/email`
تنظیمات ایمیل و گزارش‌دهی

### مثال تعامل

```
کاربر: /start
ربات: 🕌 به ربات نماز قضا خوش آمدید...

کاربر: 2
ربات: ✅ رکعت ثبت شد
      🔢 2 رکعت
      📿 نوع نماز: صبح
      🆔 شناسه: 123
      🗑️ برای حذف: /remove_123

کاربر: /stats
ربات: 📊 آمار هفتگی شما
      🔢 مجموع رکعات: 45
      📅 هفته گذشته: 12 رکعت
      ...
```

## فایل‌های مرتبط

### Controllers
- [`app/Http/Controllers/PrayerBotController.php`](../../app/Http/Controllers/PrayerBotController.php) - کنترلر اصلی

### Services
- [`app/Services/PrayerBotServiceImpl.php`](../../app/Services/PrayerBotServiceImpl.php) - منطق کسب‌وکار
- [`app/Interfaces/Services/PrayerBotService.php`](../../app/Interfaces/Services/PrayerBotService.php) - Interface

### Repositories
- [`app/Repositories/PrayerRecordRepositoryImpl.php`](../../app/Repositories/PrayerRecordRepositoryImpl.php)
- [`app/Repositories/PrayerEstimateRepositoryImpl.php`](../../app/Repositories/PrayerEstimateRepositoryImpl.php)

### Models
- [`app/Models/PrayerRecord.php`](../../app/Models/PrayerRecord.php)
- [`app/Models/PrayerEstimate.php`](../../app/Models/PrayerEstimate.php)
- [`app/Models/EmailReportQueue.php`](../../app/Models/EmailReportQueue.php)

### Helpers
- [`app/Helpers/PrayerHelper.php`](../../app/Helpers/PrayerHelper.php) - توابع کمکی
- [`app/Helpers/BotHelper.php`](../../app/Helpers/BotHelper.php) - متدهای اضافه شده

### Jobs & Commands
- [`app/Jobs/SendPrayerReportEmailJob.php`](../../app/Jobs/SendPrayerReportEmailJob.php)
- [`app/Console/Commands/SendPrayerWeeklyReports.php`](../../app/Console/Commands/SendPrayerWeeklyReports.php)

### Mail
- [`app/Mail/PrayerWeeklyReportMail.php`](../../app/Mail/PrayerWeeklyReportMail.php)
- [`resources/views/emails/prayer-weekly-report-v1.blade.php`](../../resources/views/emails/prayer-weekly-report-v1.blade.php) (و 4 نسخه دیگر)

### Migrations
- [`database/migrations/2026_01_06_140532_create_prayer_records_table.php`](../../database/migrations/2026_01_06_140532_create_prayer_records_table.php)
- [`database/migrations/2026_01_06_140549_create_prayer_estimates_table.php`](../../database/migrations/2026_01_06_140549_create_prayer_estimates_table.php)
- [`database/migrations/2026_01_06_140603_add_email_fields_to_bot_users_table.php`](../../database/migrations/2026_01_06_140603_add_email_fields_to_bot_users_table.php)
- [`database/migrations/2026_01_06_140615_create_email_report_queue_table.php`](../../database/migrations/2026_01_06_140615_create_email_report_queue_table.php)

## تنظیمات

### متغیرهای محیطی (Environment Variables)

در فایل `.env`:

```env
# توکن‌های ربات (اختیاری - در صورت استفاده از ربات جداگانه)
PRAYER_BOT_TOKEN_TELEGRAM=your_telegram_token
PRAYER_BOT_TOKEN_BALE=your_bale_token

# تنظیمات ایمیل
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="Prayer Bot"
```

### Schedule

در [`app/Console/Kernel.php`](../../app/Console/Kernel.php):

```php
$schedule->command(SendPrayerWeeklyReports::class, ['--limit=2'])
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```

### گزارش هفتگی برای کاربرانی که ایمیلشان verify شده

برای اینکه به کاربرانی که در ربات ایمیل ثبت و تأیید کرده‌اند گزارش هفتگی ارسال شود، روی سرور باید **کران‌جاب** `php artisan schedule:run` (و در صورت استفاده از صف، `queue:work`) طبق بخش **Cron Jobs** در [README.md](../../README.md) ست شود. جزئیات کامل (چیکار کنیم، چطور کران بزنیم، صف و غیره) در [راهنمای کامل ربات نماز قضا - بخش ارسال گزارش هفتگی](./PRAYER_BOT_README.md#4-ارسال-گزارش-هفتگی) آمده است.

## نصب و راه‌اندازی

### 1. Migration
```bash
php artisan migrate
```

### 2. Seeder
```bash
php artisan db:seed --class=PrayerBotWebhookEndpointSeeder
```

### 3. تنظیم Webhook

#### برای تلگرام:
```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/api/webhook-prayer-bot?origin=telegram&bot_mother_id=1"
```

#### برای بله:
```bash
curl -X POST "https://tapi.bale.ai/bot<TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/api/webhook-prayer-bot?origin=bale&bot_mother_id=1"
```

### 4. تست Webhook
```bash
php artisan tinker
>>> BotHelper::checkWebhookInfo('<TOKEN>', 'telegram');
```

### 5. اجرای Queue Worker
```bash
php artisan queue:work --tries=3
```

### 6. تست ارسال ایمیل
```bash
php artisan prayer:send-weekly-reports --limit=1 --force
```

## تست

### تست دستی
1. به ربات `/start` بفرستید
2. عدد 2 را بفرستید
3. بررسی کنید که پیام تایید دریافت شد
4. دستور `/stats` را بفرستید
5. دستور `/remove_<id>` را امتحان کنید

### تست ایمیل
```bash
# ارسال برای 1 کاربر به صورت اجباری
php artisan prayer:send-weekly-reports --limit=1 --force
```

### لاگ‌ها
تمام عملیات در `storage/logs/laravel.log` ثبت می‌شوند با prefix:
- `🕌 [PrayerBot]`
- `📧 [SendPrayerReportEmailJob]`
- `📊 [PrayerBotService]`

## امنیت

### محدودیت‌ها
- حداکثر 2 ایمیل در ساعت
- حداکثر 48 ایمیل در روز (24 ساعت × 2)
- حداکثر ~1400 ایمیل در ماه

### Unsubscribe
کاربران می‌توانند با کلیک روی لینک در ایمیل، اشتراک خود را لغو کنند. این لینک استاندارد است و در هدر `List-Unsubscribe` نیز قرار دارد.

## توسعه آینده

- [ ] پشتیبانی از گروه‌ها
- [ ] نمودار پیشرفت
- [ ] یادآوری روزانه
- [ ] صدور گواهینامه پیشرفت
- [ ] امکان اشتراک‌گذاری آمار

## مشکلات رایج (Troubleshooting)

### ربات پاسخ نمی‌دهد
1. بررسی webhook: `BotHelper::checkWebhookInfo()`
2. بررسی لاگ‌ها در `storage/logs/laravel.log`
3. بررسی اینکه `origin` و `bot_mother_id` در URL وجود دارند

### ایمیل ارسال نمی‌شود
1. بررسی تنظیمات `.env`
2. اجرای Queue Worker
3. بررسی جدول `email_report_queue`
4. بررسی جدول `failed_jobs`

### تشخیص نوع نماز اشتباه است
- نوع نماز بر اساس ساعت سرور محاسبه می‌شود
- می‌توانید با ارسال کلمه کلیدی (مثل "صبح") نوع را مشخص کنید

## مجوز

این فیچر بخشی از پروژه Bot Generator است و تحت همان مجوز منتشر می‌شود.

## تماس و پشتیبانی

در صورت بروز مشکل یا سوال:
- GitHub Issues
- ایمیل: support@example.com

---

**تاریخ ایجاد:** 1404/10/16  
**نسخه:** 1.0.0  
**وضعیت:** ✅ فعال و آماده استفاده
