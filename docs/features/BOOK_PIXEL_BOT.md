# ربات یک پیکسل کتاب

## توضیحات

ربات "یک پیکسل کتاب" به کاربران امکان به اشتراک‌گذاری صفحات کتاب، اسکن صفحات، و ارسال وویس را می‌دهد. محتوا پس از تایید در گروه نظارت، در صف انتشار قرار می‌گیرد و به صورت رندوم در کانال‌های مشخص شده منتشر می‌شود.

## ویژگی‌ها

- 📚 ثبت کتاب‌های جدید با عکس جلد
- 📄 ارسال اسکن صفحات کتاب
- 🎤 ارسال وویس صفحات (اختیاری)
- ✅ سیستم تایید محتوا در گروه نظارت
- 📊 سیستم گیمفیکیشن مستقل (100 امتیاز برای اسکن، 200 امتیاز برای وویس)
- 📢 انتشار خودکار در کانال‌ها (بین 7 شب تا 12 شب)
- 🎯 امتیازدهی و رتبه‌بندی کاربران

## مسیر فایل‌ها

### Controllers
- `app/Http/Controllers/BookPixelController.php` - کنترلر اصلی ربات
- `app/Http/Controllers/BookPixelApprovalController.php` - کنترلر تایید در گروه نظارت

### Services
- `app/Interfaces/Services/BookPixelService.php`
- `app/Services/BookPixelServiceImpl.php`
- `app/Interfaces/Services/BookGamificationService.php`
- `app/Services/BookGamificationServiceImpl.php`
- `app/Interfaces/Services/BookPublishingService.php`
- `app/Services/BookPublishingServiceImpl.php`

### Repositories
- `app/Interfaces/Repositories/BookRepository.php`
- `app/Repositories/BookRepositoryImpl.php`
- `app/Interfaces/Repositories/BookPageScanRepository.php`
- `app/Repositories/BookPageScanRepositoryImpl.php`
- `app/Interfaces/Repositories/BookUserScoreRepository.php`
- `app/Repositories/BookUserScoreRepositoryImpl.php`

### Models
- `app/Models/Book.php`
- `app/Models/BookPage.php`
- `app/Models/BookPageScan.php`
- `app/Models/BookPageVoice.php`
- `app/Models/BookPublishingQueue.php`
- `app/Models/BookPublishingChannel.php`
- `app/Models/BookModerationGroup.php`
- `app/Models/BookUserScore.php`

### Jobs & Commands
- `app/Jobs/PublishBookPageJob.php` - Job برای ارسال به کانال‌ها
- `app/Console/Commands/ScheduleBookPublishing.php` - Command برای زمان‌بندی

### Migrations
- `database/migrations/2026_01_25_052433_create_books_table.php`
- `database/migrations/2026_01_25_052506_create_book_pages_table.php`
- `database/migrations/2026_01_25_052514_create_book_page_scans_table.php`
- `database/migrations/2026_01_25_052523_create_book_page_voices_table.php`
- `database/migrations/2026_01_25_052531_create_book_publishing_queue_table.php`
- `database/migrations/2026_01_25_052539_create_book_publishing_channels_table.php`
- `database/migrations/2026_01_25_052547_create_book_moderation_groups_table.php`
- `database/migrations/2026_01_25_052601_create_book_user_scores_table.php`

### Seeders
- `database/seeders/BookPixelWebhookEndpointSeeder.php`

## ساختار دیتابیس

### جدول `books`
ذخیره اطلاعات کتاب‌ها شامل نام، ISBN، شابک، و عکس جلد.

### جدول `book_pages`
ذخیره صفحات کتاب‌ها.

### جدول `book_page_scans`
ذخیره اسکن‌های صفحات با وضعیت (pending_approval, approved, rejected).

### جدول `book_page_voices`
ذخیره وویس‌های صفحات.

### جدول `book_publishing_queue`
صف انتشار محتوا برای کانال‌ها.

### جدول `book_publishing_channels`
کانال‌های انتشار (تلگرام و بله).

### جدول `book_moderation_groups`
گروه‌های نظارت برای تایید محتوا.

### جدول `book_user_scores`
امتیازات کاربران (مستقل برای هر ربات).

## فلو کار ربات

### 1. شروع ربات (/start)
- خوش‌آمدگویی
- پرسیدن نام کتاب یا ISBN/شابک

### 2. ثبت کتاب جدید
- دریافت نام کتاب
- دریافت ISBN (اختیاری)
- دریافت شابک (اختیاری)
- دریافت عکس جلد
- ثبت کتاب در دیتابیس

### 3. ارسال اسکن صفحه
- دریافت شماره صفحه
- دریافت عکس اسکن
- ایجاد یا پیدا کردن BookPage
- ثبت BookPageScan با status='pending_approval'
- ارسال به گروه نظارت

### 4. ارسال وویس (اختیاری)
- بعد از تایید اسکن، امکان ارسال وویس
- دریافت وویس
- ثبت BookPageVoice
- امتیازدهی: 200 امتیاز

### 5. تایید در گروه نظارت
- بررسی پیام در گروه نظارت
- دکمه‌های تایید/رد
- در صورت تایید: تغییر status به 'approved' و اضافه به صف انتشار
- امتیازدهی: 100 امتیاز برای اسکن

### 6. صف انتشار
- پس از تایید، BookPageScan به BookPublishingQueue اضافه می‌شود
- Command هر ساعت بین 7 شب تا 12 شب اجرا می‌شود
- انتخاب رندوم یک مورد از صف
- ارسال به کانال‌های فعال

### 7. گیمفیکیشن
- هر اسکن تایید شده: 100 امتیاز
- هر وویس: 200 امتیاز
- دستور /score برای نمایش امتیاز کاربر

## API Endpoints

- `POST /api/webhook-book-pixel` - Webhook اصلی ربات
- `POST /api/webhook-book-pixel-approval` - Webhook تایید در گروه نظارت

## تنظیمات

### گروه نظارت
برای هر ربات باید یک گروه نظارت در جدول `book_moderation_groups` ثبت شود:
- `bot_id`: شناسه ربات
- `group_chat_id`: شناسه گروه
- `origin`: 'telegram' یا 'bale'
- `is_active`: true

### کانال‌های انتشار
برای هر ربات باید کانال‌های انتشار در جدول `book_publishing_channels` ثبت شوند:
- `bot_id`: شناسه ربات
- `channel_chat_id`: شناسه کانال
- `origin`: 'telegram' یا 'bale'
- `is_active`: true

## زمان‌بندی

Command `ScheduleBookPublishing` هر ساعت بین 19:00 تا 23:59 اجرا می‌شود و یک مورد رندوم از صف را انتخاب کرده و به کانال‌ها ارسال می‌کند.

## تست

1. اجرای migrations:
```bash
php artisan migrate
```

2. اجرای seeder:
```bash
php artisan db:seed --class=BookPixelWebhookEndpointSeeder
```

3. تنظیم گروه نظارت و کانال‌های انتشار در دیتابیس

4. تست ربات با دستور /start

5. بررسی لاگ‌ها:
```bash
tail -f storage/logs/laravel.log
```

## نکات مهم

- ربات باید ادمین گروه نظارت باشد
- توکن ربات باید از دیتابیس گرفته شود
- Route ها باید با `/api/` شروع شوند
- ترجمه‌ها باید برای همه 15 زبان اضافه شوند
- سیستم گیمفیکیشن مستقل برای هر ربات عمل می‌کند
