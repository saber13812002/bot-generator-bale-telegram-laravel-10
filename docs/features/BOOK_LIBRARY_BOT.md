# ربات کتابخانه هوشمند (Book Library)

## توضیحات

ربات «کتابخانه هوشمند» به کاربران امکان کشف کتاب بر اساس ژانر، دریافت خلاصه صوتی، پیگیری سهمیه پلن، و ارتقای دستی پلن را می‌دهد. محتوا می‌تواند از طریق ربات جداگانه **کتابخوان** (`book-library-reader`) تحویل داده شود.

## Endpoint ها

| endpoint_id | route | نقش |
|-------------|-------|-----|
| `book-library` | `api/webhook-book-library` | ربات اصلی (منو، ژانر، پلن) |
| `book-library-reader` | `api/webhook-book-library-reader` | تحویل صوت/PDF/اینفوگرافی |

## ثبت از Bot Mother

### روش ۱ — پشت سر هم (پیشنهادی)
1. endpoint `book-library` → توکن ربات اصلی
2. در همان گفتگو توکن `book-library-reader` یا «رد»
3. webhook هر دو تنظیم و در `library_bot_configs` لینک می‌شوند

### روش ۲ — reader جداگانه (مثل Bot ID 53)
1. ابتدا `book-library` را بسازید و **Main Bot ID** را یادداشت کنید
2. endpoint `book-library-reader` → توکن reader
3. وقتی خواست Bot ID اصلی را بدهید (مثلاً `52`)

## دستورات کاربر

| ربات | دستورات |
|------|---------|
| کتابخانه اصلی | `/start`, `/help`, منوی دکمه‌ای |
| کتابخوان | `/start`, `/help` — دریافت صوت/PDF |

## فایل‌ها

- `app/Http/Controllers/BookLibraryController.php`
- `app/Http/Controllers/BookLibraryReaderController.php`
- `app/Services/BookLibraryServiceImpl.php`
- `app/Services/BookLibraryDeliveryServiceImpl.php`
- `app/Services/BookLibraryPlanServiceImpl.php`
- `database/seeders/BookLibraryWebhookEndpointSeeder.php`
- `database/seeders/BookLibraryGenreSeeder.php`
- `config/book_library.php`

## پلن‌ها

| پلن | سهمیه |
|-----|-------|
| رایگان | ۳ کتاب |
| plan_100 | ۱۰۰ کتاب |
| plan_300 | ۳۰۰ کتاب |
| plan_1000 | ۱۰۰۰ کتاب |

تأیید پلن: `/library_plan_confirm {id}` در Bot Mother یا Nova → Library Plan Requests.

## مدیریت محتوا

از Nova استفاده کنید:

- Library Genre
- Library Book (+ genres)
- Library Book Media (audio, pdf, infographic)

برای seed نمونه:

```bash
BOOK_LIBRARY_SEED_BOT_ID=123 php artisan db:seed --class=BookLibraryGenreSeeder
```

## ترجمه

کلیدها در `lang/{locale}/book_library.php` (۱۵ زبان).

## فاز ۲ (خارج از محدوده فعلی)

- یادگیری / کویز
- لایک و دیس‌لایک
- ربات ادمین
- پنل وب تحلیلی
- درگاه پرداخت آنلاین
