# 🔑 راهنمای ایجاد App Password در Gmail

## روش 1: از طریق صفحه امنیت Google

### مرحله 1: ورود به صفحه امنیت
1. به [Google Account Security](https://myaccount.google.com/security) بروید
2. وارد حساب Gmail خود شوید (`reyhane.taba@gmail.com`)

### مرحله 2: فعال‌سازی 2-Step Verification
1. در صفحه امنیت، روی **"درستی‌سنجی دومرحله‌ای"** کلیک کنید
2. اگر فعال نیست، آن را فعال کنید
3. مراحل را دنبال کنید (تایید شماره تلفن، و غیره)

### مرحله 3: پیدا کردن App Passwords
1. بعد از فعال‌سازی 2-Step Verification، به صفحه Security برگردید
2. دوباره روی **"درستی‌سنجی دومرحله‌ای"** کلیک کنید
3. در پایین صفحه، بخش **"App passwords"** را پیدا کنید
4. روی **"App passwords"** کلیک کنید

### مرحله 4: ایجاد App Password
1. یک نام برای برنامه انتخاب کنید (مثلاً: "Laravel Prayer Bot Server")
2. روی **"Generate"** کلیک کنید
3. کد 16 رقمی را کپی کنید (مثلاً: `abcd efgh ijkl mnop`)
4. **مهم:** فاصله‌ها را حذف کنید (مثلاً: `abcdefghijklmnop`)

---

## روش 2: لینک مستقیم

اگر App Passwords را پیدا نمی‌کنید، از این لینک استفاده کنید:

**لینک مستقیم:** https://myaccount.google.com/apppasswords

**نکته:** این لینک فقط زمانی کار می‌کند که:
- 2-Step Verification فعال باشد
- به حساب Google خود وارد شده باشید

---

## روش 3: از طریق تنظیمات Gmail

1. به [Gmail](https://mail.google.com) بروید
2. روی آیکون حساب خود (گوشه بالا راست) کلیک کنید
3. روی **"Manage your Google Account"** کلیک کنید
4. در منوی سمت چپ، روی **"Security"** کلیک کنید
5. در بخش **"Signing in to Google"**، روی **"2-Step Verification"** کلیک کنید
6. در پایین صفحه، **"App passwords"** را پیدا کنید

---

## ⚠️ مشکلات رایج

### مشکل 1: App Passwords را نمی‌بینم

**راه‌حل:**
1. مطمئن شوید 2-Step Verification فعال است
2. اگر تازه فعال کرده‌اید، چند دقیقه صبر کنید
3. صفحه را Refresh کنید (F5)
4. از مرورگر دیگری امتحان کنید
5. از لینک مستقیم استفاده کنید: https://myaccount.google.com/apppasswords

### مشکل 2: "App passwords isn't available for this account"

**علت:** حساب شما یک حساب سازمانی (Workspace) است یا 2-Step Verification فعال نیست.

**راه‌حل:**
- اگر حساب سازمانی است، با مدیر IT تماس بگیرید
- اگر حساب شخصی است، 2-Step Verification را فعال کنید

### مشکل 3: App Password کار نمی‌کند

**راه‌حل:**
1. مطمئن شوید فاصله‌ها را حذف کرده‌اید
2. App Password جدید ایجاد کنید
3. در `.env`، `MAIL_PASSWORD` را به‌روزرسانی کنید
4. `php artisan config:clear` را اجرا کنید

---

## 📝 تنظیمات `.env`

بعد از ایجاد App Password، تنظیمات زیر را در `.env` اضافه کنید:

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=reyhane.taba@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=reyhane.taba@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**نکات:**
- `MAIL_PASSWORD`: App Password که کپی کردید (بدون فاصله)
- `MAIL_FROM_ADDRESS`: همان آدرس ایمیل Gmail شما

---

## ✅ تست تنظیمات

بعد از تنظیم `.env`:

```bash
php artisan config:clear
php artisan test:email-verification your-email@gmail.com
```

اگر پیام "✅ ایمیل با موفقیت ارسال شد!" را دیدید، تنظیمات درست است!
