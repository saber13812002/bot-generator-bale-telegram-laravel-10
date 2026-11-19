# ربات نماز قضا (Prayer Qadha Bot) - راهنمای کامل

## 📋 خلاصه

ربات نماز قضا یک سیستم جامع برای ثبت، ردیابی و گزارش‌دهی نمازهای قضا است. این ربات به کاربران امکان می‌دهد تا به راحتی رکعات نماز قضای خود را ثبت کنند و گزارش‌های دوره‌ای از پیشرفت خود دریافت کنند.

### ویژگی‌های اصلی:
- ✅ ثبت آسان رکعات با ارسال اعداد 2، 3 یا 4
- 📊 مدیریت تخمین نمازهای قضا
- 📧 دریافت گزارش‌های هفتگی به ایمیل
- 📈 پیگیری پیشرفت با آمار کامل
- 🔔 پشتیبانی از تلگرام و بله

---

## 🚀 تنظیمات اولیه

### 1. تنظیمات Laravel

#### نصب وابستگی‌ها
```bash
composer install
npm install
```

#### اجرای Migration ها
```bash
php artisan migrate
```

#### اجرای Seeder
```bash
php artisan db:seed --class=PrayerBotWebhookEndpointSeeder
```

#### پاک کردن Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### 2. تنظیمات Gmail برای ارسال ایمیل

#### مرحله 1: ایجاد App Password در Gmail

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

#### مرحله 2: تنظیمات `.env`

فایل `.env` را باز کنید و تنظیمات زیر را اضافه کنید:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=abcd efgh ijkl mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**نکات مهم:**
- `MAIL_USERNAME`: آدرس ایمیل Gmail شما
- `MAIL_PASSWORD`: کد 16 رقمی که از App Passwords کپی کردید (بدون فاصله)
- `MAIL_FROM_ADDRESS`: همان آدرس ایمیل Gmail شما

#### مرحله 3: تست ارسال ایمیل

```bash
php artisan tinker
```

در Tinker:
```php
Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

اگر ایمیل ارسال شد، تنظیمات درست است!

### 3. تنظیمات Scheduler برای گزارش‌های هفتگی

فایل `app/Console/Kernel.php` را بررسی کنید که دستور زیر اضافه شده باشد:

```php
protected function schedule(Schedule $schedule): void
{
    // ارسال گزارش‌های هفتگی نماز قضا
    $schedule->command('prayer:send-weekly-reports')
             ->weeklyOn(0, '9:00'); // یکشنبه ساعت 9 صبح
}
```

برای فعال‌سازی Cron Job در سرور:

```bash
crontab -e
```

این خط را اضافه کنید:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📱 دستورات ربات

### دستورات اصلی

| دستور | توضیح | مثال |
|-------|-------|------|
| `/start` | شروع کار با ربات | `/start` |
| `/help` | نمایش راهنمای کامل | `/help` |
| `/stats` | مشاهده آمار کامل | `/stats` |

### ثبت نماز

| ورودی | خروجی | توضیح |
|-------|-------|-------|
| `2` | ✅ 2 رکعت (صبح) ثبت شد! | ثبت نماز صبح |
| `3` | ✅ 3 رکعت (مغرب) ثبت شد! | ثبت نماز مغرب |
| `4` | ✅ 4 رکعت (ظهر) ثبت شد! | ثبت نماز ظهر/عصر/عشا |

**نکته:** ربات به صورت هوشمند نوع نماز را بر اساس زمان روز تشخیص می‌دهد.

### مدیریت تخمین

| دستور | توضیح | مثال |
|-------|-------|------|
| `/estimate` | شروع فرآیند ثبت تخمین | `/estimate` |
| `/estimate_status` | مشاهده تخمین فعلی | `/estimate_status` |

**فرآیند ثبت تخمین:**
1. کاربر `/estimate` را ارسال می‌کند
2. ربات کیبورد انتخاب واحد نمایش می‌دهد (روز، هفته، ماه، سال، رکعت)
3. کاربر واحد را انتخاب می‌کند
4. کاربر عدد را ارسال می‌کند
5. ربات تخمین را ثبت می‌کند

**مثال:**
```
کاربر: /estimate
ربات: [نمایش کیبورد]
کاربر: [کلیک روی "ماه"]
ربات: چند ماه نماز قضا دارید؟
کاربر: 6
ربات: ✅ 6 ماه = 3060 رکعت ثبت شد!
```

### مدیریت ایمیل

| دستور | توضیح | مثال |
|-------|-------|------|
| `/set_email` یا `/email` | تنظیم ایمیل برای گزارش | `/set_email` |
| `/email_settings` | مشاهده و تغییر تنظیمات ایمیل | `/email_settings` |

**فرآیند تنظیم ایمیل:**
1. کاربر `/set_email` را ارسال می‌کند
2. ربات درخواست ایمیل می‌کند
3. کاربر ایمیل را ارسال می‌کند
4. ربات کد 6 رقمی به ایمیل ارسال می‌کند
5. کاربر کد را ارسال می‌کند
6. ایمیل تایید می‌شود

**مثال:**
```
کاربر: /set_email
ربات: لطفاً ایمیل خود را ارسال کنید
کاربر: example@gmail.com
ربات: ✅ کد تایید به ایمیل شما ارسال شد
کاربر: 123456
ربات: ✅ ایمیل شما با موفقیت تایید شد!
```

### حذف رکعات

| دستور | توضیح | مثال |
|-------|-------|------|
| `/remove_<ID>` | حذف رکعت ثبت شده | `/remove_123` |

**نکته:** ID در پیام تایید ثبت رکعت نمایش داده می‌شود.

---

## 🔄 نحوه کار سیستم

### 1. ثبت رکعات

```
کاربر → ارسال عدد (2, 3, 4)
    ↓
PrayerBotController::handleTextMessage()
    ↓
PrayerHelper::detectNumberInText()
    ↓
PrayerBotService::recordPrayer()
    ↓
PrayerRecordRepository::create()
    ↓
Database (prayer_records)
    ↓
پیام تایید به کاربر
```

### 2. ثبت تخمین

```
کاربر → /estimate
    ↓
PrayerBotController::handleEstimateStart()
    ↓
State: estimate_waiting_unit
    ↓
کاربر → انتخاب واحد (ماه)
    ↓
State: estimate_waiting_value
    ↓
کاربر → ارسال عدد (6)
    ↓
PrayerBotService::convertToRakats()
    ↓
PrayerBotService::setEstimate()
    ↓
Database (prayer_estimates)
    ↓
پیام تایید
```

### 3. تنظیم ایمیل

```
کاربر → /set_email
    ↓
State: email_waiting_address
    ↓
کاربر → ارسال ایمیل
    ↓
Validation ایمیل
    ↓
تولید کد 6 رقمی
    ↓
ارسال ایمیل (EmailVerificationMail)
    ↓
State: email_waiting_code
    ↓
کاربر → ارسال کد
    ↓
تایید کد
    ↓
ذخیره در Database (bot_users)
    ↓
پیام تایید
```

### 4. ارسال گزارش هفتگی

```
Scheduler (هر یکشنبه ساعت 9)
    ↓
SendPrayerWeeklyReports Command
    ↓
SendPrayerReportEmailJob (Queue)
    ↓
PrayerWeeklyReportMail
    ↓
ارسال به ایمیل کاربر
```

#### برای اونایی که ایمیلشون وریفای شده — چیکار کنیم گزارش هفتگی براشون ارسال بشه؟

گزارش هفتگی **فقط** به کاربرانی ارسال می‌شود که در ربات نماز قضا ایمیل ثبت کرده و آن را **تأیید** (verify) کرده‌اند (`email` و `email_verified_at` در جدول `bot_users` مقدار داشته باشند).

**کارهایی که باید انجام شود:**

1. **روی سرور حتماً کران‌جاب Laravel Schedule فعال باشد**  
   بدون اجرای مکرر `php artisan schedule:run`، دستور ارسال گزارش هفتگی اصلاً اجرا نمی‌شود. در **README.md** بخش «⏰ Cron Jobs» لیست کران‌جاب‌های پیشنهادی سرور آمده است. حداقل یکی از ردیف‌هایی که `schedule:run` را صدا می‌زنند (مثلاً هر دقیقه یا طبق جدول) باید در crontab سرور ست شود:
   ```bash
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

2. **دستور `SendPrayerWeeklyReports` در `app/Console/Kernel.php` زمان‌بندی شده باشد**  
   در همین پروژه این دستور با چند اینتروال (هر ۱۰ دقیقه، هر ۳۰ دقیقه، هر ساعت) در `Kernel::schedule()` ثبت شده است. با هر بار اجرای `schedule:run`، در زمان‌های تعریف‌شده این کامند اجرا می‌شود و کاربران وریفای‌شده را انتخاب و برای آن‌ها ایمیل گزارش هفتگی را در صف قرار می‌دهد یا ارسال می‌کند.

3. **صف (queue) در صورت استفاده**  
   اگر ارسال ایمیل از طریق Queue انجام می‌شود، روی سرور باید `php artisan queue:work` (یا مشابه) در حال اجرا باشد تا جاب‌های گزارش واقعاً ارسال شوند. جزئیات و کران‌جاب صف هم در همان بخش Cron Jobs در README آمده است.

**خلاصه:** برای اینکه به کاربرانی که ایمیلشان verify شده گزارش هفتگی برسد، روی سرور باید `schedule:run` (و در صورت نیاز `queue:work`) طبق [README.md - Cron Jobs](../../README.md#-cron-jobs) ست شده باشد؛ بعد از آن نیازی به کار اضافه برای «اونایی که ایمیلشون وریفای شده» نیست — همان دستورات زمان‌بندی‌شده آن‌ها را انتخاب و ایمیل را ارسال می‌کنند.

---

## 📊 ورودی و خروجی

### ورودی‌ها

#### 1. ثبت رکعات
- **ورودی:** عدد (2, 3, 4) یا متن حاوی عدد
- **فرمت:** `"2"`, `"3"`, `"4"`, `"2 صبح"`, `"4 ظهر"`

#### 2. ثبت تخمین
- **ورودی 1:** `/estimate`
- **ورودی 2:** انتخاب واحد (callback query)
- **ورودی 3:** عدد (مثلاً `6`)

#### 3. تنظیم ایمیل
- **ورودی 1:** `/set_email`
- **ورودی 2:** آدرس ایمیل (مثلاً `example@gmail.com`)
- **ورودی 3:** کد 6 رقمی (مثلاً `123456`)

### خروجی‌ها

#### 1. ثبت رکعات
```json
{
  "message": "✅ 4 رکعت (ظهر) ثبت شد!\n\n🔢 4 رکعات\n📿 نوع نماز: ظهر\n🆔 شناسه: 123\n\n🗑️ برای حذف: /remove_123",
  "record_id": 123
}
```

#### 2. آمار (`/stats`)
```
📊 آمار شما

🎯 تخمین کل: 3060 رکعت (6 ماه)
✅ ثبت شده: 450 رکعت (15%)
📉 باقیمانده: 2610 رکعت

⏱️ با این سرعت: حدود 8 ماه تا اتمام
```

#### 3. تخمین (`/estimate_status`)
```
📊 وضعیت تخمین

🎯 تخمین کل: 3060 رکعت
📅 معادل: 6 ماه

✅ ثبت شده: 450 رکعت
📉 باقیمانده: 2610 رکعت
📊 پیشرفت: 15%

💪 پیشرفت عالی! ادامه دهید!
```

#### 4. ایمیل تایید
- **Subject:** کد تایید ایمیل - ربات نماز قضا
- **Body:** HTML با کد 6 رقمی

---

## 🗄️ ساختار دیتابیس

### جدول `prayer_records`
```sql
CREATE TABLE prayer_records (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    chat_id BIGINT,
    bot_id INT,
    rakats INT,
    prayer_type ENUM('fajr', 'dhuhr', 'asr', 'maghrib', 'isha'),
    detection_method VARCHAR(50),
    origin ENUM('telegram', 'bale'),
    message_id BIGINT,
    created_at TIMESTAMP
);
```

### جدول `prayer_estimates`
```sql
CREATE TABLE prayer_estimates (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    chat_id BIGINT,
    origin ENUM('telegram', 'bale'),
    total_missed_prayers INT,
    total_missed_rakats INT,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### جدول `bot_user_states`
```sql
CREATE TABLE bot_user_states (
    id BIGINT PRIMARY KEY,
    bot_user_id BIGINT,
    bot_mother_id INT,
    state VARCHAR(50),
    data JSON,
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### فیلدهای ایمیل در `bot_users`
```sql
ALTER TABLE bot_users ADD COLUMN email VARCHAR(255);
ALTER TABLE bot_users ADD COLUMN email_verified_at TIMESTAMP;
ALTER TABLE bot_users ADD COLUMN email_verification_code VARCHAR(6);
ALTER TABLE bot_users ADD COLUMN email_verification_code_expires_at TIMESTAMP;
ALTER TABLE bot_users ADD COLUMN email_report_frequency ENUM('daily', 'weekly', 'monthly', 'never');
ALTER TABLE bot_users ADD COLUMN email_unsubscribe_token VARCHAR(64);
```

---

## 🔧 تنظیمات پیشرفته

### Rate Limiting برای ایمیل

در `app/Console/Commands/SendPrayerWeeklyReports.php`:
- حداکثر 2 ایمیل در ساعت
- حداکثر 10 کاربر در هر batch

### State Expiration

State ها بعد از 10 دقیقه منقضی می‌شوند:
```php
$this->prayerBotService->setState(
    $botUser->id,
    $botMotherId,
    'email_waiting_code',
    ['email' => $email],
    10 // 10 دقیقه
);
```

### کد تایید ایمیل

- طول: 6 رقم
- انقضا: 10 دقیقه
- فرمت: عددی (100000-999999)

---

## 📚 مستندات بیشتر

برای اطلاعات کامل‌تر، به مستندات اصلی مراجعه کنید:

- **[مستندات کامل ربات نماز قضا](./PRAYER_QADHA_BOT.md)** - توضیحات کامل معماری و ویژگی‌ها
- **[راهنمای سیستم Help](./PRAYER_BOT_HELP_SYSTEM.md)** - توضیحات کامل سیستم راهنما
- **[راهنمای تخمین گفتگومحور](./PRAYER_ESTIMATE_CONVERSATION.md)** - توضیحات سیستم تخمین
- **[راهنمای سریع](./../PRAYER_BOT_QUICK_START.md)** - راهنمای سریع شروع کار

---

## 🐛 عیب‌یابی

### مشکل: ایمیل ارسال نمی‌شود

1. **بررسی تنظیمات `.env`:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

2. **تست ارسال ایمیل:**
   ```bash
   php artisan tinker
   Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); });
   ```

3. **بررسی لاگ‌ها:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### مشکل: State منقضی می‌شود

- State ها بعد از 10 دقیقه منقضی می‌شوند
- اگر نیاز به زمان بیشتر دارید، مقدار `expiresInMinutes` را تغییر دهید

### مشکل: کد تایید دریافت نمی‌شود

1. پوشه Spam را چک کنید
2. بررسی کنید که `MAIL_*` در `.env` درست تنظیم شده باشد
3. بررسی لاگ‌ها برای خطاهای ارسال

---

## 📞 پشتیبانی

برای سوالات و مشکلات:
- بررسی لاگ‌ها: `storage/logs/laravel.log`
- بررسی مستندات: `docs/features/PRAYER_QADHA_BOT.md`
- تماس با تیم توسعه

---

**آخرین به‌روزرسانی:** 2026-01-06  
**نسخه:** 1.0.0
