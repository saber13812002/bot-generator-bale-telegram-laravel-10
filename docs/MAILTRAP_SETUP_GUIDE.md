# راهنمای تنظیم و تست Mailtrap API

این راهنما نحوه تنظیم و استفاده از Mailtrap API برای ارسال ایمیل در ربات نماز قضا را توضیح می‌دهد.

## 📋 پیش‌نیازها

1. حساب کاربری Mailtrap (رایگان یا پولی)
2. API Token از Mailtrap
3. Inbox ID (برای Sandbox)

## 🔧 تنظیمات .env

```env
# Mailtrap API Configuration
MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338
MAILTRAP_INBOX_ID=1439975
MAILTRAP_USE_SANDBOX=true

# Email From Address
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="Bots"
```

### توضیحات:

- **MAILTRAP_API_TOKEN**: توکن API که از Mailtrap دریافت می‌کنید
- **MAILTRAP_INBOX_ID**: شناسه Inbox برای Sandbox (فقط برای حالت Sandbox)
- **MAILTRAP_USE_SANDBOX**: `true` برای Sandbox (تست) یا `false` برای Transactional (تولید)
- **MAIL_FROM_ADDRESS**: آدرس ایمیل فرستنده
- **MAIL_FROM_NAME**: نام فرستنده

## 🧪 تست تنظیمات

### 1. تست با Artisan Command

```bash
# تست Sandbox
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --token=5c2835343ece0cc635687a7047b5d338 --sandbox

# تست Transactional
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --token=YOUR_TRANSACTIONAL_TOKEN
```

### 2. تست با curl

```bash
# Sandbox
curl -X POST 'https://sandbox.api.mailtrap.io/api/send/1439975' \
  -H 'Api-Token: 5c2835343ece0cc635687a7047b5d338' \
  -H 'Content-Type: application/json' \
  -d '{
    "from": {"email": "hello@pardisania.ir", "name": "Test"},
    "to": [{"email": "saber.tabatabaee@gmail.com"}],
    "subject": "Test Email",
    "text": "This is a test email"
  }'

# Transactional
curl -X POST 'https://send.api.mailtrap.io/api/send' \
  -H 'Authorization: Bearer YOUR_TRANSACTIONAL_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "from": {"email": "hello@pardisania.ir", "name": "Test"},
    "to": [{"email": "saber.tabatabaee@gmail.com"}],
    "subject": "Test Email",
    "text": "This is a test email"
  }'
```

## 📧 استفاده در کد

### ارسال ایمیل تایید

```php
use App\Services\MailtrapEmailService;

$mailtrapService = new MailtrapEmailService();
$mailtrapService->sendVerificationEmail($email, $code);
```

### ارسال ایمیل عمومی

```php
use App\Services\MailtrapEmailService;

$mailtrapService = new MailtrapEmailService();
$mailtrapService->sendEmail(
    $to,
    $subject,
    $htmlBody,
    $textBody,
    'Category Name'
);
```

## 🔍 دیباگ و عیب‌یابی

### 1. بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های Laravel
tail -f storage/logs/laravel.log

# جستجوی خطاهای Mailtrap
grep "MailtrapEmailService" storage/logs/laravel.log
```

### 2. خطاهای رایج

#### خطا: "API Token تنظیم نشده است"
- **علت**: `MAILTRAP_API_TOKEN` در `.env` تنظیم نشده
- **راه‌حل**: توکن را در `.env` اضافه کنید و `php artisan config:clear` اجرا کنید

#### خطا: "MAILTRAP_INBOX_ID برای Sandbox ضروری است"
- **علت**: `MAILTRAP_USE_SANDBOX=true` اما `MAILTRAP_INBOX_ID` تنظیم نشده
- **راه‌حل**: Inbox ID را در `.env` اضافه کنید

#### خطا: "401 Unauthorized"
- **علت**: توکن API نامعتبر است
- **راه‌حل**: 
  - بررسی کنید توکن درست کپی شده باشد (بدون فاصله)
  - برای Transactional، دامنه باید verify شده باشد

#### خطا: "404 Not Found"
- **علت**: URL یا Inbox ID اشتباه است
- **راه‌حل**: 
  - برای Sandbox: `https://sandbox.api.mailtrap.io/api/send/{INBOX_ID}`
  - برای Transactional: `https://send.api.mailtrap.io/api/send`

### 3. بررسی تنظیمات

```bash
# بررسی تنظیمات Mailtrap
php artisan tinker
>>> config('mail.mailtrap')
>>> env('MAILTRAP_API_TOKEN')
>>> env('MAILTRAP_USE_SANDBOX')
```

## 📊 تفاوت Sandbox و Transactional

### Sandbox (تست)
- ✅ ایمیل‌ها در Mailtrap ذخیره می‌شوند
- ✅ برای تست و توسعه مناسب است
- ✅ نیاز به Inbox ID دارد
- ❌ ایمیل‌ها واقعاً ارسال نمی‌شوند

### Transactional (تولید)
- ✅ ایمیل‌ها واقعاً ارسال می‌شوند
- ✅ برای محیط تولید مناسب است
- ✅ نیاز به verify کردن دامنه دارد
- ❌ نیاز به Inbox ID ندارد

## 🚀 انتقال از Sandbox به Transactional

1. در Mailtrap، به بخش **Email API/SMTP** بروید
2. دامنه خود را verify کنید
3. API Token برای Transactional را دریافت کنید
4. در `.env` تغییر دهید:
   ```env
   MAILTRAP_USE_SANDBOX=false
   MAILTRAP_API_TOKEN=YOUR_TRANSACTIONAL_TOKEN
   ```
5. Cache را پاک کنید:
   ```bash
   php artisan config:clear
   ```

## 📝 سناریوی تست کامل

### مرحله 1: تنظیمات اولیه
```bash
# 1. اضافه کردن تنظیمات به .env
echo "MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338" >> .env
echo "MAILTRAP_INBOX_ID=1439975" >> .env
echo "MAILTRAP_USE_SANDBOX=true" >> .env
echo "MAIL_FROM_ADDRESS=hello@pardisania.ir" >> .env

# 2. پاک کردن cache
php artisan config:clear
```

### مرحله 2: تست دستور Artisan
```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --sandbox
```

### مرحله 3: تست در ربات
1. ربات را باز کنید
2. به بخش تنظیمات ایمیل بروید
3. ایمیل خود را وارد کنید
4. کد تایید را دریافت کنید
5. کد را در Mailtrap Sandbox بررسی کنید

### مرحله 4: تست گزارش هفتگی
```bash
# ارسال گزارش تستی
php artisan prayer:send-weekly-reports --limit=1
```

## 🔐 امنیت

- ✅ هرگز توکن API را در Git commit نکنید
- ✅ از `.env` برای ذخیره توکن استفاده کنید
- ✅ توکن‌ها را به صورت دوره‌ای rotate کنید
- ✅ برای تولید، از Transactional API استفاده کنید

## 📚 منابع

- [Mailtrap API Documentation](https://mailtrap.io/api-docs)
- [Mailtrap Sandbox Guide](https://mailtrap.io/docs/sandbox)
- [Mailtrap Transactional Guide](https://mailtrap.io/docs/transactional)
