---
name: ربات تست روانشناسی
overview: پیاده‌سازی یک ربات تست روانشناسی کامل با امکان تعریف سوالات، دسته‌بندی‌ها، وزن‌ها، مدیریت ادمین‌ها، محاسبه امتیازات و نمایش گزارش‌ها.
todos:
  - id: create-migrations
    content: ایجاد 5 migration برای جداول psychology_test_bots, psychology_test_categories, psychology_test_questions, psychology_test_results, psychology_test_bot_admins
    status: completed
  - id: create-models
    content: ایجاد 5 Model با relationships مناسب
    status: completed
  - id: update-webhook-endpoint-helper
    content: اضافه کردن endpoint جدید به WebhookEndpointHelper
    status: completed
  - id: update-bot-mother-state-helper
    content: اضافه کردن state های جدید به BotMotherStateHelper
    status: completed
  - id: update-bot-mother-controller
    content: اضافه کردن منطق ساخت ربات و import سوالات در BotMotherController
    status: completed
  - id: create-psychology-test-controller
    content: ایجاد PsychologyTestBotController با منطق تست و مدیریت ادمین‌ها
    status: completed
  - id: add-route
    content: اضافه کردن route در routes/api.php
    status: completed
  - id: add-translations
    content: اضافه کردن کلیدهای ترجمه به فایل‌های lang
    status: completed
  - id: test-implementation
    content: تست کامل جریان کار از ساخت ربات تا دریافت گزارش
    status: completed
---

# طراحی و پیاده‌سازی ربات تست روانشناسی

## خلاصه

ایجاد یک ربات تست روانشناسی که امکان تعریف سوالات 5 گزینه‌ای با دسته‌بندی‌ها، وزن‌ها و محاسبه امتیازات را فراهم می‌کند. این ربات قابلیت مدیریت ادمین‌ها (1 تا 3 نفر)، ذخیره نتایج تست‌ها و نمایش گزارش‌ها را دارد.

## ساختار دیتابیس

### 1. جدول `psychology_test_bots`

- `id`: شناسه
- `bot_id`: ارتباط با جدول `bots`
- `title`: عنوان تست (مثلاً "تست‌های روانشناسی")
- `description`: توضیحات کلی
- `created_at`, `updated_at`

### 2. جدول `psychology_test_categories`

- `id`: شناسه
- `psychology_test_bot_id`: ارتباط با ربات تست
- `name`: نام دسته (مثلاً "درون‌گرا/برون‌گرا")
- `description`: توضیحات دسته (که بعد از import سوالات تعریف می‌شود)
- `created_at`, `updated_at`

### 3. جدول `psychology_test_questions`

- `id`: شناسه
- `psychology_test_bot_id`: ارتباط با ربات تست
- `psychology_test_category_id`: ارتباط با دسته
- `question_text`: متن سوال
- `weight`: وزن سوال (پیش‌فرض 1.0)
- `direction`: جهت سوال (0 = خیلی کم به سمت دسته، 1 = خیلی زیاد به سمت دسته)
- `order`: ترتیب نمایش (برای رندوم کردن بعداً)
- `created_at`, `updated_at`

### 4. جدول `psychology_test_results`

- `id`: شناسه
- `psychology_test_bot_id`: ارتباط با ربات تست
- `chat_id`: شناسه چت کاربر
- `origin`: نوع پیام‌رسان (telegram/bale)
- `result_data`: JSON شامل امتیازات هر دسته
- `completed_at`: تاریخ تکمیل تست
- `created_at`, `updated_at`

### 5. جدول `psychology_test_bot_admins`

- `id`: شناسه
- `psychology_test_bot_id`: ارتباط با ربات تست
- `chat_id`: شناسه چت ادمین
- `origin`: نوع پیام‌رسان (telegram/bale)
- `is_creator`: آیا سازنده ربات است؟
- `created_at`, `updated_at`

## فایل‌های مورد نیاز

### 1. Migrations

- `create_psychology_test_bots_table.php`
- `create_psychology_test_categories_table.php`
- `create_psychology_test_questions_table.php`
- `create_psychology_test_results_table.php`
- `create_psychology_test_bot_admins_table.php`

### 2. Models

- `PsychologyTestBot.php`
- `PsychologyTestCategory.php`
- `PsychologyTestQuestion.php`
- `PsychologyTestResult.php`
- `PsychologyTestBotAdmin.php`

### 3. Controller

- `PsychologyTestBotController.php`: کنترلر اصلی برای مدیریت webhook ربات تست

### 4. Helpers

- به‌روزرسانی `WebhookEndpointHelper.php`: اضافه کردن endpoint جدید
- به‌روزرسانی `BotMotherStateHelper.php`: اضافه کردن state های جدید

### 5. Routes

- اضافه کردن route در `routes/api.php`: `/webhook-psychology-test`

### 6. BotMotherController

- اضافه کردن منطق برای ساخت ربات تست در `BotMotherController.php`
- State های جدید برای import سوالات و تعریف توضیحات دسته‌ها

## جریان کار

### 1. ساخت ربات در ربات مادر

1. کاربر `/start` را در ربات مادر می‌زند
2. لیست endpoint ها نمایش داده می‌شود
3. کاربر endpoint "تست روانشناسی" را انتخاب می‌کند
4. توکن ربات را وارد می‌کند
5. ربات ساخته می‌شود و state به `STATE_WAITING_PSYCHOLOGY_QUESTIONS` تغییر می‌کند

### 2. Import سوالات

1. کاربر سوالات را به فرمت زیر وارد می‌کند:
   ```
   سوال متن سوال [دسته, وزن, جهت]
   ```


مثال:

   ```
   آیا در جمع‌ها راحت هستید؟ [برون‌گرا, 1.0, 1]
   ترجیح می‌دهید تنها باشید؟ [درون‌گرا, 0.9, 1]
   ```

2. سیستم سوالات را parse می‌کند و دسته‌بندی‌های جدید را شناسایی می‌کند
3. بعد از وارد کردن "پایان"، سیستم لیست دسته‌ها را نمایش می‌دهد
4. State به `STATE_WAITING_CATEGORY_DESCRIPTIONS` تغییر می‌کند

### 3. تعریف توضیحات دسته‌ها

1. برای هر دسته، سیستم از کاربر توضیحات می‌خواهد
2. کاربر توضیحات را وارد می‌کند
3. بعد از تکمیل همه دسته‌ها، webhook تنظیم می‌شود

### 4. استفاده از ربات

1. کاربر `/start` را در ربات تست می‌زند
2. ربات سوالات را به صورت رندوم نمایش می‌دهد
3. هر سوال با 5 گزینه (خیلی کم، کم، متوسط، زیاد، خیلی زیاد) نمایش داده می‌شود
4. بعد از پاسخ به همه سوالات، امتیازات محاسبه می‌شود
5. گزارش نهایی به کاربر نمایش داده می‌شود

### 5. مدیریت ادمین‌ها

1. سازنده ربات می‌تواند با دستور `/add_admin @username` ادمین اضافه کند (تا 3 نفر)
2. ادمین‌ها می‌توانند نتایج کاربران را مشاهده کنند

## منطق محاسبه امتیاز

برای هر دسته:

1. سوالات مربوط به آن دسته را جمع‌آوری کن
2. برای هر پاسخ:

   - اگر `direction = 1`: امتیاز = (شماره گزینه - 1) / 4 * weight
   - اگر `direction = 0`: امتیاز = (4 - شماره گزینه + 1) / 4 * weight

3. مجموع امتیازات را برای دسته محاسبه کن
4. امتیاز نهایی = مجموع امتیازات / تعداد سوالات دسته

## نکات مهم

1. سوالات باید به صورت رندوم نمایش داده شوند (برای جلوگیری از الگوگیری کاربر)
2. گزینه‌های هر سوال باید به صورت رندوم چیده شوند (این کار توسط طراح سوالات انجام می‌شود)
3. همه نتایج ذخیره می‌شوند و کاربر می‌تواند تاریخچه تست‌های خود را ببیند
4. ادمین‌ها (سازنده + حداکثر 2 ادمین دیگر) می‌توانند نتایج را مشاهده کنند
5. از سیستم ترجمه Laravel برای متن‌ها استفاده شود

## فایل‌های اصلی که تغییر می‌کنند

- `app/Helpers/WebhookEndpointHelper.php`: اضافه کردن endpoint جدید
- `app/Helpers/BotMotherStateHelper.php`: اضافه کردن state های جدید
- `app/Http/Controllers/BotMotherController.php`: اضافه کردن منطق ساخت ربات
- `routes/api.php`: اضافه کردن route جدید
- ایجاد `app/Http/Controllers/PsychologyTestBotController.php`
- ایجاد 5 Model جدید
- ایجاد 5 Migration جدید