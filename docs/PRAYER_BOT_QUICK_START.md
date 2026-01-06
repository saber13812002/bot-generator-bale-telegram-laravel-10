# 🕌 راهنمای سریع: ربات نماز قضا

## خلاصه یک خطی
ربات ثبت و پیگیری نماز قضا با قابلیت گزارش‌دهی ایمیلی هفتگی

---

## 🚀 نصب سریع

```bash
# 1. Migration
php artisan migrate

# 2. Seeder
php artisan db:seed --class=PrayerBotWebhookEndpointSeeder

# 3. تنظیم webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/api/webhook-prayer-bot?origin=telegram&bot_mother_id=1"

# 4. Queue Worker
php artisan queue:work
```

---

## 📱 نحوه استفاده (برای کاربر)

### دستورات اصلی

```
/start          → شروع
2               → ثبت 2 رکعت صبح
3               → ثبت 3 رکعت مغرب
4               → ثبت 4 رکعت (ظهر/عصر/عشا)
/stats          → مشاهده آمار
/estimate 1000  → ثبت تخمین (1000 نماز)
/remove_123     → حذف رکعت شماره 123
/email          → تنظیم ایمیل
```

### مثال تعامل

```
کاربر: 2
ربات: ✅ رکعت ثبت شد
      🔢 2 رکعت
      📿 نوع نماز: صبح
      🆔 شناسه: 123
      🗑️ برای حذف: /remove_123
```

---

## 🎯 تشخیص هوشمند

| عدد | نماز | توضیحات |
|-----|------|---------|
| 2 | صبح | همیشه |
| 3 | مغرب | همیشه |
| 4 | ظهر | صبح تا 13:00 |
| 4 | عصر | 13:00 تا 18:00 |
| 4 | عشا | بعد از 18:00 |

---

## 📧 سیستم ایمیل

- ✅ ارسال خودکار هفتگی
- ✅ 5 تمپلیت متنوع (ضد اسپم)
- ✅ محدودیت: 2 ایمیل/ساعت
- ✅ Unsubscribe استاندارد

---

## 📂 فایل‌های کلیدی

```
app/
├── Http/Controllers/
│   └── PrayerBotController.php          ← کنترلر اصلی
├── Services/
│   └── PrayerBotServiceImpl.php         ← منطق کسب‌وکار
├── Repositories/
│   ├── PrayerRecordRepositoryImpl.php   ← دیتا
│   └── PrayerEstimateRepositoryImpl.php
├── Models/
│   ├── PrayerRecord.php                 ← مدل رکعات
│   ├── PrayerEstimate.php               ← مدل تخمین
│   └── EmailReportQueue.php             ← صف ایمیل
├── Helpers/
│   └── PrayerHelper.php                 ← توابع کمکی
├── Mail/
│   └── PrayerWeeklyReportMail.php       ← ایمیل
├── Jobs/
│   └── SendPrayerReportEmailJob.php     ← Job ارسال
└── Console/Commands/
    └── SendPrayerWeeklyReports.php      ← Command

database/migrations/
├── xxxx_create_prayer_records_table.php
├── xxxx_create_prayer_estimates_table.php
├── xxxx_add_email_fields_to_bot_users.php
└── xxxx_create_email_report_queue_table.php

routes/
└── api.php                              ← Routes
```

---

## 🔧 تنظیمات .env

```env
# توکن ربات (اختیاری)
PRAYER_BOT_TOKEN_TELEGRAM=
PRAYER_BOT_TOKEN_BALE=

# ایمیل
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=app_password
MAIL_ENCRYPTION=tls
```

---

## 🧪 تست سریع

```bash
# تست ارسال ایمیل
php artisan prayer:send-weekly-reports --limit=1 --force

# بررسی webhook
php artisan tinker
>>> BotHelper::checkWebhookInfo('<TOKEN>', 'telegram');

# بررسی لاگ‌ها
tail -f storage/logs/laravel.log | grep PrayerBot
```

---

## 📊 جداول دیتابیس

### prayer_records
```sql
id, chat_id, rakats, prayer_type, origin, created_at
```

### prayer_estimates
```sql
id, chat_id, total_missed_prayers, total_missed_rakats
```

### email_report_queue
```sql
id, email, status, sent_at
```

---

## ❓ مشکلات رایج

### ربات جواب نمیده؟
```bash
# 1. چک webhook
curl https://api.telegram.org/bot<TOKEN>/getWebhookInfo

# 2. چک لاگ
tail -f storage/logs/laravel.log

# 3. مطمئن شو origin و bot_mother_id توی URL هستند
```

### ایمیل نمیره؟
```bash
# 1. Queue worker اجرا شده؟
php artisan queue:work

# 2. تنظیمات .env چک باشه

# 3. جدول email_report_queue رو ببین
```

---

## 📖 مستندات کامل

برای جزئیات بیشتر: [`docs/features/PRAYER_QADHA_BOT.md`](./features/PRAYER_QADHA_BOT.md)

---

## 🎯 وضعیت

✅ **فعال و آماده استفاده**  
📅 **آخرین به‌روزرسانی:** 1404/10/16  
🔢 **نسخه:** 1.0.0

---

## 🤝 پشتیبانی

- 📧 ایمیل: support@example.com
- 🐛 GitHub Issues
- 📱 تلگرام: @support

---

**ساخته شده با ❤️ برای کمک به برگزاری نماز قضا**
