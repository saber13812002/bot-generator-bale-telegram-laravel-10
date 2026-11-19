# راهنمای تست و دیباگ Mailtrap - قدم به قدم

این راهنما به صورت قدم به قدم نحوه تست و دیباگ سیستم ایمیل با Mailtrap را توضیح می‌دهد.

## 📋 فهرست مطالب

1. [تنظیمات اولیه](#1-تنظیمات-اولیه)
2. [تست دستور Artisan](#2-تست-دستور-artisan)
3. [تست در ربات](#3-تست-در-ربات)
4. [تست گزارش هفتگی](#4-تست-گزارش-هفتگی)
5. [دیباگ خطاها](#5-دیباگ-خطاها)
6. [بررسی لاگ‌ها](#6-بررسی-لاگها)

---

## 1. تنظیمات اولیه

### مرحله 1.1: بررسی تنظیمات فعلی

```bash
# بررسی فایل .env
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

### مرحله 1.2: اضافه کردن تنظیمات (اگر وجود ندارد)

```bash
# اضافه کردن به .env
echo "" >> .env
echo "# Mailtrap API Configuration" >> .env
echo "MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338" >> .env
echo "MAILTRAP_INBOX_ID=1439975" >> .env
echo "MAILTRAP_USE_SANDBOX=true" >> .env
echo "MAIL_FROM_ADDRESS=hello@pardisania.ir" >> .env
echo "MAIL_FROM_NAME=\"Bots\"" >> .env
```

### مرحله 1.3: پاک کردن Cache

```bash
php artisan config:clear
php artisan cache:clear
```

**✅ بررسی موفقیت:**
```bash
php artisan tinker
>>> env('MAILTRAP_API_TOKEN')
# باید توکن را نمایش دهد
>>> exit
```

---

## 2. تست دستور Artisan

### مرحله 2.1: تست Sandbox

```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --token=5c2835343ece0cc635687a7047b5d338 --sandbox
```

**خروجی مورد انتظار:**
```
📧 تست ارسال ایمیل با Mailtrap API به: saber.tabatabaee@gmail.com
🔑 کد تایید: 123456
📦 حالت: Sandbox

🔍 بررسی تنظیمات Mailtrap:
   API Token: ✅ تنظیم شده
   Inbox ID: 1439975

📤 در حال ارسال ایمیل از طریق Mailtrap API...
   From: hello@pardisania.ir (Bots)
   To: saber.tabatabaee@gmail.com
   Subject: کد تایید ایمیل - ربات نماز قضا

✅ ایمیل با موفقیت ارسال شد!
   Message ID: 5279849414

💡 نکته:
   ایمیل در Mailtrap Sandbox ذخیره شده است
   برای مشاهده: https://mailtrap.io/inboxes/1439975/messages
```

### مرحله 2.2: بررسی ایمیل در Mailtrap

1. به [Mailtrap.io](https://mailtrap.io) بروید
2. وارد حساب کاربری شوید
3. به بخش **Sandbox** بروید
4. Inbox با ID `1439975` را باز کنید
5. ایمیل ارسال شده را بررسی کنید

**✅ بررسی موفقیت:**
- ایمیل باید در لیست نمایش داده شود
- محتوای HTML باید صحیح باشد
- کد تایید باید نمایش داده شود

### مرحله 2.3: تست با تنظیمات .env

```bash
# بدون پارامترهای اضافی (از .env می‌خواند)
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --sandbox
```

---

## 3. تست در ربات

### مرحله 3.1: راه‌اندازی ربات

```bash
# بررسی اینکه ربات در حال اجرا است
ps aux | grep "php artisan serve"
# یا
ps aux | grep "php-fpm"
```

### مرحله 3.2: تست Webhook

```bash
# ارسال پیام تستی به ربات
curl -X POST "https://your-domain.com/api/webhook-prayer-bot" \
  -H "Content-Type: application/json" \
  -d '{
    "message": {
      "chat": {"id": 123456789},
      "text": "/start"
    }
  }'
```

### مرحله 3.3: تست ثبت ایمیل در ربات

1. ربات را در تلگرام/بله باز کنید
2. دستور `/start` را بزنید
3. به بخش **تنظیمات ایمیل** بروید
4. ایمیل خود را وارد کنید (مثلاً: `saber.tabatabaee@gmail.com`)
5. منتظر دریافت پیام تایید باشید

**✅ بررسی موفقیت:**
- ربات باید پیام "✅ کد تایید به ایمیل شما ارسال شد" را نمایش دهد
- در Mailtrap Sandbox، ایمیل جدید باید ظاهر شود

### مرحله 3.4: بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های اخیر
tail -n 50 storage/logs/laravel.log | grep -i "prayerbot\|mailtrap"

# یا جستجوی خطاها
tail -n 100 storage/logs/laravel.log | grep -i "error\|exception"
```

**خروجی مورد انتظار:**
```
[2026-01-08 10:17:00] local.INFO: 📧 [PrayerBot] Verification code sent via Mailtrap {"chat_id":123456789,"email":"saber.tabatabaee@gmail.com","code":"123456"}
[2026-01-08 10:17:01] local.INFO: ✅ [MailtrapEmailService] Email sent successfully {"to":"saber.tabatabaee@gmail.com","message_id":"5279849414","mode":"sandbox"}
```

---

## 4. تست گزارش هفتگی

### مرحله 4.1: ایجاد کاربر تستی

```bash
php artisan tinker
```

```php
// ایجاد یا پیدا کردن کاربر
$user = \App\Models\BotUsers::firstOrCreate(
    ['chat_id' => 123456789],
    [
        'email' => 'saber.tabatabaee@gmail.com',
        'email_verified_at' => now(),
        'email_unsubscribe_token' => bin2hex(random_bytes(32)),
        'origin' => 'telegram'
    ]
);

// بررسی کاربر
$user->email;
$user->email_verified_at;
exit;
```

### مرحله 4.2: ایجاد داده تستی برای گزارش

```bash
php artisan tinker
```

```php
// ایجاد چند نماز قضا برای تست
$user = \App\Models\BotUsers::where('chat_id', 123456789)->first();
$service = app(\App\Interfaces\Services\PrayerBotService::class);

// ثبت چند نماز قضا
// (این بستگی به ساختار دیتابیس شما دارد)
exit;
```

### مرحله 4.3: ارسال گزارش تستی

```bash
# ارسال گزارش برای یک کاربر
php artisan prayer:send-weekly-reports --limit=1 --force
```

**خروجی مورد انتظار:**
```
🕌 شروع ارسال گزارش‌های هفتگی نماز قضا...
📊 تعداد کاربران: 1
✅ ایمیل برای saber.tabatabaee@gmail.com آماده ارسال شد.

📈 خلاصه:
✅ موفق: 1
❌ ناموفق: 0
⏱️  زمان: 2.5 ثانیه
```

### مرحله 4.4: بررسی ایمیل گزارش در Mailtrap

1. به Mailtrap Sandbox بروید
2. ایمیل جدید با موضوع "📊 گزارش هفتگی نماز قضا شما" را پیدا کنید
3. محتوای HTML را بررسی کنید
4. لینک لغو اشتراک را تست کنید

---

## 5. دیباگ خطاها

### خطای 1: "API Token تنظیم نشده است"

**علت:** `MAILTRAP_API_TOKEN` در `.env` تنظیم نشده

**راه‌حل:**
```bash
# 1. بررسی .env
cat .env | grep MAILTRAP_API_TOKEN

# 2. اگر وجود ندارد، اضافه کنید
echo "MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338" >> .env

# 3. پاک کردن cache
php artisan config:clear
```

### خطای 2: "MAILTRAP_INBOX_ID برای Sandbox ضروری است"

**علت:** `MAILTRAP_USE_SANDBOX=true` اما `MAILTRAP_INBOX_ID` تنظیم نشده

**راه‌حل:**
```bash
echo "MAILTRAP_INBOX_ID=1439975" >> .env
php artisan config:clear
```

### خطای 3: "401 Unauthorized"

**علت:** توکن API نامعتبر است

**راه‌حل:**
```bash
# 1. بررسی توکن (بدون فاصله)
php artisan tinker
>>> trim(env('MAILTRAP_API_TOKEN'))
# باید توکن بدون فاصله نمایش دهد

# 2. بررسی در Mailtrap
# به Mailtrap بروید و توکن جدید دریافت کنید

# 3. به‌روزرسانی .env
# توکن جدید را در .env قرار دهید
php artisan config:clear
```

### خطای 4: "404 Not Found"

**علت:** URL یا Inbox ID اشتباه است

**راه‌حل:**
```bash
# 1. بررسی Inbox ID
php artisan tinker
>>> env('MAILTRAP_INBOX_ID')

# 2. بررسی URL در کد
# فایل app/Services/MailtrapEmailService.php را بررسی کنید
# برای Sandbox: https://sandbox.api.mailtrap.io/api/send/{INBOX_ID}
# برای Transactional: https://send.api.mailtrap.io/api/send
```

### خطای 5: "Connection timeout"

**علت:** مشکل اتصال به اینترنت یا فایروال

**راه‌حل:**
```bash
# 1. تست اتصال
curl -I https://sandbox.api.mailtrap.io

# 2. بررسی فایروال
# اگر در سرور هستید، پورت 443 باید باز باشد

# 3. تست با curl مستقیم
curl -X POST 'https://sandbox.api.mailtrap.io/api/send/1439975' \
  -H 'Api-Token: 5c2835343ece0cc635687a7047b5d338' \
  -H 'Content-Type: application/json' \
  -d '{"from":{"email":"hello@pardisania.ir"},"to":[{"email":"test@example.com"}],"subject":"Test","text":"Test"}'
```

---

## 6. بررسی لاگ‌ها

### مرحله 6.1: مشاهده لاگ‌های زنده

```bash
# مشاهده لاگ‌های جدید
tail -f storage/logs/laravel.log

# فیلتر کردن فقط خطاها
tail -f storage/logs/laravel.log | grep -i "error\|exception"
```

### مرحله 6.2: جستجوی لاگ‌های Mailtrap

```bash
# جستجوی تمام لاگ‌های Mailtrap
grep -i "mailtrap" storage/logs/laravel.log | tail -20

# جستجوی خطاهای Mailtrap
grep -i "mailtrap.*error" storage/logs/laravel.log | tail -20
```

### مرحله 6.3: جستجوی لاگ‌های PrayerBot

```bash
# جستجوی لاگ‌های PrayerBot
grep -i "prayerbot" storage/logs/laravel.log | tail -20

# جستجوی لاگ‌های ارسال ایمیل
grep -i "verification code sent\|email sent" storage/logs/laravel.log | tail -20
```

### مرحله 6.4: تحلیل لاگ‌ها

```bash
# شمارش خطاها
grep -c "ERROR" storage/logs/laravel.log

# نمایش آخرین 10 خطا
grep "ERROR" storage/logs/laravel.log | tail -10

# نمایش خطاهای امروز
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep "ERROR"
```

---

## ✅ چک‌لیست تست کامل

- [ ] تنظیمات `.env` کامل است
- [ ] Cache پاک شده است
- [ ] دستور `test:mailtrap-api` موفق است
- [ ] ایمیل در Mailtrap Sandbox نمایش داده می‌شود
- [ ] ربات می‌تواند ایمیل تایید ارسال کند
- [ ] کد تایید در Mailtrap قابل مشاهده است
- [ ] گزارش هفتگی ارسال می‌شود
- [ ] لاگ‌ها بدون خطا هستند
- [ ] لینک لغو اشتراک کار می‌کند

---

## 🆘 در صورت بروز مشکل

1. **بررسی تنظیمات:**
   ```bash
   php artisan tinker
   >>> env('MAILTRAP_API_TOKEN')
   >>> env('MAILTRAP_INBOX_ID')
   >>> env('MAILTRAP_USE_SANDBOX')
   ```

2. **بررسی لاگ‌ها:**
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```

3. **تست مستقیم API:**
   ```bash
   curl -X POST 'https://sandbox.api.mailtrap.io/api/send/1439975' \
     -H 'Api-Token: 5c2835343ece0cc635687a7047b5d338' \
     -H 'Content-Type: application/json' \
     -d '{"from":{"email":"hello@pardisania.ir"},"to":[{"email":"test@example.com"}],"subject":"Test","text":"Test"}'
   ```

4. **بررسی مستندات:**
   - [MAILTRAP_SETUP_GUIDE.md](./MAILTRAP_SETUP_GUIDE.md)
   - [EMAIL_TROUBLESHOOTING.md](./EMAIL_TROUBLESHOOTING.md)

---

**آخرین به‌روزرسانی:** 2026-01-08
