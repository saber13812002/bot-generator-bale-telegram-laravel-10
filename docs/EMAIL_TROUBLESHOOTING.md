# 🔧 عیب‌یابی کامل تنظیمات ایمیل

## ❌ خطای فعلی: "Username and Password not accepted"

این خطا معمولاً به این دلایل رخ می‌دهد:
1. App Password اشتباه است یا منقضی شده
2. `MAIL_FROM_ADDRESS` در `.env` تنظیم نشده
3. تنظیمات cache نشده است

---

## ✅ راه‌حل گام‌به‌گام

### مرحله 1: ایجاد App Password جدید

1. به [لینک مستقیم App Passwords](https://myaccount.google.com/apppasswords) بروید
2. اگر لینک کار نمی‌کند:
   - به [Google Account Security](https://myaccount.google.com/security) بروید
   - روی "درستی‌سنجی دومرحله‌ای" کلیک کنید
   - در پایین صفحه، "App passwords" را پیدا کنید
3. یک نام انتخاب کنید (مثلاً: "Laravel Prayer Bot Server")
4. روی "Generate" کلیک کنید
5. **کد 16 رقمی را کپی کنید** (مثلاً: `abcd efgh ijkl mnop`)
6. **فاصله‌ها را حذف کنید** (مثلاً: `abcdefghijklmnop`)

---

### مرحله 2: تنظیمات `.env`

فایل `.env` را در سرور باز کنید و این تنظیمات را **کاملاً** اضافه یا به‌روزرسانی کنید:

```env
# Mail Configuration - Gmail با SSL (پورت 465)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=reyhane.taba@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=reyhane.taba@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**⚠️ نکات مهم:**
- `MAIL_PASSWORD`: App Password جدید که کپی کردید (بدون فاصله)
- `MAIL_FROM_ADDRESS`: **حتماً** باید همان آدرس ایمیل Gmail شما باشد (نه `hello@example.com`)
- `MAIL_USERNAME`: همان آدرس ایمیل Gmail شما

---

### مرحله 3: پاک کردن Cache

بعد از تغییر `.env`، **حتماً** این دستورات را اجرا کنید:

```bash
php artisan config:clear
php artisan cache:clear
```

---

### مرحله 4: تست

```bash
php artisan test:email-verification saber.tabatabaee@gmail.com
```

---

## 🔄 اگر هنوز کار نمی‌کند: استفاده از TLS (پورت 587)

گاهی اوقات پورت 465 با SSL مشکل دارد. می‌توانید از پورت 587 با TLS استفاده کنید:

```env
# Mail Configuration - Gmail با TLS (پورت 587)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=reyhane.taba@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=reyhane.taba@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

بعد از تغییر:
```bash
php artisan config:clear
php artisan test:email-verification saber.tabatabaee@gmail.com
```

---

## 🔍 بررسی تنظیمات فعلی

برای بررسی اینکه تنظیمات درست cache شده یا نه:

```bash
php artisan config:show mail.from.address
php artisan config:show mail.mailers.smtp.username
php artisan config:show mail.mailers.smtp.password
```

اگر مقادیر درست نیستند:
1. `.env` را دوباره بررسی کنید
2. `php artisan config:clear` را اجرا کنید
3. دوباره بررسی کنید

---

## ⚠️ مشکلات رایج

### مشکل 1: `MAIL_FROM_ADDRESS` هنوز `hello@example.com` است

**علت:** `MAIL_FROM_ADDRESS` در `.env` تنظیم نشده یا cache نشده است.

**راه‌حل:**
1. `.env` را باز کنید
2. `MAIL_FROM_ADDRESS=reyhane.taba@gmail.com` را اضافه کنید
3. `php artisan config:clear` را اجرا کنید
4. دوباره تست کنید

### مشکل 2: App Password کار نمی‌کند

**راه‌حل:**
1. App Password جدید ایجاد کنید
2. مطمئن شوید فاصله‌ها را حذف کرده‌اید
3. در `.env`، `MAIL_PASSWORD` را به‌روزرسانی کنید
4. `php artisan config:clear` را اجرا کنید
5. دوباره تست کنید

### مشکل 3: خطای "Connection could not be established"

**راه‌حل:**
1. از پورت 587 با TLS استفاده کنید (به جای 465 با SSL)
2. تنظیمات را در `.env` به‌روزرسانی کنید
3. `php artisan config:clear` را اجرا کنید
4. دوباره تست کنید

---

## 📋 چک‌لیست نهایی

قبل از تست، این موارد را بررسی کنید:

- [ ] 2-Step Verification در Gmail فعال است
- [ ] App Password جدید ایجاد شده است
- [ ] App Password بدون فاصله در `.env` قرار گرفته است
- [ ] `MAIL_USERNAME` درست است
- [ ] `MAIL_PASSWORD` درست است
- [ ] `MAIL_FROM_ADDRESS` همان آدرس ایمیل Gmail است (نه `hello@example.com`)
- [ ] `php artisan config:clear` اجرا شده است
- [ ] `php artisan cache:clear` اجرا شده است

اگر همه موارد را بررسی کردید و هنوز مشکل دارید، App Password جدید دیگری ایجاد کنید.
