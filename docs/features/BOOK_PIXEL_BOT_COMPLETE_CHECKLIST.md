# ✅ چک‌لیست کامل راه‌اندازی ربات یک پیکسل کتاب

## 🚀 مرحله 1: نصب و راه‌اندازی (5 دقیقه)

### ✅ اجرای Migrations
```bash
php artisan migrate
```
**بررسی:** 8 جدول جدید باید ایجاد شوند

### ✅ اجرای Seeder
```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
```
**بررسی:** باید پیام "✅ Webhook endpoint for Book Pixel Bot created successfully!" را ببینید

### ✅ پاک کردن Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### ✅ بررسی در ربات مادر
1. به ربات مادر بروید
2. `/start` بزنید
3. **باید "یک پیکسل کتاب" را در لیست ببینید**

**اگر نمی‌بینید:**
```bash
# بررسی در دیتابیس
php artisan tinker
>>> DB::table('webhook_endpoints')->where('endpoint_id', 'book-pixel')->first();
```

---

## 🤖 مرحله 2: ساخت ربات جدید (2 دقیقه)

1. در ربات مادر:
   - شماره "یک پیکسل کتاب" را انتخاب کنید
   - نوع ربات را انتخاب کنید (تلگرام/بله)
   - توکن ربات را ارسال کنید
   - زبان را انتخاب کنید

**✅ نتیجه:** ربات جدید ساخته می‌شود

**📝 یادداشت:** شناسه ربات را یادداشت کنید (از لاگ یا دیتابیس)

```sql
SELECT id, telegram_bot_name, bale_bot_name, endpoint_id 
FROM bots 
WHERE endpoint_id = 'book-pixel' 
ORDER BY id DESC 
LIMIT 1;
```

---

## 👥 مرحله 3: تنظیم گروه نظارت (5 دقیقه)

### 3.1. ایجاد گروه
- [ ] گروه جدید ایجاد شد
- [ ] ربات به گروه اضافه شد
- [ ] ربات به عنوان ادمین تنظیم شد

### 3.2. گرفتن شناسه گروه

**روش ساده:**
1. در گروه یک پیام بفرستید
2. لاگ را ببینید:
```bash
tail -f storage/logs/laravel.log | grep "chat_id"
```

**یا از ربات @userinfobot استفاده کنید**

### 3.3. ثبت در دیتابیس

```sql
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,              -- شناسه ربات
    <GROUP_CHAT_ID>,       -- شناسه گروه (عدد منفی)
    'telegram',           -- یا 'bale'
    1,
    NOW(),
    NOW()
);
```

**✅ بررسی:**
```sql
SELECT * FROM book_moderation_groups WHERE bot_id = <BOT_ID>;
```

---

## 📢 مرحله 4: تنظیم کانال انتشار (5 دقیقه)

### 4.1. ایجاد کانال
- [ ] کانال جدید ایجاد شد
- [ ] ربات به کانال اضافه شد
- [ ] ربات به عنوان ادمین تنظیم شد

### 4.2. گرفتن شناسه کانال
همان روش گروه

### 4.3. ثبت در دیتابیس

```sql
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,
    <CHANNEL_CHAT_ID>,
    'telegram',           -- یا 'bale'
    1,
    NOW(),
    NOW()
);
```

**✅ بررسی:**
```sql
SELECT * FROM book_publishing_channels WHERE bot_id = <BOT_ID>;
```

---

## 🧪 مرحله 5: تست کامل (15 دقیقه)

### تست 1: ثبت کتاب ✅
- [ ] `/start` در ربات
- [ ] نام کتاب ارسال شد
- [ ] ISBN ارسال شد (یا Enter)
- [ ] شابک ارسال شد (یا Enter)
- [ ] عکس جلد ارسال شد
- [ ] پیام "کتاب ثبت شد" دریافت شد

**بررسی:**
```sql
SELECT * FROM books WHERE bot_id = <BOT_ID> ORDER BY id DESC LIMIT 1;
```

### تست 2: ارسال اسکن ✅
- [ ] شماره صفحه ارسال شد
- [ ] عکس اسکن ارسال شد
- [ ] پیام "اسکن برای تایید ارسال شد" دریافت شد
- [ ] در گروه نظارت پیام با دکمه‌ها ظاهر شد

**بررسی:**
```sql
SELECT * FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'pending_approval';
```

### تست 3: تایید اسکن ✅
- [ ] در گروه نظارت روی "✅ تایید" کلیک شد
- [ ] پیام در گروه به "✅ تایید شده" تغییر کرد
- [ ] کاربر پیام تایید و دکمه "🎤 ارسال وویس" را دریافت کرد

**بررسی:**
```sql
SELECT * FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'approved';
SELECT * FROM book_user_scores WHERE bot_id = <BOT_ID>;
SELECT * FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'pending';
```

### تست 4: ارسال وویس (اختیاری) ✅
- [ ] روی "🎤 ارسال وویس" کلیک شد
- [ ] فایل وویس ارسال شد
- [ ] پیام "وویس ارسال شد" دریافت شد

**بررسی:**
```sql
SELECT * FROM book_page_voices WHERE bot_id = <BOT_ID>;
SELECT * FROM book_user_scores WHERE bot_id = <BOT_ID>; -- باید 200 امتیاز اضافه شده باشد
```

### تست 5: انتشار در کانال ✅
- [ ] Command اجرا شد: `php artisan app:schedule-book-publishing`
- [ ] محتوا در کانال منتشر شد
- [ ] عکس صفحه در کانال دیده می‌شود

**بررسی:**
```sql
SELECT * FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'published';
```

---

## 🔍 دستورات بررسی سریع

### بررسی وضعیت کلی
```sql
SELECT 
    (SELECT COUNT(*) FROM books WHERE bot_id = <BOT_ID>) as books,
    (SELECT COUNT(*) FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'pending_approval') as pending,
    (SELECT COUNT(*) FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'approved') as approved,
    (SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'pending') as queue,
    (SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'published') as published;
```

### بررسی تنظیمات
```sql
-- گروه نظارت
SELECT * FROM book_moderation_groups WHERE bot_id = <BOT_ID> AND is_active = 1;

-- کانال‌های انتشار
SELECT * FROM book_publishing_channels WHERE bot_id = <BOT_ID> AND is_active = 1;
```

### بررسی امتیازات
```sql
SELECT 
    u.chat_id,
    s.total_points,
    s.scans_count,
    s.voices_count
FROM book_user_scores s
JOIN bot_users u ON s.user_id = u.id
WHERE s.bot_id = <BOT_ID>
ORDER BY s.total_points DESC;
```

---

## ❗ مشکلات رایج و راه حل

### مشکل: ربات مادر ربات را نمی‌بیند
```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
php artisan cache:clear
php artisan config:clear
```

### مشکل: اسکن به گروه نمی‌رود
- بررسی کنید ربات ادمین گروه است
- شناسه گروه را دوباره بررسی کنید
- لاگ‌ها را چک کنید

### مشکل: محتوا در کانال منتشر نمی‌شود
- بررسی کنید ربات ادمین کانال است
- Command را دستی اجرا کنید
- زمان `scheduled_at` را بررسی کنید

---

## 📚 فایل‌های راهنما

1. **BOOK_PIXEL_BOT_SETUP_GUIDE.md** - راهنمای کامل تنظیمات
2. **BOOK_PIXEL_BOT_QUICK_START.md** - راهنمای سریع شروع
3. **BOOK_PIXEL_BOT_TEST_SCENARIO.md** - سناریوهای تست کامل
4. **BOOK_PIXEL_BOT.md** - مستندات فنی

---

## ✅ همه چیز آماده است!

اگر همه چک‌لیست‌ها را انجام دادید، ربات شما آماده استفاده است! 🎉
