# راهنمای سیستم Threshold برای ایمیل

این راهنما توضیح می‌دهد که چگونه سیستم Threshold برای مدیریت ارسال ایمیل کار می‌کند.

## 📋 فهرست مطالب

1. [معرفی](#معرفی)
2. [نحوه کار](#نحوه-کار)
3. [تنظیمات](#تنظیمات)
4. [اینتروال‌ها و باکس‌بندی](#اینتروالها-و-باکسبندی)
5. [Command Line Tools](#command-line-tools)
6. [عیب‌یابی](#عیب‌یابی)

---

## 🎯 معرفی

سیستم Threshold برای جلوگیری از ارسال بیش از حد ایمیل طراحی شده است. این سیستم سه نوع Threshold دارد:

- **Daily (روزانه)**: حداکثر 100 ایمیل در روز
- **Weekly (هفتگی)**: حداکثر 500 ایمیل در هفته
- **Monthly (ماهانه)**: حداکثر 2000 ایمیل در ماه

---

## ⚙️ نحوه کار

### 1. چک کردن Threshold

قبل از ارسال هر ایمیل، سیستم چک می‌کند که آیا می‌توان ایمیل ارسال کرد:

```php
$thresholdService = app(EmailThresholdService::class);
if ($thresholdService->canSendEmail('daily')) {
    // ارسال ایمیل
} else {
    // ارسال متوقف می‌شود
}
```

### 2. افزایش تعداد

بعد از ارسال موفق، تعداد افزایش می‌یابد:

```php
$thresholdService->incrementSentCount('daily');
```

### 3. Reset خودکار

Threshold ها به صورت خودکار reset می‌شوند:
- **Daily**: هر روز در نیمه شب
- **Weekly**: هر هفته در ابتدای هفته
- **Monthly**: هر ماه در ابتدای ماه

### 4. هشدار به ادمین

وقتی Threshold رد می‌شود، پیام هشدار به همه ادمین‌ها ارسال می‌شود.

---

## 🔧 تنظیمات

### تنظیمات در دیتابیس

Threshold ها در جدول `email_threshold_settings` ذخیره می‌شوند.

**مقادیر پیش‌فرض:**
- Daily: 100
- Weekly: 500
- Monthly: 2000

**تغییر مقادیر:**

```php
use App\Models\EmailThresholdSetting;

// تغییر حداکثر Daily
$setting = EmailThresholdSetting::where('threshold_type', 'daily')->first();
$setting->update(['max_emails' => 200]);
```

### تنظیمات در .env

```env
# اندازه باکس ایمیل
EMAIL_BATCH_SIZE=10

# اینتروال پیش‌فرض (دقیقه)
EMAIL_INTERVAL_MINUTES=30

# اینتروال‌های مجاز (جدا شده با کاما)
EMAIL_INTERVALS=10,30,60
```

---

## 📦 اینتروال‌ها و باکس‌بندی

### اینتروال‌ها

سیستم از سه اینتروال استفاده می‌کند:

1. **10 دقیقه**: برای باکس‌های کوچک (5 ایمیل)
2. **30 دقیقه**: برای باکس‌های متوسط (10 ایمیل)
3. **60 دقیقه**: برای باکس‌های بزرگ (10 ایمیل)

### Scheduler

در `app/Console/Kernel.php` سه Schedule تعریف شده است:

```php
// هر 10 دقیقه
$schedule->command(SendPrayerWeeklyReports::class, [
    '--batch-size=5',
    '--interval=10'
])->everyTenMinutes();

// هر 30 دقیقه
$schedule->command(SendPrayerWeeklyReports::class, [
    '--batch-size=10',
    '--interval=30'
])->everyThirtyMinutes();

// هر ساعت
$schedule->command(SendPrayerWeeklyReports::class, [
    '--batch-size=10',
    '--interval=60'
])->hourly();
```

---

## 🛠️ Command Line Tools

### 1. تست ارسال پیام به ادمین

```bash
php artisan email:test-admin-notification "پیام تست"
```

**گزینه‌ها:**
- `--type=telegram`: فقط Telegram
- `--type=bale`: فقط Bale
- `--type=both`: هر دو (پیش‌فرض)

### 2. تست ارسال واقعی ایمیل

```bash
# ایمیل تایید
php artisan email:test-sending --email=test@example.com --type=verification

# گزارش هفتگی
php artisan email:test-sending --email=test@example.com --type=weekly-report

# ایمیل انگیزشی
php artisan email:test-sending --email=test@example.com --type=motivational
```

**گزینه‌ها:**
- `--force-threshold`: نادیده گرفتن Threshold

### 3. مدیریت ادمین

```bash
# پیدا کردن ادمین‌ها
php artisan email:admin find

# پیدا کردن بر اساس ایمیل
php artisan email:admin find --email=admin@example.com

# ست کردن ادمین
php artisan email:admin set --chat-id=123456 --type=telegram
```

### 4. تست Job هفتگی

```bash
# تست با User ID
php artisan email:test-weekly-job --user-id=1

# تست با Email
php artisan email:test-weekly-job --email=test@example.com

# تست با اولین کاربر واجد شرایط
php artisan email:test-weekly-job

# نادیده گرفتن زمان آخرین ایمیل
php artisan email:test-weekly-job --force
```

### 5. ارسال گزارش‌های هفتگی

```bash
# ارسال با تنظیمات پیش‌فرض
php artisan prayer:send-weekly-reports

# ارسال با باکس بزرگ
php artisan prayer:send-weekly-reports --batch-size=20 --interval=60

# نادیده گرفتن Threshold
php artisan prayer:send-weekly-reports --force-threshold
```

---

## 🔍 عیب‌یابی

### مشکل 1: Threshold همیشه رد می‌شود

**علت:** ممکن است Threshold reset نشده باشد.

**راه‌حل:**
```php
use App\Models\EmailThresholdSetting;

// Reset دستی
EmailThresholdSetting::resetCount('daily');
```

### مشکل 2: پیام به ادمین نمی‌رسد

**علت:** ممکن است Chat ID ادمین‌ها در `.env` تنظیم نشده باشد.

**راه‌حل:**
```bash
# تست ارسال پیام
php artisan email:test-admin-notification "تست"

# بررسی Chat ID ها
php artisan email:admin find
```

### مشکل 3: ایمیل‌ها ارسال نمی‌شوند

**علت:** ممکن است Threshold رد شده باشد.

**راه‌حل:**
```bash
# چک کردن Threshold
php artisan tinker
>>> app(App\Services\EmailThresholdService::class)->getThresholdInfo('daily');

# نادیده گرفتن Threshold (فقط برای تست)
php artisan prayer:send-weekly-reports --force-threshold
```

### مشکل 4: Scheduler کار نمی‌کند

**علت:** ممکن است Cron Job تنظیم نشده باشد.

**راه‌حل:**
```bash
# اضافه کردن به crontab
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📊 مانیتورینگ

### مشاهده آمار Threshold

```php
use App\Services\EmailThresholdService;

$service = app(EmailThresholdService::class);

// اطلاعات Daily
$info = $service->getThresholdInfo('daily');
echo "Current: {$info['current']} / {$info['max']}\n";
echo "Remaining: {$info['remaining']}\n";
echo "Percentage: {$info['percentage']}%\n";
```

### مشاهده لاگ‌ها

```bash
# لاگ‌های Threshold
tail -f storage/logs/laravel.log | grep -i "threshold"

# لاگ‌های ارسال ایمیل
tail -f storage/logs/laravel.log | grep -i "email"
```

---

## ✅ چک‌لیست

- [ ] Migration اجرا شده است
- [ ] Seeder اجرا شده است
- [ ] Threshold ها در دیتابیس تنظیم شده‌اند
- [ ] تنظیمات `.env` کامل است
- [ ] Scheduler در `Kernel.php` تنظیم شده است
- [ ] Cron Job فعال است
- [ ] تست Command Line Tools موفق است

---

**آخرین به‌روزرسانی:** 2026-01-21
