---
name: بهبود سیستم ایمیل و Threshold Management
overview: پیاده‌سازی سیستم مدیریت Threshold برای ایمیل‌ها، بهبود پیام‌های تنظیمات، سیستم اینتروال و باکس‌بندی برای ارسال تدریجی، و Command Line Tools برای تست و مدیریت
todos:
  - id: "1"
    content: ایجاد Migration و Model برای EmailThresholdSetting
    status: completed
  - id: "2"
    content: ایجاد EmailThresholdService برای مدیریت Threshold
    status: completed
    dependencies:
      - "1"
  - id: "3"
    content: ایجاد EmailSchedulingService برای باکس‌بندی و اینتروال
    status: completed
    dependencies:
      - "1"
  - id: "4"
    content: بهبود handleEmailCommand در PrayerBotController برای پیام کوتاه
    status: completed
  - id: "5"
    content: بهبود SendPrayerWeeklyReports با Threshold و باکس‌بندی
    status: completed
    dependencies:
      - "2"
      - "3"
  - id: "6"
    content: ایجاد EmailAdminHelper برای ارسال پیام به ادمین‌ها
    status: completed
  - id: "7"
    content: ایجاد Command TestAdminNotification
    status: completed
    dependencies:
      - "6"
  - id: "8"
    content: ایجاد Command TestEmailSending
    status: completed
    dependencies:
      - "2"
  - id: "9"
    content: ایجاد Command ManageEmailAdmin
    status: completed
  - id: "10"
    content: ایجاد Command TestWeeklyReportJob
    status: completed
    dependencies:
      - "5"
  - id: "11"
    content: به‌روزرسانی Kernel.php برای Schedule های جدید
    status: completed
    dependencies:
      - "5"
  - id: "12"
    content: ایجاد Seeder برای مقادیر پیش‌فرض Threshold
    status: completed
    dependencies:
      - "1"
  - id: "13"
    content: ایجاد مستندات EMAIL_THRESHOLD_GUIDE.md
    status: completed
---

# پلن پیاده‌سازی: بهبود سیستم ایمیل و Threshold Management

## 1. بهبود پیام‌های تنظیمات ایمیل

### فایل: `app/Http/Controllers/PrayerBotController.php`

**تغییرات:**

- در متد `handleEmailCommand`: چک کردن اینکه آیا کاربر قبلاً ایمیل ست و verify کرده است
- اگر ایمیل ست شده: نمایش پیام کوتاه با دکمه‌های "تغییر ایمیل" و "لغو اشتراک"
- اگر ایمیل ست نشده: نمایش پیام کامل راهنما (متن فعلی)

**کد:**

```php
protected function handleEmailCommand(Telegram $bot, int $chatId, $botUser, string $type, int $botMotherId): void
{
    // اگر ایمیل ست و verify شده است
    if ($botUser->email && $botUser->email_verified_at) {
        $message = "✅ ایمیل شما تنظیم شده است:\n";
        $message .= "📧 {$botUser->email}\n\n";
        $message .= "اگر می‌خواهید تغییر دهید، از دکمه‌های زیر استفاده کنید:";
        
        $keyboard = [
            [
                ['text' => '✏️ تغییر ایمیل', 'callback_data' => 'email_change'],
                ['text' => '🔕 لغو اشتراک', 'callback_data' => 'email_unsubscribe']
            ]
        ];
        
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        return;
    }
    
    // پیام کامل راهنما (کد فعلی)
    // ...
}
```

---

## 2. ایجاد جدول Threshold Settings

### Migration: `database/migrations/YYYY_MM_DD_create_email_threshold_settings_table.php`

**ساختار جدول:**

- `id`: primary key
- `threshold_type`: enum('daily', 'weekly', 'monthly')
- `max_emails`: integer (مقدار پیش‌فرض: Daily=100, Weekly=500, Monthly=2000)
- `current_count`: integer (تعداد فعلی ارسال شده)
- `reset_at`: datetime (زمان reset شدن)
- `created_at`, `updated_at`

### Model: `app/Models/EmailThresholdSetting.php`

**متدها:**

- `checkThreshold(string $type): bool` - چک کردن آیا می‌توان ایمیل ارسال کرد
- `incrementCount(string $type): void` - افزایش تعداد
- `resetCount(string $type): void` - reset کردن در ابتدای دوره جدید
- `getCurrentCount(string $type): int` - دریافت تعداد فعلی

---

## 3. Service برای مدیریت Threshold

### فایل: `app/Services/EmailThresholdService.php`

**مسئولیت‌ها:**

- چک کردن Threshold قبل از ارسال
- افزایش تعداد بعد از ارسال موفق
- Reset خودکار در ابتدای دوره جدید
- ارسال پیام به ادمین‌ها در صورت رد شدن از Threshold

**متدها:**

```php
public function canSendEmail(string $type = 'daily'): bool
public function incrementSentCount(string $type = 'daily'): void
public function notifyAdminsIfThresholdExceeded(string $type, int $attemptedCount): void
```

---

## 4. سیستم اینتروال و باکس‌بندی

### فایل: `app/Services/EmailSchedulingService.php`

**مسئولیت‌ها:**

- محاسبه کاربرانی که باید ایمیل دریافت کنند (یک هفته از آخرین ایمیل گذشته)
- تقسیم به باکس‌های 5-10 تایی
- زمان‌بندی ارسال با اینتروال‌های قابل تنظیم

**متدها:**

```php
public function getEligibleUsers(int $batchSize = 10): Collection
public function scheduleBatchEmails(Collection $users, int $intervalMinutes = 30): void
```

### تنظیمات در `.env`:

```env
EMAIL_BATCH_SIZE=10
EMAIL_INTERVAL_MINUTES=30
EMAIL_INTERVALS=10,30,60  # اینتروال‌های ممکن (دقیقه)
```

---

## 5. بهبود Command ارسال گزارش‌های هفتگی

### فایل: `app/Console/Commands/SendPrayerWeeklyReports.php`

**تغییرات:**

- چک کردن Threshold قبل از ارسال
- استفاده از `EmailSchedulingService` برای باکس‌بندی
- ارسال تدریجی با اینتروال
- لاگ کردن اطلاعات Threshold

**پارامترهای جدید:**

- `--batch-size=10`: اندازه باکس
- `--interval=30`: اینتروال به دقیقه
- `--force-threshold`: نادیده گرفتن Threshold (فقط برای ادمین)

---

## 6. Command Line Tools

### 6.1 تست ارسال پیام به ادمین

**فایل:** `app/Console/Commands/TestAdminNotification.php`

```bash
php artisan email:test-admin-notification "پیام تست"
```

**عملکرد:**

- ارسال پیام به همه ادمین‌ها (از `AdminHelper::getAdmins()`)
- پشتیبانی از Telegram و Bale
- نمایش نتیجه در Console

### 6.2 تست ارسال واقعی ایمیل

**فایل:** `app/Console/Commands/TestEmailSending.php`

```bash
php artisan email:test-sending --email=test@example.com --type=verification
php artisan email:test-sending --email=test@example.com --type=weekly-report
php artisan email:test-sending --email=test@example.com --type=motivational
```

**عملکرد:**

- ارسال واقعی ایمیل (نه Sandbox)
- چک کردن Threshold
- نمایش نتیجه

### 6.3 پیدا کردن و ست کردن ادمین

**فایل:** `app/Console/Commands/ManageEmailAdmin.php`

```bash
# پیدا کردن ادمین فعلی
php artisan email:admin-find

# ست کردن ادمین جدید
php artisan email:admin-set --chat-id=123456 --type=telegram
```

**عملکرد:**

- پیدا کردن ادمین از `BotUsers` بر اساس email
- ست کردن chat_id برای ادمین
- ذخیره در جدول یا .env

### 6.4 تست Job هفتگی

**فایل:** `app/Console/Commands/TestWeeklyReportJob.php`

```bash
php artisan email:test-weekly-job --user-id=1 --force
```

**عملکرد:**

- اجرای Job برای یک کاربر خاص
- تست کامل فرآیند ارسال
- نمایش لاگ‌ها

---

## 7. ارسال پیام به ادمین‌ها

### Helper: `app/Helpers/EmailAdminHelper.php`

**متدها:**

```php
public static function sendToAllAdmins(string $message, string $type = 'telegram'): void
public static function sendThresholdExceededNotification(string $thresholdType, int $attemptedCount, int $maxCount): void
```

**استفاده:**

- در `EmailThresholdService` وقتی Threshold رد می‌شود
- در `SendPrayerWeeklyReports` برای گزارش‌های مهم

---

## 8. بهبود Scheduler

### فایل: `app/Console/Kernel.php`

**تغییرات:**

- اضافه کردن چندین Schedule برای اینتروال‌های مختلف
- استفاده از `EmailSchedulingService`
```php
// هر 10 دقیقه (باکس کوچک)
$schedule->command(SendPrayerWeeklyReports::class, ['--batch-size=5', '--interval=10'])
    ->everyTenMinutes()
    ->withoutOverlapping();

// هر 30 دقیقه (باکس متوسط)
$schedule->command(SendPrayerWeeklyReports::class, ['--batch-size=10', '--interval=30'])
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// هر ساعت (باکس بزرگ)
$schedule->command(SendPrayerWeeklyReports::class, ['--batch-size=10', '--interval=60'])
    ->hourly()
    ->withoutOverlapping();
```


---

## 9. Migration و Seeder

### Migration برای Threshold Settings

**فایل:** `database/migrations/YYYY_MM_DD_create_email_threshold_settings_table.php`

### Seeder برای مقادیر پیش‌فرض

**فایل:** `database/seeders/EmailThresholdSettingsSeeder.php`

**مقادیر پیش‌فرض:**

- Daily: 100
- Weekly: 500
- Monthly: 2000

---

## 10. مستندات

### فایل: `docs/EMAIL_THRESHOLD_GUIDE.md`

**محتوای مستندات:**

- توضیح سیستم Threshold
- نحوه تنظیم اینتروال‌ها
- نحوه استفاده از Command Line Tools
- Troubleshooting

---

## فایل‌های ایجاد/تغییر

### فایل‌های جدید:

1. `app/Models/EmailThresholdSetting.php`
2. `app/Services/EmailThresholdService.php`
3. `app/Services/EmailSchedulingService.php`
4. `app/Helpers/EmailAdminHelper.php`
5. `app/Console/Commands/TestAdminNotification.php`
6. `app/Console/Commands/TestEmailSending.php`
7. `app/Console/Commands/ManageEmailAdmin.php`
8. `app/Console/Commands/TestWeeklyReportJob.php`
9. `database/migrations/YYYY_MM_DD_create_email_threshold_settings_table.php`
10. `database/seeders/EmailThresholdSettingsSeeder.php`
11. `docs/EMAIL_THRESHOLD_GUIDE.md`

### فایل‌های تغییر:

1. `app/Http/Controllers/PrayerBotController.php` - بهبود پیام‌های تنظیمات
2. `app/Console/Commands/SendPrayerWeeklyReports.php` - اضافه کردن Threshold و باکس‌بندی
3. `app/Console/Kernel.php` - اضافه کردن Schedule های جدید

---

## ترتیب پیاده‌سازی

1. ایجاد Migration و Model برای Threshold
2. ایجاد Service های Threshold و Scheduling
3. بهبود Controller برای پیام‌های تنظیمات
4. بهبود Command ارسال گزارش‌ها
5. ایجاد Command Line Tools
6. ایجاد Helper برای ادمین‌ها
7. به‌روزرسانی Scheduler
8. ایجاد Seeder و مستندات
9. تست کامل

---

## نکات مهم

- Threshold ها در دیتابیس ذخیره می‌شوند (قابل تغییر از Admin Panel در آینده)
- اینتروال‌ها و اندازه باکس از `.env` خوانده می‌شوند
- همه ادمین‌ها از `AdminHelper::getAdmins()` دریافت می‌شوند
- پیام‌های Threshold به همه ادمین‌ها ارسال می‌شود
- Command Line Tools برای تست و دیباگ در دسترس هستند