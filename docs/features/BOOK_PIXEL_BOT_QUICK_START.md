# راهنمای سریع شروع کار با ربات یک پیکسل کتاب

## ⚡ شروع سریع (5 دقیقه)

### 1️⃣ اجرای Seeder

```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
```

**نتیجه:** باید پیام "✅ Webhook endpoint for Book Pixel Bot created successfully!" را ببینید.

### 2️⃣ بررسی در ربات مادر

1. به ربات مادر بروید
2. `/start` بزنید
3. باید "یک پیکسل کتاب" را در لیست ببینید

**اگر نمی‌بینید:**
```bash
php artisan cache:clear
php artisan config:clear
```

### 3️⃣ ساخت ربات جدید

1. در ربات مادر، شماره "یک پیکسل کتاب" را انتخاب کنید
2. نوع ربات را انتخاب کنید (تلگرام یا بله)
3. توکن ربات را ارسال کنید
4. زبان را انتخاب کنید (اختیاری)

---

## 📝 تنظیمات اولیه (10 دقیقه)

### مرحله 1: ثبت گروه نظارت

#### 1.1. ایجاد گروه

1. یک گروه جدید در تلگرام/بله ایجاد کنید
2. ربات را به گروه اضافه کنید
3. ربات را **ادمین** کنید

#### 1.2. گرفتن شناسه گروه

**روش 1: از طریق ربات**
```bash
# در گروه یک پیام بفرستید و لاگ را ببینید
tail -f storage/logs/laravel.log | grep "chat_id"
```

**روش 2: از طریق Bot API**
```bash
# برای تلگرام
curl "https://api.telegram.org/bot<TOKEN>/getUpdates" | jq '.result[].message.chat.id'

# برای بله  
curl "https://tapi.bale.ai/bot<TOKEN>/getUpdates" | jq '.result[].message.chat.id'
```

#### 1.3. ثبت در دیتابیس

```sql
-- شناسه ربات را از جدول bots بگیرید
SELECT id, telegram_bot_name, bale_bot_name FROM bots WHERE id = <BOT_ID>;

-- ثبت گروه نظارت
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,              -- شناسه ربات
    -1001234567890,        -- شناسه گروه (عدد منفی)
    'telegram',            -- یا 'bale'
    1,
    NOW(),
    NOW()
);
```

**مثال:**
```sql
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (1, -1001234567890, 'telegram', 1, NOW(), NOW());
```

### مرحله 2: ثبت کانال انتشار

#### 2.1. ایجاد کانال

1. یک کانال جدید ایجاد کنید
2. ربات را به کانال اضافه کنید
3. ربات را **ادمین** کنید

#### 2.2. گرفتن شناسه کانال

همان روش‌های گروه را استفاده کنید.

#### 2.3. ثبت در دیتابیس

```sql
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,              -- شناسه ربات
    -1001234567890,        -- شناسه کانال
    'telegram',            -- یا 'bale'
    1,
    NOW(),
    NOW()
);
```

**مثال برای چند کانال:**
```sql
-- کانال تلگرام
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (1, -1001111111111, 'telegram', 1, NOW(), NOW());

-- کانال بله
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (1, -1002222222222, 'bale', 1, NOW(), NOW());
```

---

## 🧪 تست کامل (15 دقیقه)

### تست 1: ثبت کتاب

1. به ربات "یک پیکسل کتاب" بروید
2. `/start` بزنید
3. نام کتاب را بفرستید (مثلاً: "شازده کوچولو")
4. ISBN را بفرستید یا Enter بزنید
5. شابک را بفرستید یا Enter بزنید
6. **عکس جلد کتاب** را بفرستید

**نتیجه:** باید پیام "کتاب 'شازده کوچولو' با موفقیت ثبت شد" را ببینید.

### تست 2: ارسال اسکن

1. شماره صفحه را بفرستید (مثلاً: `25`)
2. **عکس اسکن صفحه** را بفرستید

**نتیجه:** باید پیام "✅ اسکن صفحه برای تایید ارسال شد" را ببینید.

### تست 3: تایید در گروه نظارت

1. به گروه نظارت بروید
2. باید پیام با عکس و دکمه‌های "✅ تایید" و "❌ رد" را ببینید
3. روی "✅ تایید" کلیک کنید

**نتیجه:**
- پیام در گروه به "✅ تایید شده" تغییر می‌کند
- کاربر پیام تایید و دکمه "🎤 ارسال وویس" را دریافت می‌کند

### تست 4: ارسال وویس (اختیاری)

1. کاربر روی "🎤 ارسال وویس این صفحه" کلیک می‌کند
2. **فایل وویس** را ارسال می‌کند

**نتیجه:** باید پیام "✅ وویس با موفقیت ارسال شد" را ببینید.

### تست 5: انتشار در کانال

```bash
# اجرای دستی command
php artisan app:schedule-book-publishing
```

**نتیجه:**
- یک مورد از صف انتخاب می‌شود
- به کانال‌ها ارسال می‌شود
- در کانال باید عکس صفحه را ببینید

---

## 🔍 بررسی وضعیت

### بررسی در دیتابیس

```sql
-- تعداد کتاب‌ها
SELECT COUNT(*) as total_books FROM books WHERE bot_id = <BOT_ID>;

-- اسکن‌های در انتظار
SELECT COUNT(*) as pending_scans 
FROM book_page_scans 
WHERE bot_id = <BOT_ID> AND status = 'pending_approval';

-- اسکن‌های تایید شده
SELECT COUNT(*) as approved_scans 
FROM book_page_scans 
WHERE bot_id = <BOT_ID> AND status = 'approved';

-- موارد در صف انتشار
SELECT COUNT(*) as queue_items 
FROM book_publishing_queue 
WHERE bot_id = <BOT_ID> AND status = 'pending';

-- امتیازات کاربران
SELECT 
    u.chat_id,
    s.total_points,
    s.scans_count,
    s.voices_count
FROM book_user_scores s
JOIN bot_users u ON s.user_id = u.id
WHERE s.bot_id = <BOT_ID>
ORDER BY s.total_points DESC
LIMIT 10;
```

### بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های ربات
tail -f storage/logs/laravel.log | grep "BookPixel"

# مشاهده لاگ‌های تایید
tail -f storage/logs/laravel.log | grep "Book Pixel Approval"

# مشاهده لاگ‌های انتشار
tail -f storage/logs/laravel.log | grep "BookPublishingService"
```

---

## ❗ مشکلات رایج

### مشکل: ربات مادر ربات را نمی‌بیند

**راه حل:**
```bash
# 1. بررسی وجود endpoint
php artisan tinker
>>> DB::table('webhook_endpoints')->where('endpoint_id', 'book-pixel')->first();

# 2. اگر null است، seeder را اجرا کنید
php artisan db:seed --class=BookPixelWebhookEndpointSeeder

# 3. Cache را پاک کنید
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### مشکل: اسکن به گروه نظارت نمی‌رود

**بررسی:**
```sql
SELECT * FROM book_moderation_groups WHERE bot_id = <BOT_ID> AND is_active = 1;
```

**راه حل:**
- مطمئن شوید ربات ادمین گروه است
- شناسه گروه را دوباره بررسی کنید
- لاگ‌ها را چک کنید

### مشکل: محتوا در کانال منتشر نمی‌شود

**بررسی:**
```sql
SELECT * FROM book_publishing_channels WHERE bot_id = <BOT_ID> AND is_active = 1;
SELECT * FROM book_publishing_queue WHERE status = 'pending' AND scheduled_at <= NOW();
```

**راه حل:**
- مطمئن شوید ربات ادمین کانال است
- Command را دستی اجرا کنید و خطاها را ببینید
- زمان `scheduled_at` را بررسی کنید

---

## ✅ چک‌لیست نهایی

- [ ] Seeder اجرا شده
- [ ] ربات مادر ربات را می‌بیند
- [ ] ربات جدید ساخته شده
- [ ] گروه نظارت ثبت شده و ربات ادمین است
- [ ] کانال‌های انتشار ثبت شده‌اند و ربات ادمین است
- [ ] تست ثبت کتاب انجام شده
- [ ] تست ارسال اسکن انجام شده
- [ ] تست تایید در گروه انجام شده
- [ ] تست انتشار در کانال انجام شده

---

## 📞 دستورات مفید

```bash
# پاک کردن cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# مشاهده route ها
php artisan route:list | grep book-pixel

# اجرای migrations
php artisan migrate

# اجرای seeder
php artisan db:seed --class=BookPixelWebhookEndpointSeeder

# اجرای command انتشار
php artisan app:schedule-book-publishing

# مشاهده لاگ‌ها
tail -f storage/logs/laravel.log
```

---

**برای راهنمای کامل، به فایل `BOOK_PIXEL_BOT_SETUP_GUIDE.md` مراجعه کنید.**
