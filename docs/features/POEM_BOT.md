# ربات شعر و موسیقی (Poem Bot)

## توضیحات

ربات شعر و موسیقی یک ربات تلگرام کامل است که به کاربران امکان ارسال، ویرایش، نسخه‌بندی، لایک و پیشنهاد مصرع برای اشعار را می‌دهد. این ربات از دکمه‌های شیشه‌ای (Inline Buttons) برای تعاملات کاربران استفاده می‌کند.

## ویژگی‌ها

### 1. ارسال شعر جدید
- پشتیبانی از دو نوع شعر: کلاسیک و نو
- دریافت مصرع‌ها/جملات به ترتیب
- امکان افزودن مصرع‌های بیشتر
- ذخیره به عنوان پیش‌نویس یا انتشار

### 2. ویرایش شعر
- نمایش فهرست اشعار کاربر
- ویرایش مصرع‌ها
- حذف و جابجایی مصرع‌ها
- ایجاد نسخه جدید از شعر

### 3. نسخه‌بندی
- سیستم versioning مشابه Git
- ذخیره نسخه‌های مختلف شعر
- امکان بازگشت به نسخه‌های قبلی

### 4. لایک و امتیازدهی
- لایک کردن اشعار
- نمایش تعداد لایک‌ها
- کامند `/like [poem_id]`

### 5. مشاهده فهرست
- نمایش اشعار منتشر شده
- مرتب‌سازی بر اساس لایک یا تاریخ
- جستجو در اشعار

### 6. پیشنهاد مصرع
- پیشنهاد مصرع برای اشعار دیگران
- تایید/رد پیشنهادات توسط صاحب شعر

### 7. همکاری (Fork)
- امکان فورک کردن اشعار دیگران
- ایجاد نسخه جدید بر اساس شعر اصلی

## کامندها

- `/start` - شروع و نمایش منوی اصلی
- `/newpoem` - ارسال شعر جدید
- `/editpoem` - ویرایش شعر
- `/like [poem_id]` - لایک کردن شعر
- `/help` - راهنمای کامل
- `/about` - اطلاعات ربات
- `/myprofile` - پروفایل کاربر

## ساختار دیتابیس

### جداول

1. **poems** - جدول اصلی اشعار
   - id, bot_user_id, bot_mother_id, bot_id, title, poem_type, status, likes_count

2. **poem_versions** - نسخه‌بندی اشعار
   - id, poem_id, parent_version_id, version_number, created_by

3. **poem_lines** - مصرع‌ها/جملات شعر
   - id, poem_id, version_id, line_number, content, line_type

4. **poem_likes** - لایک‌های شعر
   - id, poem_id, bot_user_id

5. **poem_suggestions** - پیشنهادات مصرع
   - id, poem_id, suggested_by, line_content, status

6. **poem_collaborations** - همکاری‌ها (فورک‌ها)
   - id, original_poem_id, forked_poem_id, forked_by

## فایل‌های مرتبط

### Controllers
- `app/Http/Controllers/PoemBotController.php` - Controller اصلی ربات

### Services
- `app/Interfaces/Services/PoemBotService.php` - Interface
- `app/Services/PoemBotServiceImpl.php` - Implementation

### Repositories
- `app/Interfaces/Repositories/PoemRepository.php`
- `app/Repositories/PoemRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemVersionRepository.php`
- `app/Repositories/PoemVersionRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemLineRepository.php`
- `app/Repositories/PoemLineRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemLikeRepository.php`
- `app/Repositories/PoemLikeRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemSuggestionRepository.php`
- `app/Repositories/PoemSuggestionRepositoryImpl.php`

### Models
- `app/Models/Poem.php`
- `app/Models/PoemVersion.php`
- `app/Models/PoemLine.php`
- `app/Models/PoemLike.php`
- `app/Models/PoemSuggestion.php`
- `app/Models/PoemCollaboration.php`

### Migrations
- `database/migrations/YYYY_MM_DD_create_poems_table.php`
- `database/migrations/YYYY_MM_DD_create_poem_versions_table.php`
- `database/migrations/YYYY_MM_DD_create_poem_lines_table.php`
- `database/migrations/YYYY_MM_DD_create_poem_likes_table.php`
- `database/migrations/YYYY_MM_DD_create_poem_suggestions_table.php`
- `database/migrations/YYYY_MM_DD_create_poem_collaborations_table.php`

### Seeders
- `database/seeders/PoemBotWebhookEndpointSeeder.php`

## API Endpoints

- `POST /api/webhook-poem-bot` - Webhook endpoint برای ربات

## State Management

ربات از `BotUserState` برای مدیریت state استفاده می‌کند:

- `waiting_poem_type` - منتظر انتخاب قالب
- `waiting_poem_line` - منتظر دریافت مصرع
- `editing_poem` - در حال ویرایش شعر
- `selecting_poem_to_edit` - انتخاب شعر برای ویرایش
- `waiting_suggestion` - منتظر پیشنهاد مصرع

## نصب و راه‌اندازی

1. اجرای Migrations:
```bash
php artisan migrate
```

2. اجرای Seeder:
```bash
php artisan db:seed --class=PoemBotWebhookEndpointSeeder
```

3. پاک کردن Cache:
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

## تست

1. ایجاد ربات در Bot Mother
2. تنظیم Webhook با توکن و bot_id
3. ارسال `/start` به ربات
4. تست ارسال شعر جدید
5. تست ویرایش و لایک

## ترجمه‌ها

ترجمه‌ها برای همه 15 زبان موجود در `lang/*/bot.php` اضافه شده است:
- fa, en, ar-IQ, az, bs, de-DE, es, fr, he, pt-BR, pt-PT, ru, tr, ur, zh-CN

## نکات مهم

1. Route باید با `/api/` شروع شود
2. `requires_token` همیشه `true` است
3. توکن از دیتابیس گرفته می‌شود
4. از `Log::info()` برای لاگ استفاده می‌شود
5. از Inline Buttons برای تعاملات استفاده می‌شود

## توسعه‌های آینده

- پیاده‌سازی کامل ویرایش شعر
- اضافه کردن جستجو در اشعار
- اضافه کردن فیلترها و مرتب‌سازی پیشرفته
- اضافه کردن API برای دسترسی خارجی
