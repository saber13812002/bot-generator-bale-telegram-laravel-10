# راهنمای تنظیم Mailtrap برای Production (ارسال واقعی ایمیل)

این راهنما نحوه انتقال از Mailtrap Sandbox به Transactional API برای ارسال واقعی ایمیل در Production را توضیح می‌دهد.

## 📋 تفاوت Sandbox و Transactional

### Sandbox (تست)
- ✅ ایمیل‌ها در Mailtrap ذخیره می‌شوند
- ✅ برای تست و توسعه مناسب است
- ❌ ایمیل‌ها واقعاً ارسال نمی‌شوند
- ✅ رایگان

### Transactional (Production)
- ✅ ایمیل‌ها واقعاً ارسال می‌شوند
- ✅ برای محیط Production مناسب است
- ✅ نیاز به verify کردن دامنه دارد
- 💰 پولی (اما پلن رایگان 500 ایمیل/ماه دارد)

---

## 🚀 مراحل انتقال به Production

### مرحله 1: ایجاد حساب Mailtrap Transactional

1. به [Mailtrap.io](https://mailtrap.io) بروید
2. وارد حساب کاربری شوید
3. به بخش **Email API/SMTP** بروید
4. روی **Get Started** کلیک کنید

### مرحله 2: Verify کردن دامنه

1. در بخش **Domains**، روی **Add Domain** کلیک کنید
2. دامنه خود را وارد کنید (مثلاً: `pardisania.ir`)
3. رکوردهای DNS را اضافه کنید:
   - **SPF Record**: برای احراز هویت فرستنده
   - **DKIM Record**: برای امضای دیجیتال
   - **DMARC Record**: برای محافظت از دامنه

#### مثال رکوردهای DNS:

```
# SPF Record
Type: TXT
Name: @
Value: v=spf1 include:mailtrap.io ~all

# DKIM Record
Type: TXT
Name: mailtrap._domainkey
Value: (مقدار ارائه شده توسط Mailtrap)

# DMARC Record (اختیاری)
Type: TXT
Name: _dmarc
Value: v=DMARC1; p=none; rua=mailto:dmarc@pardisania.ir
```

4. منتظر بمانید تا Mailtrap دامنه را verify کند (معمولاً 5-10 دقیقه)

### مرحله 3: دریافت API Token برای Transactional

1. بعد از verify شدن دامنه، به بخش **API Tokens** بروید
2. روی **Create Token** کلیک کنید
3. نام توکن را وارد کنید (مثلاً: `Production Token`)
4. توکن را کپی کنید (فقط یک بار نمایش داده می‌شود!)

**⚠️ مهم:** توکن را در جای امن ذخیره کنید.

### مرحله 4: به‌روزرسانی تنظیمات .env

```env
# Mailtrap API Configuration - Production
MAILTRAP_API_TOKEN=YOUR_TRANSACTIONAL_API_TOKEN
MAILTRAP_USE_SANDBOX=false
# MAILTRAP_INBOX_ID دیگر نیاز نیست برای Transactional

# Email From Address (باید دامنه verify شده باشد)
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="Bots"
```

### مرحله 5: پاک کردن Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### مرحله 6: تست ارسال واقعی

```bash
# تست با Transactional API
php artisan test:mailtrap-api saber.tabatabaee@gmail.com
```

**✅ بررسی موفقیت:**
- ایمیل باید واقعاً به صندوق ورودی `saber.tabatabaee@gmail.com` ارسال شود
- در Mailtrap، در بخش **Email Logs** می‌توانید وضعیت ارسال را ببینید

---

## 🔍 بررسی و دیباگ

### 1. بررسی وضعیت دامنه

در Mailtrap:
1. به بخش **Domains** بروید
2. دامنه خود را پیدا کنید
3. وضعیت باید **Verified** باشد

### 2. بررسی API Token

```bash
php artisan tinker
>>> env('MAILTRAP_API_TOKEN')
>>> env('MAILTRAP_USE_SANDBOX')
# باید false باشد
```

### 3. تست مستقیم با curl

```bash
curl -X POST 'https://send.api.mailtrap.io/api/send' \
  -H 'Authorization: Bearer YOUR_TRANSACTIONAL_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "from": {"email": "hello@pardisania.ir", "name": "Test"},
    "to": [{"email": "saber.tabatabaee@gmail.com"}],
    "subject": "Test Email from Production",
    "text": "This is a test email from production"
  }'
```

### 4. بررسی Email Logs در Mailtrap

1. به بخش **Email Logs** در Mailtrap بروید
2. تمام ایمیل‌های ارسال شده را می‌بینید
3. وضعیت ارسال (Sent, Delivered, Bounced) را بررسی کنید

---

## ⚠️ نکات مهم Production

### 1. محدودیت‌ها

- **پلن رایگان**: 500 ایمیل/ماه
- **پلن Starter**: 10,000 ایمیل/ماه ($15/ماه)
- **پلن Business**: 100,000 ایمیل/ماه ($50/ماه)

### 2. Rate Limiting

Mailtrap محدودیت نرخ ارسال دارد:
- **پلن رایگان**: 10 ایمیل/ثانیه
- **پلن Starter**: 100 ایمیل/ثانیه
- **پلن Business**: 1000 ایمیل/ثانیه

### 3. Best Practices

- ✅ همیشه از دامنه verify شده استفاده کنید
- ✅ از `MAIL_FROM_ADDRESS` دامنه verify شده استفاده کنید
- ✅ Rate limiting را در نظر بگیرید
- ✅ Email Logs را به صورت دوره‌ای بررسی کنید
- ✅ Bounce و Complaint را مانیتور کنید

### 4. Fallback Strategy

در صورت نیاز، می‌توانید از چند سرویس استفاده کنید:

```php
// مثال: استفاده از Mailtrap به عنوان primary و SMTP به عنوان fallback
try {
    $mailtrapService->sendEmail(...);
} catch (Exception $e) {
    // Fallback to SMTP
    Mail::to(...)->send(...);
}
```

---

## 🔄 بازگشت به Sandbox (برای تست)

اگر می‌خواهید دوباره به Sandbox برگردید:

```env
MAILTRAP_API_TOKEN=YOUR_SANDBOX_TOKEN
MAILTRAP_INBOX_ID=1439975
MAILTRAP_USE_SANDBOX=true
```

```bash
php artisan config:clear
```

---

## 📊 مانیتورینگ

### 1. بررسی Email Logs

در Mailtrap:
- **Sent**: ایمیل ارسال شده
- **Delivered**: ایمیل تحویل داده شده
- **Bounced**: ایمیل برگشت خورده
- **Complained**: کاربر ایمیل را به عنوان spam گزارش داده

### 2. بررسی لاگ‌های Laravel

```bash
# جستجوی لاگ‌های Mailtrap
grep "MailtrapEmailService" storage/logs/laravel.log | tail -20

# جستجوی خطاها
grep "MailtrapEmailService.*ERROR" storage/logs/laravel.log | tail -20
```

### 3. آمار ارسال

در Mailtrap:
- به بخش **Analytics** بروید
- آمار ارسال روزانه/هفتگی/ماهانه را ببینید
- نرخ تحویل (Delivery Rate) را بررسی کنید

---

## 🆘 عیب‌یابی

### خطا: "401 Unauthorized"

**علت:** توکن API نامعتبر است

**راه‌حل:**
1. بررسی کنید توکن Transactional است (نه Sandbox)
2. توکن را دوباره کپی کنید (بدون فاصله)
3. `php artisan config:clear` اجرا کنید

### خطا: "Domain not verified"

**علت:** دامنه verify نشده است

**راه‌حل:**
1. به Mailtrap بروید و وضعیت دامنه را بررسی کنید
2. رکوردهای DNS را دوباره بررسی کنید
3. منتظر بمانید تا verify شود (تا 24 ساعت)

### خطا: "Rate limit exceeded"

**علت:** بیش از حد مجاز ایمیل ارسال کرده‌اید

**راه‌حل:**
1. پلن خود را بررسی کنید
2. صبر کنید یا پلن را ارتقا دهید
3. Rate limiting را در کد پیاده‌سازی کنید

### خطا: "From address not verified"

**علت:** آدرس فرستنده از دامنه verify شده نیست

**راه‌حل:**
1. `MAIL_FROM_ADDRESS` باید از دامنه verify شده باشد
2. مثلاً اگر `pardisania.ir` verify شده، باید `hello@pardisania.ir` باشد

---

## ✅ چک‌لیست Production

- [ ] دامنه در Mailtrap verify شده است
- [ ] API Token برای Transactional دریافت شده است
- [ ] `.env` به‌روزرسانی شده است (`MAILTRAP_USE_SANDBOX=false`)
- [ ] Cache پاک شده است
- [ ] تست ارسال واقعی موفق بوده است
- [ ] Email Logs در Mailtrap بررسی شده است
- [ ] Rate limiting در نظر گرفته شده است
- [ ] مانیتورینگ تنظیم شده است

---

## 📚 منابع

- [Mailtrap Transactional Documentation](https://mailtrap.io/docs/transactional)
- [Mailtrap Domain Verification](https://mailtrap.io/docs/domains)
- [Mailtrap API Reference](https://mailtrap.io/api-docs)

---

**آخرین به‌روزرسانی:** 2026-01-08
