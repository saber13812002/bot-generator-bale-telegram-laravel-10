# راهنمای تست کامل Email Service - مرحله به مرحله

این راهنما به صورت کامل و مرحله به مرحله نحوه تست ماژول Email Service را توضیح می‌دهد.

## 📋 پیش‌نیازها

1. تنظیمات `.env` کامل شده باشد
2. Cache پاک شده باشد
3. Service Provider به‌روزرسانی شده باشد

---

## 🔧 مرحله 1: بررسی تنظیمات

### 1.1 بررسی .env

```bash
# بررسی تنظیمات Mailtrap
cat .env | grep MAILTRAP
cat .env | grep MAIL_FROM
```

**خروجی مورد انتظار:**
```
MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338
MAILTRAP_INBOX_ID=1439975
MAILTRAP_USE_SANDBOX=true
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="Bots"
```

### 1.2 پاک کردن Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 1.3 بررسی Service Provider

```bash
# بررسی اینکه EmailService bind شده است
php artisan tinker
>>> app(App\Interfaces\Services\EmailService::class)
# باید MailtrapEmailServiceImpl را نمایش دهد
>>> exit
```

---

## 🧪 مرحله 2: تست اتصال

### 2.1 تست Connection

```bash
php artisan email:test connection
```

**خروجی مورد انتظار:**
```
🧪 تست ماژول Email Service
📦 نوع: connection

🔍 در حال بررسی اتصال...
✅ اتصال موفق است!
```

### 2.2 در صورت خطا

اگر خطا دریافت کردید:

```bash
# بررسی لاگ‌ها
tail -n 50 storage/logs/laravel.log | grep -i "email\|mailtrap"

# بررسی تنظیمات
php artisan tinker
>>> env('MAILTRAP_API_TOKEN')
>>> env('MAILTRAP_USE_SANDBOX')
```

---

## 📧 مرحله 3: تست ایمیل تایید

### 3.1 ارسال ایمیل تایید

```bash
php artisan email:test verification test@example.com --code=123456
```

**خروجی مورد انتظار:**
```
🧪 تست ماژول Email Service
📦 نوع: verification

📧 ارسال ایمیل تایید به: test@example.com
🔑 کد تایید: 123456
✅ ایمیل با موفقیت ارسال شد!
```

### 3.2 بررسی در Mailtrap

1. به [Mailtrap.io](https://mailtrap.io) بروید
2. وارد Sandbox شوید
3. ایمیل جدید را بررسی کنید
4. کد تایید را چک کنید

### 3.3 تست با ایمیل واقعی

```bash
php artisan email:test verification saber.tabatabaee@gmail.com
```

---

## 📊 مرحله 4: تست گزارش هفتگی

### 4.1 ارسال گزارش هفتگی

```bash
php artisan email:test weekly-report test@example.com
```

**خروجی مورد انتظار:**
```
🧪 تست ماژول Email Service
📦 نوع: weekly-report

📊 ارسال گزارش هفتگی به: test@example.com
✅ گزارش هفتگی با موفقیت ارسال شد!
📋 تمپلیت: v3
```

### 4.2 بررسی در Mailtrap

1. ایمیل جدید را در Sandbox بررسی کنید
2. محتوای HTML را چک کنید
3. لینک لغو اشتراک را تست کنید

---

## 🌟 مرحله 5: تست ایمیل انگیزشی

### 5.1 ارسال ایمیل انگیزشی

```bash
php artisan email:test motivational test@example.com
```

**خروجی مورد انتظار:**
```
🧪 تست ماژول Email Service
📦 نوع: motivational

🌟 ارسال ایمیل انگیزشی به: test@example.com
✅ ایمیل انگیزشی با موفقیت ارسال شد!
```

### 5.2 بررسی در Mailtrap

1. ایمیل را در Sandbox بررسی کنید
2. محتوای پیام را چک کنید

---

## 🤖 مرحله 6: تست در ربات

### 6.1 تست در PrayerBot

1. ربات را باز کنید
2. دستور `/start` را بزنید
3. به بخش **تنظیمات ایمیل** بروید
4. ایمیل خود را وارد کنید
5. کد تایید را دریافت کنید

### 6.2 بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های زنده
tail -f storage/logs/laravel.log | grep -i "email\|prayerbot"

# جستجوی خطاها
grep "ERROR.*Email" storage/logs/laravel.log | tail -20
```

### 6.3 تست گزارش هفتگی در ربات

```bash
# ارسال گزارش تستی
php artisan prayer:send-weekly-reports --limit=1 --force
```

---

## 🔍 مرحله 7: دیباگ و عیب‌یابی

### 7.1 بررسی Dependency Injection

```bash
php artisan tinker
```

```php
// بررسی اینکه EmailService به درستی inject می‌شود
>>> $service = app(App\Interfaces\Services\EmailService::class);
>>> get_class($service);
// باید: App\Services\MailtrapEmailServiceImpl

// تست اتصال
>>> $service->testConnection();
// باید: true

exit;
```

### 7.2 بررسی در Controller

```bash
php artisan tinker
```

```php
// تست در Controller
>>> $controller = app(App\Http\Controllers\PrayerBotController::class);
>>> get_class($controller->emailService);
// باید: App\Services\MailtrapEmailServiceImpl

exit;
```

### 7.3 بررسی در Job

```bash
php artisan tinker
```

```php
// تست Job
>>> $job = new App\Jobs\SendPrayerReportEmailJob(1, [], 'test@example.com', 'token');
>>> // Job باید EmailService را از DI دریافت کند

exit;
```

---

## ✅ چک‌لیست تست کامل

### تنظیمات
- [ ] `.env` تنظیم شده است
- [ ] Cache پاک شده است
- [ ] Service Provider به‌روزرسانی شده است

### تست Command Line
- [ ] تست Connection موفق است
- [ ] تست Verification موفق است
- [ ] تست Weekly Report موفق است
- [ ] تست Motivational موفق است

### تست در Mailtrap
- [ ] ایمیل تایید در Sandbox نمایش داده می‌شود
- [ ] گزارش هفتگی در Sandbox نمایش داده می‌شود
- [ ] ایمیل انگیزشی در Sandbox نمایش داده می‌شود

### تست در ربات
- [ ] ربات می‌تواند ایمیل تایید ارسال کند
- [ ] کاربر کد تایید را دریافت می‌کند
- [ ] گزارش هفتگی ارسال می‌شود
- [ ] لاگ‌ها بدون خطا هستند

### Dependency Injection
- [ ] EmailService در Controller inject می‌شود
- [ ] EmailService در Job inject می‌شود
- [ ] همه استفاده‌ها از Interface هستند

---

## 🆘 عیب‌یابی مشکلات رایج

### مشکل 1: "Class not found"

**علت:** Service Provider به‌روزرسانی نشده

**راه‌حل:**
```bash
php artisan config:clear
composer dump-autoload
```

### مشکل 2: "Interface cannot be instantiated"

**علت:** EmailService bind نشده است

**راه‌حل:**
```php
// در AppServiceProvider
$this->app->bind(EmailService::class, MailtrapEmailServiceImpl::class);
```

### مشکل 3: "Method not found"

**علت:** از کلاس قدیمی استفاده می‌شود

**راه‌حل:**
```php
// ❌ اشتباه
$service = new MailtrapEmailService();

// ✅ درست
$service = app(EmailService::class);
```

### مشکل 4: "View not found"

**علت:** View وجود ندارد

**راه‌حل:**
```bash
# بررسی وجود View
ls resources/views/emails/

# باید این فایل‌ها وجود داشته باشند:
# - email-verification.blade.php
# - prayer-weekly-report-v1.blade.php (تا v5)
# - motivational.blade.php
```

---

## 📊 خلاصه تست

```bash
# 1. تست اتصال
php artisan email:test connection

# 2. تست ایمیل تایید
php artisan email:test verification test@example.com

# 3. تست گزارش هفتگی
php artisan email:test weekly-report test@example.com

# 4. تست ایمیل انگیزشی
php artisan email:test motivational test@example.com

# 5. تست در ربات
# (دستورات ربات را اجرا کنید)

# 6. بررسی لاگ‌ها
tail -f storage/logs/laravel.log | grep -i "email"
```

---

**آخرین به‌روزرسانی:** 2026-01-08
