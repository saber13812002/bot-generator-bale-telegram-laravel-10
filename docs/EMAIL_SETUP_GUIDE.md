# 📧 راهنمای تنظیمات ایمیل برای ربات نماز قضا

## 🔧 تنظیمات Gmail

### مرحله 1: ایجاد App Password در Gmail

1. وارد حساب Gmail خود شوید
2. به [Google Account Security](https://myaccount.google.com/security) بروید
3. در بخش "Signing in to Google"، روی "2-Step Verification" کلیک کنید
4. اگر فعال نیست، آن را فعال کنید
5. بعد از فعال‌سازی، به صفحه Security برگردید
6. در پایین صفحه، "App passwords" را پیدا کنید
7. روی "App passwords" کلیک کنید
8. یک نام برای برنامه انتخاب کنید (مثلاً: "Laravel Prayer Bot")
9. روی "Generate" کلیک کنید
10. کد 16 رقمی را کپی کنید (مثلاً: `abcd efgh ijkl mnop`)

### مرحله 2: تنظیمات `.env`

فایل `.env` را در ریشه پروژه باز کنید و تنظیمات زیر را اضافه یا به‌روزرسانی کنید:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**نکات مهم:**
- `MAIL_USERNAME`: آدرس ایمیل Gmail شما (مثلاً: `saber.tabatabaee@gmail.com`)
- `MAIL_PASSWORD`: کد 16 رقمی که از App Passwords کپی کردید (بدون فاصله)
- `MAIL_FROM_ADDRESS`: همان آدرس ایمیل Gmail شما
- `MAIL_FROM_NAME`: نام نمایشی فرستنده (می‌تواند `${APP_NAME}` باشد)

### مرحله 3: پاک کردن Cache

بعد از تغییر `.env`، حتماً cache را پاک کنید:

```bash
php artisan config:clear
php artisan cache:clear
```

### مرحله 4: تست ارسال ایمیل

از دستور تست استفاده کنید:

```bash
php artisan test:email-verification your-email@gmail.com
```

یا با کد خاص:

```bash
php artisan test:email-verification your-email@gmail.com --code=123456
```

اگر پیام "✅ ایمیل با موفقیت ارسال شد!" را دیدید، تنظیمات درست است!

## 🔍 عیب‌یابی

### خطا: "Connection could not be established"

**علت:** تنظیمات `MAIL_HOST` یا `MAIL_PORT` اشتباه است.

**راه‌حل:**
- مطمئن شوید `MAIL_HOST=smtp.gmail.com` است
- مطمئن شوید `MAIL_PORT=587` است
- اتصال به اینترنت را بررسی کنید

### خطا: "Authentication failed"

**علت:** `MAIL_USERNAME` یا `MAIL_PASSWORD` اشتباه است.

**راه‌حل:**
- مطمئن شوید از App Password استفاده می‌کنید (نه رمز عبور اصلی)
- مطمئن شوید فاصله‌ها در App Password حذف شده‌اند
- App Password جدید ایجاد کنید

### خطا: "Username is not set"

**علت:** `MAIL_USERNAME` در `.env` تنظیم نشده است.

**راه‌حل:**
- `MAIL_USERNAME` را در `.env` اضافه کنید
- بعد از تغییر، `php artisan config:clear` را اجرا کنید

## 📝 مثال کامل `.env`

```env
APP_NAME="Prayer Bot"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=saber.tabatabaee@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=saber.tabatabaee@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

## ✅ بررسی تنظیمات

برای بررسی تنظیمات فعلی، دستور تست را اجرا کنید:

```bash
php artisan test:email-verification test@example.com
```

این دستور تنظیمات فعلی را نمایش می‌دهد و تلاش می‌کند یک ایمیل تست ارسال کند.
