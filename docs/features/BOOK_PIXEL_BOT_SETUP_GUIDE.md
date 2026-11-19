# راهنمای کامل تنظیم و تست ربات یک پیکسل کتاب

## 📋 فهرست مطالب

1. [نصب و راه‌اندازی اولیه](#نصب-و-راه‌اندازی-اولیه)
2. [تنظیم گروه نظارت](#تنظیم-گروه-نظارت)
3. [تنظیم کانال‌های انتشار](#تنظیم-کانال‌های-انتشار)
4. [تست کامل سناریوها](#تست-کامل-سناریوها)
5. [عیب‌یابی](#عیب‌یابی)

---

## 🚀 نصب و راه‌اندازی اولیه

### مرحله 1: اجرای Migrations

```bash
php artisan migrate
```

این دستور 8 جدول زیر را ایجاد می‌کند:
- `books`
- `book_pages`
- `book_page_scans`
- `book_page_voices`
- `book_publishing_queue`
- `book_publishing_channels`
- `book_moderation_groups`
- `book_user_scores`

### مرحله 2: اجرای Seeder

```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
```

این دستور endpoint را در جدول `webhook_endpoints` ثبت می‌کند تا ربات مادر بتواند آن را ببیند.

**نکته مهم:** اگر ربات مادر هنوز ربات را نمی‌بیند:
1. Cache را پاک کنید: `php artisan cache:clear`
2. دوباره seeder را اجرا کنید
3. بررسی کنید که در جدول `webhook_endpoints` رکورد با `endpoint_id = 'book-pixel'` وجود دارد

### مرحله 3: بررسی ثبت در دیتابیس

```sql
SELECT * FROM webhook_endpoints WHERE endpoint_id = 'book-pixel';
```

باید یک رکورد با مشخصات زیر ببینید:
- `endpoint_id`: `book-pixel`
- `name`: `یک پیکسل کتاب`
- `route`: `api/webhook-book-pixel`
- `is_active`: `1`

---

## 👥 تنظیم گروه نظارت

### مرحله 1: ایجاد گروه در تلگرام/بله

1. یک گروه جدید در تلگرام یا بله ایجاد کنید
2. ربات را به گروه اضافه کنید
3. ربات را به عنوان **ادمین** گروه تنظیم کنید (مهم!)

### مرحله 2: گرفتن شناسه گروه

#### روش 1: از طریق ربات

1. ربات را در گروه فعال کنید
2. یک پیام در گروه ارسال کنید
3. به لاگ‌ها نگاه کنید:

```bash
tail -f storage/logs/laravel.log | grep "chat_id"
```

#### روش 2: از طریق Bot API

```bash
# برای تلگرام
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates"

# برای بله
curl "https://tapi.bale.ai/bot<YOUR_BOT_TOKEN>/getUpdates"
```

شناسه گروه معمولاً یک عدد منفی است (مثل `-1001234567890`)

### مرحله 3: ثبت گروه در دیتابیس

```sql
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,                    -- شناسه ربات از جدول bots
    -1001234567890,              -- شناسه گروه (عدد منفی)
    'telegram',                  -- یا 'bale'
    1,                           -- فعال
    NOW(),
    NOW()
);
```

**مثال:**
```sql
INSERT INTO book_moderation_groups (bot_id, group_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    1,
    -1001234567890,
    'telegram',
    1,
    NOW(),
    NOW()
);
```

---

## 📢 تنظیم کانال‌های انتشار

### مرحله 1: ایجاد کانال در تلگرام/بله

1. یک کانال جدید ایجاد کنید
2. ربات را به کانال اضافه کنید
3. ربات را به عنوان **ادمین** کانال تنظیم کنید

### مرحله 2: گرفتن شناسه کانال

#### روش 1: از طریق ربات

1. ربات را در کانال فعال کنید
2. یک پیام در کانال ارسال کنید
3. به لاگ‌ها نگاه کنید

#### روش 2: از طریق Bot API

```bash
# برای تلگرام
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates"

# برای بله
curl "https://tapi.bale.ai/bot<YOUR_BOT_TOKEN>/getUpdates"
```

شناسه کانال معمولاً با `-100` شروع می‌شود (مثل `-1001234567890`)

### مرحله 3: ثبت کانال در دیتابیس

```sql
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (
    <BOT_ID>,                    -- شناسه ربات
    -1001234567890,              -- شناسه کانال
    'telegram',                  -- یا 'bale'
    1,                           -- فعال
    NOW(),
    NOW()
);
```

**مثال برای چند کانال:**
```sql
-- کانال تلگرام
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (1, -1001234567890, 'telegram', 1, NOW(), NOW());

-- کانال بله
INSERT INTO book_publishing_channels (bot_id, channel_chat_id, origin, is_active, created_at, updated_at)
VALUES (1, -1009876543210, 'bale', 1, NOW(), NOW());
```

---

## 🧪 تست کامل سناریوها

### سناریو 1: ثبت کتاب جدید

#### گام 1: شروع ربات

1. به ربات مادر بروید
2. `/start` بزنید
3. از لیست، "یک پیکسل کتاب" را انتخاب کنید
4. توکن ربات را ارسال کنید

#### گام 2: ثبت کتاب

1. در ربات "یک پیکسل کتاب" `/start` بزنید
2. نام کتاب را ارسال کنید (مثلاً: "شازده کوچولو")
3. اگر خواست، ISBN را ارسال کنید (یا Enter بزنید)
4. اگر خواست، شابک را ارسال کنید (یا Enter بزنید)
5. **عکس جلد کتاب** را ارسال کنید

**نتیجه مورد انتظار:**
- پیام: "کتاب 'شازده کوچولو' با موفقیت ثبت شد."
- درخواست شماره صفحه

#### گام 3: بررسی در دیتابیس

```sql
SELECT * FROM books WHERE name LIKE '%شازده کوچولو%';
```

باید رکورد کتاب را ببینید.

---

### سناریو 2: ارسال اسکن صفحه

#### گام 1: ارسال شماره صفحه

1. شماره صفحه را ارسال کنید (مثلاً: `25`)
2. ربات باید بپرسد: "لطفاً اسکن صفحه کتاب را ارسال کنید"

#### گام 2: ارسال اسکن

1. **عکس اسکن صفحه** را ارسال کنید
2. ربات باید بگوید: "✅ اسکن صفحه برای تایید ارسال شد"

#### گام 3: بررسی در دیتابیس

```sql
SELECT * FROM book_page_scans WHERE status = 'pending_approval';
```

باید اسکن با وضعیت `pending_approval` را ببینید.

#### گام 4: بررسی در گروه نظارت

1. به گروه نظارت بروید
2. باید یک پیام با عکس اسکن و دکمه‌های "✅ تایید" و "❌ رد" ببینید

---

### سناریو 3: تایید اسکن

#### گام 1: تایید در گروه نظارت

1. در گروه نظارت، روی دکمه **"✅ تایید"** کلیک کنید
2. پیام باید به "✅ تایید شده" تغییر کند

#### گام 2: بررسی اطلاع‌رسانی به کاربر

1. کاربر باید پیامی دریافت کند:
   - "✅ اسکن صفحه شما تایید شد!"
   - "🎯 امتیاز اضافه شده: 100"
   - دکمه "🎤 ارسال وویس این صفحه"

#### گام 3: بررسی در دیتابیس

```sql
-- بررسی وضعیت اسکن
SELECT * FROM book_page_scans WHERE status = 'approved';

-- بررسی امتیاز کاربر
SELECT * FROM book_user_scores WHERE bot_id = <BOT_ID>;

-- بررسی صف انتشار
SELECT * FROM book_publishing_queue WHERE status = 'pending';
```

---

### سناریو 4: ارسال وویس (اختیاری)

#### گام 1: کلیک روی دکمه "ارسال وویس"

1. کاربر روی دکمه "🎤 ارسال وویس این صفحه" کلیک می‌کند
2. ربات می‌پرسد: "لطفاً وویس صفحه را ارسال کنید"

#### گام 2: ارسال وویس

1. کاربر **فایل وویس** را ارسال می‌کند
2. ربات باید بگوید: "✅ وویس با موفقیت ارسال شد"

#### گام 3: بررسی در دیتابیس

```sql
SELECT * FROM book_page_voices;
SELECT * FROM book_user_scores WHERE bot_id = <BOT_ID>;
```

امتیاز کاربر باید 200 امتیاز اضافه شده باشد.

---

### سناریو 5: انتشار در کانال

#### گام 1: بررسی زمان‌بندی

Command `ScheduleBookPublishing` هر ساعت بین 19:00 تا 23:59 اجرا می‌شود.

#### گام 2: اجرای دستی Command

```bash
php artisan app:schedule-book-publishing
```

**نتیجه مورد انتظار:**
- یک مورد رندوم از صف انتخاب می‌شود
- به کانال‌های فعال ارسال می‌شود
- وضعیت به `published` تغییر می‌کند

#### گام 3: بررسی در کانال

1. به کانال انتشار بروید
2. باید عکس صفحه کتاب را ببینید
3. اگر وویس وجود دارد، وویس هم ارسال می‌شود

#### گام 4: بررسی در دیتابیس

```sql
SELECT * FROM book_publishing_queue WHERE status = 'published';
```

---

## 🔍 عیب‌یابی

### مشکل 1: ربات مادر ربات را نمی‌بیند

**راه حل:**
```bash
# 1. بررسی وجود endpoint
php artisan tinker
>>> DB::table('webhook_endpoints')->where('endpoint_id', 'book-pixel')->first();

# 2. اگر وجود ندارد، seeder را اجرا کنید
php artisan db:seed --class=BookPixelWebhookEndpointSeeder

# 3. Cache را پاک کنید
php artisan cache:clear
php artisan config:clear
```

### مشکل 2: اسکن به گروه نظارت ارسال نمی‌شود

**بررسی:**
```sql
-- بررسی وجود گروه نظارت
SELECT * FROM book_moderation_groups WHERE bot_id = <BOT_ID> AND is_active = 1;

-- بررسی اینکه ربات ادمین گروه است
```

**راه حل:**
- مطمئن شوید ربات ادمین گروه است
- شناسه گروه را دوباره بررسی کنید
- لاگ‌ها را چک کنید: `tail -f storage/logs/laravel.log`

### مشکل 3: محتوا در کانال منتشر نمی‌شود

**بررسی:**
```sql
-- بررسی وجود کانال
SELECT * FROM book_publishing_channels WHERE bot_id = <BOT_ID> AND is_active = 1;

-- بررسی صف انتشار
SELECT * FROM book_publishing_queue WHERE status = 'pending';
```

**راه حل:**
- مطمئن شوید ربات ادمین کانال است
- Command را دستی اجرا کنید و خطاها را ببینید
- لاگ‌ها را چک کنید

### مشکل 4: امتیاز به کاربر اضافه نمی‌شود

**بررسی:**
```sql
SELECT * FROM book_user_scores WHERE bot_id = <BOT_ID> AND user_id = <USER_ID>;
```

**راه حل:**
- بررسی کنید که `user_id` درست است
- بررسی کنید که تایید انجام شده است
- لاگ‌ها را چک کنید

---

## 📝 دستورات مفید

### بررسی وضعیت کلی

```sql
-- تعداد کتاب‌ها
SELECT COUNT(*) FROM books WHERE bot_id = <BOT_ID>;

-- تعداد اسکن‌های در انتظار تایید
SELECT COUNT(*) FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'pending_approval';

-- تعداد اسکن‌های تایید شده
SELECT COUNT(*) FROM book_page_scans WHERE bot_id = <BOT_ID> AND status = 'approved';

-- تعداد موارد در صف انتشار
SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'pending';

-- تعداد موارد منتشر شده
SELECT COUNT(*) FROM book_publishing_queue WHERE bot_id = <BOT_ID> AND status = 'published';

-- امتیازات کاربران
SELECT u.chat_id, s.total_points, s.scans_count, s.voices_count
FROM book_user_scores s
JOIN bot_users u ON s.user_id = u.id
WHERE s.bot_id = <BOT_ID>
ORDER BY s.total_points DESC
LIMIT 10;
```

### پاک کردن داده‌های تست

```sql
-- حذف همه داده‌های تست (مراقب باشید!)
DELETE FROM book_publishing_queue WHERE bot_id = <BOT_ID>;
DELETE FROM book_page_voices WHERE bot_id = <BOT_ID>;
DELETE FROM book_page_scans WHERE bot_id = <BOT_ID>;
DELETE FROM book_pages WHERE book_id IN (SELECT id FROM books WHERE bot_id = <BOT_ID>);
DELETE FROM books WHERE bot_id = <BOT_ID>;
DELETE FROM book_user_scores WHERE bot_id = <BOT_ID>;
```

---

## ✅ چک‌لیست نهایی

قبل از استفاده در production، این موارد را بررسی کنید:

- [ ] Migrations اجرا شده
- [ ] Seeder اجرا شده
- [ ] Route ها درست ثبت شده‌اند
- [ ] گروه نظارت ثبت شده و ربات ادمین است
- [ ] کانال‌های انتشار ثبت شده‌اند و ربات ادمین است
- [ ] Command در Kernel.php ثبت شده
- [ ] ترجمه‌ها برای همه زبان‌ها اضافه شده
- [ ] لاگ‌ها درست کار می‌کنند
- [ ] تست کامل انجام شده

---

## 📞 پشتیبانی

اگر مشکلی پیش آمد:
1. لاگ‌ها را بررسی کنید: `storage/logs/laravel.log`
2. دیتابیس را بررسی کنید
3. Route ها را تست کنید: `php artisan route:list | grep book-pixel`
