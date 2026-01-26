# سناریوهای تست کامل ربات یک پیکسل کتاب

## 🎯 سناریو کامل از صفر تا صد

### مرحله 1: راه‌اندازی اولیه ✅

```bash
# 1. اجرای migrations
php artisan migrate

# 2. اجرای seeder
php artisan db:seed --class=BookPixelWebhookEndpointSeeder

# 3. پاک کردن cache
php artisan cache:clear
php artisan config:clear
```

**بررسی:**
- در ربات مادر `/start` بزنید
- باید "یک پیکسل کتاب" را در لیست ببینید

---

### مرحله 2: ساخت ربات جدید 🤖

1. در ربات مادر:
   - `/start` بزنید
   - شماره "یک پیکسل کتاب" را انتخاب کنید (مثلاً: `5`)
   - نوع ربات را انتخاب کنید (`1` برای تلگرام یا `2` برای بله)
   - توکن ربات را ارسال کنید
   - زبان را انتخاب کنید (یا Enter بزنید)

**نتیجه:** ربات جدید ساخته می‌شود و webhook تنظیم می‌شود.

**بررسی:**
```sql
SELECT id, telegram_bot_name, bale_bot_name, endpoint_id 
FROM bots 
WHERE endpoint_id = 'book-pixel' 
ORDER BY id DESC 
LIMIT 1;
```

**شناسه ربات را یادداشت کنید** (مثلاً: `123`)

---

### مرحله 3: تنظیم گروه نظارت 👥

#### 3.1. ایجاد و تنظیم گروه

1. یک گروه جدید در تلگرام/بله ایجاد کنید
2. ربات را به گروه اضافه کنید
3. ربات را به عنوان **ادمین** تنظیم کنید (مهم!)

#### 3.2. گرفتن شناسه گروه

**روش 1: از طریق ربات (ساده‌ترین)**

1. در گروه یک پیام بفرستید (مثلاً: "سلام")
2. به لاگ‌ها نگاه کنید:

```bash
tail -f storage/logs/laravel.log | grep "chat_id"
```

شناسه گروه معمولاً یک عدد منفی است (مثلاً: `-1001234567890`)

**روش 2: از طریق Bot API**

```bash
# برای تلگرام
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates" | jq '.result[].message.chat.id'

# برای بله
curl "https://tapi.bale.ai/bot<YOUR_BOT_TOKEN>/getUpdates" | jq '.result[].message.chat.id'
```

**روش 3: از طریق ربات @userinfobot**

1. ربات `@userinfobot` را به گروه اضافه کنید
2. `/start` بزنید
3. شناسه گروه را نشان می‌دهد

#### 3.3. ثبت در دیتابیس

```sql
-- جایگزین کنید:
-- <BOT_ID> = شناسه ربات (مثلاً: 123)
-- <GROUP_CHAT_ID> = شناسه گروه (مثلاً: -1001234567890)
-- <ORIGIN> = 'telegram' یا 'bale'

INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,
    <GROUP_CHAT_ID>,
    '<ORIGIN>',
    1,
    NOW(),
    NOW()
);
```

**مثال:**
```sql
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (123, -1001234567890, 'telegram', 1, NOW(), NOW());
```

**بررسی:**
```sql
SELECT * FROM book_moderation_groups WHERE bot_id = 123;
```

---

### مرحله 4: تنظیم کانال انتشار 📢

#### 4.1. ایجاد و تنظیم کانال

1. یک کانال جدید ایجاد کنید
2. ربات را به کانال اضافه کنید
3. ربات را به عنوان **ادمین** تنظیم کنید

#### 4.2. گرفتن شناسه کانال

همان روش‌های گروه را استفاده کنید.

**نکته:** شناسه کانال معمولاً با `-100` شروع می‌شود.

#### 4.3. ثبت در دیتابیس

```sql
-- جایگزین کنید:
-- <BOT_ID> = شناسه ربات
-- <CHANNEL_CHAT_ID> = شناسه کانال
-- <ORIGIN> = 'telegram' یا 'bale'

INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,
    <CHANNEL_CHAT_ID>,
    '<ORIGIN>',
    1,
    NOW(),
    NOW()
);
```

**مثال برای چند کانال:**
```sql
-- کانال تلگرام
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (123, -1001111111111, 'telegram', 1, NOW(), NOW());

-- کانال بله
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (123, -1002222222222, 'bale', 1, NOW(), NOW());
```

**بررسی:**
```sql
SELECT * FROM book_publishing_channels WHERE bot_id = 123;
```

---

## 🧪 تست کامل سناریوها

### تست 1: ثبت کتاب جدید 📚

#### گام 1: شروع ربات

1. به ربات "یک پیکسل کتاب" بروید
2. `/start` بزنید

**نتیجه:** پیام خوش‌آمدگویی و درخواست نام کتاب

#### گام 2: ثبت اطلاعات کتاب

1. نام کتاب را بفرستید: `شازده کوچولو`
2. ISBN را بفرستید (یا Enter): `9789643411234`
3. شابک را بفرستید (یا Enter): `964-341-123-4`
4. **عکس جلد کتاب** را بفرستید

**نتیجه مورد انتظار:**
```
✅ کتاب 'شازده کوچولو' با موفقیت ثبت شد.
لطفاً شماره صفحه را ارسال کنید:
```

**بررسی در دیتابیس:**
```sql
SELECT * FROM books WHERE name = 'شازده کوچولو' AND bot_id = 123;
```

باید رکورد کتاب را ببینید.

---

### تست 2: ارسال اسکن صفحه 📄

#### گام 1: ارسال شماره صفحه

1. شماره صفحه را بفرستید: `25`

**نتیجه:** درخواست اسکن صفحه

#### گام 2: ارسال اسکن

1. **عکس اسکن صفحه** را بفرستید

**نتیجه مورد انتظار:**
```
✅ اسکن صفحه برای تایید ارسال شد. پس از تایید، در صف انتشار قرار می‌گیرد.
```

**بررسی در دیتابیس:**
```sql
-- بررسی اسکن
SELECT * FROM book_page_scans 
WHERE bot_id = 123 
AND status = 'pending_approval'
ORDER BY id DESC 
LIMIT 1;

-- بررسی صفحه کتاب
SELECT * FROM book_pages 
WHERE book_id = (SELECT id FROM books WHERE name = 'شازده کوچولو' LIMIT 1)
AND page_number = 25;
```

#### گام 3: بررسی در گروه نظارت

1. به گروه نظارت بروید
2. باید یک پیام با این محتوا ببینید:
   - عکس اسکن صفحه
   - متن: "📚 درخواست تایید اسکن صفحه"
   - اطلاعات: نام کتاب، شماره صفحه، شناسه کاربر
   - دکمه‌ها: "✅ تایید" و "❌ رد"

---

### تست 3: تایید اسکن ✅

#### گام 1: تایید در گروه نظارت

1. در گروه نظارت، روی دکمه **"✅ تایید"** کلیک کنید

**نتیجه در گروه:**
- دکمه‌ها به "✅ تایید شده" تغییر می‌کند

**نتیجه برای کاربر:**
```
✅ اسکن صفحه شما تایید شد!

📖 کتاب: شازده کوچولو
📄 صفحه: 25
🎯 امتیاز اضافه شده: 100
📊 امتیاز کل شما: 100

💡 می‌توانید وویس این صفحه را ارسال کنید (200 امتیاز اضافی)

[🎤 ارسال وویس این صفحه]
```

#### گام 2: بررسی در دیتابیس

```sql
-- بررسی وضعیت اسکن
SELECT * FROM book_page_scans 
WHERE bot_id = 123 
AND status = 'approved'
ORDER BY id DESC 
LIMIT 1;

-- بررسی امتیاز کاربر
SELECT * FROM book_user_scores 
WHERE bot_id = 123;

-- بررسی صف انتشار
SELECT * FROM book_publishing_queue 
WHERE bot_id = 123 
AND status = 'pending';
```

**نکته:** باید یک رکورد در `book_publishing_queue` با `status = 'pending'` ببینید.

---

### تست 4: ارسال وویس (اختیاری) 🎤

#### گام 1: کلیک روی دکمه "ارسال وویس"

1. کاربر روی دکمه **"🎤 ارسال وویس این صفحه"** کلیک می‌کند

**نتیجه:** پیام "لطفاً وویس صفحه را ارسال کنید"

#### گام 2: ارسال وویس

1. کاربر **فایل وویس** را ارسال می‌کند

**نتیجه مورد انتظار:**
```
✅ وویس با موفقیت ارسال شد.
```

#### گام 3: بررسی در دیتابیس

```sql
-- بررسی وویس
SELECT * FROM book_page_voices 
WHERE bot_id = 123 
ORDER BY id DESC 
LIMIT 1;

-- بررسی امتیاز کاربر (باید 200 اضافه شده باشد)
SELECT * FROM book_user_scores 
WHERE bot_id = 123;

-- بررسی صف انتشار (باید voice_id اضافه شده باشد)
SELECT * FROM book_publishing_queue 
WHERE bot_id = 123 
AND status = 'pending'
ORDER BY id DESC 
LIMIT 1;
```

**نکته:** `book_page_voice_id` باید در `book_publishing_queue` ثبت شده باشد.

---

### تست 5: انتشار در کانال 📢

#### گام 1: بررسی زمان‌بندی

Command `ScheduleBookPublishing` هر ساعت بین 19:00 تا 23:59 اجرا می‌شود.

#### گام 2: اجرای دستی Command

```bash
php artisan app:schedule-book-publishing
```

**نتیجه مورد انتظار:**
```
Starting book page publishing...
Published item 1 for bot 123
Published 1 items.
```

#### گام 3: بررسی در کانال

1. به کانال انتشار بروید
2. باید این محتوا را ببینید:
   - عکس صفحه کتاب
   - Caption: "📚 شازده کوچولو\n📄 صفحه 25"
   - اگر وویس وجود دارد، وویس هم ارسال می‌شود

#### گام 4: بررسی در دیتابیس

```sql
-- بررسی وضعیت انتشار
SELECT * FROM book_publishing_queue 
WHERE bot_id = 123 
AND status = 'published'
ORDER BY id DESC 
LIMIT 1;

-- باید published_at پر شده باشد
SELECT id, status, published_at 
FROM book_publishing_queue 
WHERE bot_id = 123 
AND status = 'published';
```

---

## 🔍 بررسی وضعیت کلی

### دستورات SQL مفید

```sql
-- آمار کلی
SELECT 
    (SELECT COUNT(*) FROM books WHERE bot_id = 123) as total_books,
    (SELECT COUNT(*) FROM book_page_scans WHERE bot_id = 123 AND status = 'pending_approval') as pending_scans,
    (SELECT COUNT(*) FROM book_page_scans WHERE bot_id = 123 AND status = 'approved') as approved_scans,
    (SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = 123 AND status = 'pending') as queue_items,
    (SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = 123 AND status = 'published') as published_items;

-- لیست کتاب‌ها
SELECT id, name, isbn, shabak, created_at 
FROM books 
WHERE bot_id = 123 
ORDER BY created_at DESC;

-- لیست اسکن‌های در انتظار
SELECT 
    s.id,
    b.name as book_name,
    s.page_number,
    s.status,
    s.created_at
FROM book_page_scans s
JOIN book_pages p ON s.book_page_id = p.id
JOIN books b ON p.book_id = b.id
WHERE s.bot_id = 123 
AND s.status = 'pending_approval'
ORDER BY s.created_at DESC;

-- لیست صف انتشار
SELECT 
    q.id,
    b.name as book_name,
    s.page_number,
    q.status,
    q.scheduled_at,
    q.published_at
FROM book_publishing_queue q
JOIN book_page_scans s ON q.book_page_scan_id = s.id
JOIN book_pages p ON s.book_page_id = p.id
JOIN books b ON p.book_id = b.id
WHERE q.bot_id = 123
ORDER BY q.created_at DESC;

-- رتبه‌بندی کاربران
SELECT 
    u.chat_id,
    s.total_points,
    s.scans_count,
    s.voices_count,
    s.updated_at
FROM book_user_scores s
JOIN bot_users u ON s.user_id = u.id
WHERE s.bot_id = 123
ORDER BY s.total_points DESC
LIMIT 10;
```

---

## ❗ عیب‌یابی

### مشکل 1: ربات مادر ربات را نمی‌بیند

**بررسی:**
```sql
SELECT * FROM webhook_endpoints WHERE endpoint_id = 'book-pixel';
```

**راه حل:**
```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
php artisan cache:clear
php artisan config:clear
```

### مشکل 2: اسکن به گروه نظارت نمی‌رود

**بررسی:**
```sql
SELECT * FROM book_moderation_groups WHERE bot_id = 123 AND is_active = 1;
```

**راه حل:**
- مطمئن شوید ربات ادمین گروه است
- شناسه گروه را دوباره بررسی کنید
- لاگ‌ها را چک کنید: `tail -f storage/logs/laravel.log | grep "BookPixel"`

### مشکل 3: محتوا در کانال منتشر نمی‌شود

**بررسی:**
```sql
SELECT * FROM book_publishing_channels WHERE bot_id = 123 AND is_active = 1;
SELECT * FROM book_publishing_queue WHERE bot_id = 123 AND status = 'pending' AND scheduled_at <= NOW();
```

**راه حل:**
- مطمئن شوید ربات ادمین کانال است
- Command را دستی اجرا کنید: `php artisan app:schedule-book-publishing`
- لاگ‌ها را چک کنید

### مشکل 4: امتیاز به کاربر اضافه نمی‌شود

**بررسی:**
```sql
SELECT * FROM book_user_scores WHERE bot_id = 123 AND user_id = <USER_ID>;
SELECT * FROM book_page_scans WHERE bot_id = 123 AND status = 'approved' AND user_id = <USER_ID>;
```

**راه حل:**
- بررسی کنید که تایید انجام شده است
- بررسی کنید که `user_id` درست است
- لاگ‌ها را چک کنید

---

## 📝 چک‌لیست نهایی

- [ ] Migrations اجرا شده
- [ ] Seeder اجرا شده
- [ ] ربات مادر ربات را می‌بیند
- [ ] ربات جدید ساخته شده
- [ ] گروه نظارت ثبت شده و ربات ادمین است
- [ ] کانال‌های انتشار ثبت شده‌اند و ربات ادمین است
- [ ] تست ثبت کتاب انجام شده
- [ ] تست ارسال اسکن انجام شده
- [ ] تست تایید در گروه انجام شده
- [ ] تست ارسال وویس انجام شده
- [ ] تست انتشار در کانال انجام شده
- [ ] همه چیز درست کار می‌کند! ✅

---

## 🎉 تبریک!

اگر همه تست‌ها موفق بودند، ربات شما آماده استفاده است!

برای اطلاعات بیشتر، به فایل `BOOK_PIXEL_BOT_SETUP_GUIDE.md` مراجعه کنید.
