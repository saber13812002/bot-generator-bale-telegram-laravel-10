# ربات کتابخانه محتوا (Content Library)

## معماری جدید (Content-centric)

ربات `book-library` با مدل **دسته‌بندی + صف ترتیبی** کار می‌کند:

- هر **دسته** (`content_categories`) یک موضوع است
- هر **آیتم** (`content_items`) با `queue_order` در صف قرار دارد
- کاربر با انتخاب دسته، آیتم بعدی (`last_position + 1`) را دریافت می‌کند
- فاز فعلی: فقط **audio** (`content_assets`)
- **reader** (`book-library-reader`) اختیاری است — فعلاً استفاده نکنید؛ محتوا در همین ربات ارسال می‌شود

## Endpoint ها

| endpoint_id | route | نقش |
|-------------|-------|-----|
| `book-library` | `api/webhook-book-library` | ربات اصلی (کاربر + ادمین مالک) |
| `book-library-reader` | `api/webhook-book-library-reader` | اختیاری — فاز بعد |

## ثبت از Bot Mother

1. endpoint `book-library` → توکن ربات
2. مرحله reader: **«رد»** بزنید (پیشنهادی)
3. دسته‌ها را seed کنید (پایین)
4. مدیریت محتوا: `/content` در Bot Mother یا دستورات داخل ربات (مالک)

## جریان کاربر

1. `/start` → لیست دسته‌ها (صفحه‌بندی)
2. انتخاب دسته → ارسال آیتم بعدی صف (صوت)
3. «کتاب‌های من» → تاریخچه پیشرفت per category
4. «ارتقای پلن» → همان `library_plan_*`

## ادمین داخل ربات (مالک)

| دستور / رویداد | رفتار |
|----------------|--------|
| ارسال voice/audio | ذخیره در `content_pending_uploads` + File ID |
| `/addFileToCategory {id}` | انتخاب دسته → append به انتهای صف |
| `/addCategory` | نام دسته → اعلان بله/خیر |
| `/broadcast` | متن/صوت → فیلتر → Job |

## Bot Mother `/content` (ادمین پلتفرم)

- لیست ربات‌های محتوایی (`config/content_bots.php`)
- دسته‌ها، فایل‌های pending، آمار، broadcast

## Seeder دسته‌ها (۱۶ دسته)

```bash
CONTENT_SEED_BOT_ID=<bot_id> php artisan db:seed --class=ContentCategorySeeder
```

یا (سازگاری قدیمی):

```bash
BOOK_LIBRARY_SEED_BOT_ID=<bot_id> php artisan db:seed --class=BookLibraryGenreSeeder
```

## جداول `content_*`

- `content_categories` — دسته per bot_id
- `content_items` — آیتم صف
- `content_assets` — audio/pdf/…
- `content_pending_uploads` — قبل از دسته‌بندی
- `content_user_progress` — last_position per user+category
- `content_broadcast_jobs` — پیام همگانی

داده‌های `library_*` در migration به `content_*` منتقل می‌شوند.

## پلن‌ها

همان `config/book_library.php` و `library_user_subscriptions`.

تأیید: `/library_plan_confirm {id}` در Bot Mother.

## Nova

- Content Category
- Content Item (+ Assets)

## فایل‌های کلیدی

- `app/Http/Controllers/BookLibraryController.php`
- `app/Services/ContentQueueServiceImpl.php`
- `app/Services/ContentDeliveryServiceImpl.php`
- `app/Services/ContentAdminService.php`
- `app/Services/ContentBotMotherService.php`
- `app/Jobs/ContentBroadcastJob.php`
- `app/Jobs/NotifyNewCategoryJob.php`
- `database/migrations/2026_06_16_100000_create_content_tables.php`
- `database/seeders/ContentCategorySeeder.php`

## Deploy

```bash
php artisan migrate
CONTENT_SEED_BOT_ID=<id> php artisan db:seed --class=ContentCategorySeeder
php artisan cache:clear
```

## ترجمه

کلیدها در `lang/{locale}/book_library.php` (۱۵ زبان).
