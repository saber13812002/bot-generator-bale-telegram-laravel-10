---
name: ربات تلگرام شعر و موسیقی
overview: ساخت یک ربات تلگرام کامل برای پلتفرم شعر و موسیقی با قابلیت‌های ارسال، ویرایش، نسخه‌بندی، لایک، پیشنهاد مصرع و همکاری. شامل ساختار دیتابیس، API بک‌اند، و ربات تلگرام با دکمه‌های شیشه‌ای.
todos:
  - id: create_migrations
    content: ایجاد 6 migration برای جداول poems, poem_versions, poem_lines, poem_likes, poem_suggestions, poem_collaborations
    status: completed
  - id: create_models
    content: ایجاد Models با Relations مناسب (Poem, PoemVersion, PoemLine, PoemLike, PoemSuggestion, PoemCollaboration)
    status: completed
    dependencies:
      - create_migrations
  - id: create_repositories
    content: ایجاد Repository Interfaces و Implementations برای دسترسی به دیتابیس
    status: completed
    dependencies:
      - create_models
  - id: register_repositories
    content: ثبت Repositories در AppServiceProvider
    status: completed
    dependencies:
      - create_repositories
  - id: create_service
    content: ایجاد Service Interface و Implementation برای منطق کسب‌وکار ربات
    status: completed
    dependencies:
      - register_repositories
  - id: register_service
    content: ثبت Service در AppServiceProvider
    status: completed
    dependencies:
      - create_service
  - id: create_controller
    content: ایجاد PoemBotController با مدیریت callback queries و inline buttons
    status: completed
    dependencies:
      - register_service
  - id: create_route
    content: اضافه کردن route در routes/api.php با /api/webhook-poem-bot
    status: completed
    dependencies:
      - create_controller
  - id: create_seeder
    content: ایجاد PoemBotWebhookEndpointSeeder برای ثبت webhook endpoint
    status: completed
    dependencies:
      - create_route
  - id: add_translations
    content: اضافه کردن کلیدهای ترجمه به lang/*/bot.php برای همه 15 زبان
    status: completed
    dependencies:
      - create_controller
  - id: create_documentation
    content: ایجاد مستندات در docs/features/POEM_BOT.md
    status: completed
    dependencies:
      - create_seeder
---

# پلان ساخت ربات تلگرام شعر و موسیقی

## خلاصه

ساخت یک ربات تلگرام کامل برای پلتفرم شعر و موسیقی که امکان ارسال، ویرایش، نسخه‌بندی، لایک، پیشنهاد مصرع و همکاری را فراهم می‌کند.

## ساختار دیتابیس

### جداول مورد نیاز:

1. **poems** - جدول اصلی اشعار

   - id, bot_user_id, bot_mother_id, bot_id, title, poem_type (classic/novel), status (draft/published), likes_count, created_at, updated_at

2. **poem_versions** - نسخه‌بندی اشعار (مشابه Git)

   - id, poem_id, parent_version_id, version_number, created_by, created_at

3. **poem_lines** - مصرع‌ها/جملات شعر

   - id, poem_id, version_id, line_number, content, line_type (classic_line/novel_sentence), created_at

4. **poem_likes** - لایک‌های شعر

   - id, poem_id, bot_user_id, created_at

5. **poem_suggestions** - پیشنهادات مصرع برای اشعار دیگران

   - id, poem_id, suggested_by, line_content, status (pending/accepted/rejected), created_at

6. **poem_collaborations** - همکاری‌ها (فورک‌ها)

   - id, original_poem_id, forked_poem_id, forked_by, created_at

## فایل‌های مورد نیاز

### 1. Migrations

- `YYYY_MM_DD_create_poems_table.php`
- `YYYY_MM_DD_create_poem_versions_table.php`
- `YYYY_MM_DD_create_poem_lines_table.php`
- `YYYY_MM_DD_create_poem_likes_table.php`
- `YYYY_MM_DD_create_poem_suggestions_table.php`
- `YYYY_MM_DD_create_poem_collaborations_table.php`

### 2. Models

- `app/Models/Poem.php`
- `app/Models/PoemVersion.php`
- `app/Models/PoemLine.php`
- `app/Models/PoemLike.php`
- `app/Models/PoemSuggestion.php`
- `app/Models/PoemCollaboration.php`

### 3. Repositories

- `app/Interfaces/Repositories/PoemRepository.php` (Interface)
- `app/Repositories/PoemRepositoryImpl.php` (Implementation)
- `app/Interfaces/Repositories/PoemVersionRepository.php`
- `app/Repositories/PoemVersionRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemLineRepository.php`
- `app/Repositories/PoemLineRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemLikeRepository.php`
- `app/Repositories/PoemLikeRepositoryImpl.php`
- `app/Interfaces/Repositories/PoemSuggestionRepository.php`
- `app/Repositories/PoemSuggestionRepositoryImpl.php`

### 4. Services

- `app/Interfaces/Services/PoemBotService.php` (Interface)
- `app/Services/PoemBotServiceImpl.php` (Implementation)

### 5. Controllers

- `app/Http/Controllers/PoemBotController.php` - ربات تلگرام
- `app/Http/Controllers/Api/PoemApiController.php` - API بک‌اند (اختیاری برای آینده)

### 6. Routes

- اضافه کردن route در `routes/api.php`: `Route::post('/api/webhook-poem-bot', [PoemBotController::class, 'webhook']);`

### 7. Seeder

- `database/seeders/PoemBotWebhookEndpointSeeder.php`

### 8. ترجمه‌ها

- اضافه کردن کلیدهای ترجمه به `lang/*/bot.php` برای همه 15 زبان

### 9. مستندات

- `docs/features/POEM_BOT.md`

## ویژگی‌های ربات

### 1. کامند /start

- نمایش پیام خوش‌آمدگویی
- نمایش دکمه‌های شیشه‌ای اصلی:
  - 📝 ارسال شعر جدید
  - ✏️ ویرایش شعر
  - 📚 شعرهای من
  - ❤️ لایک‌ها
  - 🔍 مشاهده فهرست
  - 💡 پیشنهاد مصرع
  - ℹ️ راهنما

### 2. ارسال شعر جدید (/newpoem)

- انتخاب قالب: کلاسیک یا نو
- دریافت مصرع‌ها/جملات به ترتیب
- دکمه "افزودن مصرع جدید" برای هر مصرع
- دکمه "پایان و انتشار" برای اتمام
- ذخیره در state با BotUserState

### 3. ویرایش شعر (/editpoem)

- نمایش فهرست اشعار کاربر
- انتخاب شعر برای ویرایش
- نمایش مصرع‌های فعلی با دکمه‌های:
  - ✏️ ویرایش مصرع
  - 🗑️ حذف مصرع
  - ⬆️ جابجایی بالا
  - ⬇️ جابجایی پایین
- ذخیره تغییرات

### 4. نسخه جدید

- پس از ویرایش، امکان ایجاد نسخه جدید
- دکمه‌های:
  - 💾 ذخیره به عنوان پیش‌نویس
  - 🚀 انتشار نسخه جدید
- ثبت در جدول poem_versions

### 5. لایک (/like)

- امکان لایک کردن اشعار
- نمایش تعداد لایک‌ها
- کامند: `/like [poem_id]`
- دکمه لایک در نمایش هر شعر

### 6. مشاهده فهرست

- نمایش اشعار به ترتیب لایک یا تاریخ
- دکمه‌های:
  - 📊 بر اساس لایک
  - 📅 بر اساس تاریخ
  - 🔍 جستجو

### 7. پیشنهاد مصرع

- انتخاب شعر
- ارسال پیشنهاد مصرع
- ذخیره در جدول poem_suggestions
- نمایش به صاحب شعر برای تایید/رد

### 8. کامندهای فرعی

- `/help` - راهنمای کامل
- `/about` - اطلاعات ربات
- `/myprofile` - پروفایل کاربر

## جریان کار (State Management)

استفاده از `BotUserState` برای مدیریت state:

- `waiting_poem_type` - منتظر انتخاب قالب
- `waiting_poem_line` - منتظر دریافت مصرع
- `editing_poem` - در حال ویرایش شعر
- `selecting_poem_to_edit` - انتخاب شعر برای ویرایش
- `waiting_suggestion` - منتظر پیشنهاد مصرع

## ثبت در AppServiceProvider

```php
// Repositories
$this->app->bind(PoemRepository::class, PoemRepositoryImpl::class);
$this->app->bind(PoemVersionRepository::class, PoemVersionRepositoryImpl::class);
$this->app->bind(PoemLineRepository::class, PoemLineRepositoryImpl::class);
$this->app->bind(PoemLikeRepository::class, PoemLikeRepositoryImpl::class);
$this->app->bind(PoemSuggestionRepository::class, PoemSuggestionRepositoryImpl::class);

// Services
$this->app->bind(PoemBotService::class, PoemBotServiceImpl::class);
```

## نکات مهم

1. **Route:** باید با `/api/` شروع شود: `/api/webhook-poem-bot`
2. **requires_token:** همیشه `true` در Seeder
3. **توکن:** از دیتابیس گرفته شود (الگوی createBotInstance)
4. **لاگ:** از `Log::info()` استفاده شود (نه LogHelper با Request معمولی)
5. **ترجمه:** همه کلیدها برای 15 زبان اضافه شود
6. **Inline Buttons:** استفاده از `$bot->buildInlineKeyBoardButton()` و `BotHelper::sendKeyboardMessage()`

## ترتیب اجرا

1. ایجاد Migrations
2. ایجاد Models با Relations
3. ایجاد Repository Interfaces و Implementations
4. ثبت Repositories در AppServiceProvider
5. ایجاد Service Interface و Implementation
6. ثبت Service در AppServiceProvider
7. ایجاد Controller ربات
8. ایجاد Route
9. ایجاد Seeder
10. اضافه کردن ترجمه‌ها
11. ایجاد مستندات
12. تست و اجرای Migration و Seeder