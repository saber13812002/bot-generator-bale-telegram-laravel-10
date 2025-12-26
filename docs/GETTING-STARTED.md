# 🚀 راهنمای شروع کار

این بخش شامل مراحل کامل نصب و راه‌اندازی پروژه از صفر تا اجرا است.

## 1️⃣ نصب PHP یا XAMPP

برای اجرای این پروژه نیاز به PHP 8.1 یا بالاتر دارید. می‌توانید یکی از روش‌های زیر را انتخاب کنید:

### روش 1: نصب XAMPP (پیشنهادی برای مبتدیان)

1. از [وب‌سایت رسمی XAMPP](https://www.apachefriends.org/) آخرین نسخه را دانلود کنید
2. XAMPP را نصب کنید (توصیه می‌شود در مسیر `C:\xampp` نصب شود)
3. XAMPP Control Panel را باز کنید
4. Apache و MySQL را Start کنید
5. PHP به صورت خودکار با XAMPP نصب می‌شود

### روش 2: نصب PHP به صورت مستقل

1. از [وب‌سایت رسمی PHP](https://www.php.net/downloads.php) نسخه 8.1 یا بالاتر را دانلود کنید
2. PHP را در مسیری مانند `C:\php` استخراج کنید
3. مسیر PHP را به متغیر محیطی PATH اضافه کنید
4. فایل `php.ini` را ویرایش کنید و extension های زیر را فعال کنید:
   - `extension=mbstring`
   - `extension=zip`
   - `extension=pdo_mysql`
   - `extension=curl`
   - `extension=openssl`

### بررسی نصب PHP

برای اطمینان از نصب صحیح PHP، در Command Prompt یا PowerShell دستور زیر را اجرا کنید:

```bash
php -v
```

باید نسخه PHP 8.1 یا بالاتر نمایش داده شود.

## 2️⃣ ریستور دیتابیس

1. فایل بکاپ دیتابیس (`.sql` یا `.dump`) را آماده کنید
2. XAMPP Control Panel را باز کنید و MySQL را Start کنید
3. به phpMyAdmin بروید: `http://localhost/phpmyadmin`
4. یک دیتابیس جدید ایجاد کنید (مثلاً `bot_platform`)
5. دیتابیس را انتخاب کنید و به تب Import بروید
6. فایل بکاپ را انتخاب کرده و Import را بزنید

**یا از طریق Command Line:**

```bash
mysql -u root -p bot_platform < database_backup.sql
```

**نکته:** اگر از XAMPP استفاده می‌کنید، ممکن است رمز عبور root خالی باشد. در این صورت:

```bash
mysql -u root bot_platform < database_backup.sql
```

## 3️⃣ نصب Composer

Composer یک ابزار مدیریت وابستگی‌ها برای PHP است که برای این پروژه ضروری است.

1. از [وب‌سایت رسمی Composer](https://getcomposer.org/download/) آخرین نسخه را دانلود کنید
2. فایل `Composer-Setup.exe` را اجرا کنید
3. در حین نصب، مسیر PHP را مشخص کنید (معمولاً `C:\xampp\php\php.exe`)
4. نصب را تکمیل کنید

### بررسی نصب Composer

```bash
composer --version
```

## 4️⃣ شروع کار

پس از نصب PHP و Composer، مراحل زیر را انجام دهید:

### مرحله 1: کلون کردن پروژه (اگر از Git استفاده می‌کنید)

```bash
git clone <repository-url>
cd bot-rad-git
```

### مرحله 2: نصب وابستگی‌ها با Composer

```bash
composer install
```

یا اگر می‌خواهید وابستگی‌های development را هم نصب کنید:

```bash
composer install --no-dev
```

### مرحله 3: تنظیم فایل محیطی

```bash
copy .env.example .env
```

سپس فایل `.env` را ویرایش کنید و اطلاعات دیتابیس را تنظیم کنید:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bot_platform
DB_USERNAME=root
DB_PASSWORD=
```

### مرحله 4: تولید کلید اپلیکیشن

```bash
php artisan key:generate
```

### مرحله 5: اجرای Migration ها

```bash
php artisan migrate
```

### مرحله 6: Seed کردن دیتابیس (اختیاری)

```bash
php artisan db:seed
```

### مرحله 7: ایجاد لینک Symbolic برای Storage

```bash
php artisan storage:link
```

### مرحله 8: اجرای سرور توسعه

```bash
php artisan serve
```

پروژه شما در آدرس `http://localhost:8000` در دسترس خواهد بود.

## ✅ بررسی نهایی

برای اطمینان از نصب صحیح، موارد زیر را بررسی کنید:

- ✅ PHP 8.1+ نصب شده است
- ✅ Composer نصب شده است
- ✅ دیتابیس ریستور شده است
- ✅ فایل `.env` تنظیم شده است
- ✅ Migration ها اجرا شده‌اند
- ✅ سرور Laravel در حال اجرا است

## 📝 نکات مهم

- اگر از XAMPP استفاده می‌کنید، مطمئن شوید که Apache و MySQL در XAMPP Control Panel در حال اجرا هستند
- در صورت بروز خطا، فایل `storage/logs/laravel.log` را بررسی کنید
- برای محیط Production، حتماً `APP_DEBUG=false` را در فایل `.env` تنظیم کنید

