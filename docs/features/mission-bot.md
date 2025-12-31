# ربات ماموریت (Mission Bot)

## توضیحات

ربات ماموریت برای مدیریت تسک‌های کاربران و سیستم امتیازدهی طراحی شده است. کاربران می‌توانند تسک رزرو کنند، انجام دهند، و پس از تایید امتیاز دریافت کنند.

## مسیر فایل‌ها

- Controller: `app/Http/Controllers/MissionBotController.php`
- Approval Controller: `app/Http/Controllers/TaskApprovalController.php`
- Models:
  - `app/Models/Task.php`
  - `app/Models/Prompt.php`
  - `app/Models/Training.php`
- Migrations:
  - `database/migrations/*_create_tasks_table.php`
  - `database/migrations/*_create_prompts_table.php`
  - `database/migrations/*_create_trainings_table.php`
  - `database/migrations/*_add_message_id_to_tasks_table.php`

## ساختار دیتابیس

### جدول `tasks`
- `id`: شناسه منحصر به فرد
- `task_name`: نام تسک
- `assigned_user_id`: شناسه پرسنل اختصاص داده شده
- `task_status`: وضعیت تسک (`reserved`, `in_progress`, `pending_approval`, `approved`, `rejected`)
- `task_time`: زمان انجام تسک
- `assigned_time`: زمان اختصاص تسک
- `reserved_time`: زمان رزرو (2 ساعت آینده)
- `points`: امتیاز تسک
- `final_link`: لینک نهایی ارسال شده توسط کاربر
- `approval_message_id`: شناسه پیام در گروه تایید
- `rejection_reason`: دلیل رد (در صورت رد شدن)
- `approved_by_chat_id`: شناسه کاربر تایید کننده
- `approved_at`: زمان تایید
- `rejected_at`: زمان رد
- `created_at`, `updated_at`: زمان ایجاد و به‌روزرسانی

### جدول `prompts`
- `id`: شناسه منحصر به فرد
- `content`: متن پرامپت
- `task_id`: شناسه تسک مرتبط
- `created_at`, `updated_at`: زمان ایجاد و به‌روزرسانی

### جدول `trainings`
- `id`: شناسه منحصر به فرد
- `task_id`: شناسه تسک مرتبط
- `training_content`: محتوای آموزشی
- `training_url`: آدرس محتوای آموزشی
- `created_at`, `updated_at`: زمان ایجاد و به‌روزرسانی

## فرآیند کار

### 1. ورود کاربر

کاربر با لینک اختصاصی خود وارد ربات می‌شود:
```
/start?personnel_id=1
```

### 2. رزرو تسک

کاربر با دستور `/reserve` یک تسک رزرو می‌کند:
- تسک برای 2 ساعت آینده رزرو می‌شود
- کاربر باید تا قبل از پایان زمان رزرو لینک نهایی را ارسال کند

### 3. ارسال لینک نهایی

کاربر پس از انجام تسک، لینک نهایی را در ربات ارسال می‌کند:
- لینک باید یک URL معتبر باشد
- تسک به وضعیت `pending_approval` تغییر می‌کند
- پیام برای تایید به گروه تلگرامی ارسال می‌شود

### 4. تایید یا رد تسک

در گروه تلگرامی تایید:
- ادمین‌ها می‌توانند با reply کردن کلمه "تایید" تسک را تایید کنند
- برای رد، هر متن دیگری را reply می‌کنند

### 5. ذخیره امتیاز و ارتقاء سطح

پس از تایید:
- امتیاز به حساب کاربر اضافه می‌شود
- درجه کاربر بر اساس امتیاز کل به‌روز می‌شود
- کاربر از طریق ربات ماموریت مطلع می‌شود

## دستورات ربات

- `/start` - شروع و ثبت اطلاعات کاربر
- `/reserve` - رزرو یک تسک جدید
- `/status` - مشاهده وضعیت تسک‌ها
- `/help` - نمایش راهنما

## تنظیمات محیط (.env)

```env
# توکن ربات ماموریت
MISSION_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
MISSION_BOT_TOKEN_BALE=your_bale_bot_token

# شناسه گروه تلگرامی برای تایید تسک‌ها
MISSION_APPROVAL_GROUP_CHAT_ID=your_group_chat_id
```

## Routes

```
POST /api/webhook-mission-bot - Webhook اصلی ربات ماموریت
POST /api/webhook-task-approval - Webhook برای تایید/رد تسک‌ها در گروه
```

## سیستم امتیازدهی

امتیازدهی بر اساس درجات زیر است:
- سرباز صفر: 0-99 امتیاز
- سرباز یک: 100-499 امتیاز
- سرباز دو: 500-999 امتیاز
- سرباز سه: 1000+ امتیاز

## نکات مهم

- هر کاربر در یک زمان فقط می‌تواند یک تسک فعال داشته باشد
- زمان رزرو تسک 2 ساعت است
- پس از پایان زمان رزرو، تسک به صورت خودکار رد می‌شود
- لینک‌های ارسال شده باید URL معتبر باشند

## لینک به README اصلی

[README.md](../README.md)

