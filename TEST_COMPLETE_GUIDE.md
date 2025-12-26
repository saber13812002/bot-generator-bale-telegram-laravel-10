# راهنمای کامل تست سیستم

این راهنما مراحل کامل تست سیستم Task و Mission را توضیح می‌دهد.

## مرحله 1: اجرای Seeder

```bash
# اجرای migrations (اگر هنوز اجرا نشده)
php artisan migrate

# اجرای seeders پایه
php artisan db:seed --class=TenantSeeder
php artisan db:seed --class=PersonnelSeeder

# اجرای seeder کامل تست
php artisan db:seed --class=CompleteTestSeeder
```

بعد از اجرای `CompleteTestSeeder`، شما خواهید داشت:
- ✅ یک Task با Prompt و Content
- ✅ یک Mission با Prompt و Content
- ✅ همه به یک پرسنل assign شده‌اند

## مرحله 2: بررسی نتایج Seeder

بعد از اجرای Seeder، اطلاعات زیر نمایش داده می‌شود:

```
📋 Task:
   Task ID: 1
   Task Name: تسک تست - 2025-12-25 15:00
   Task Prompt ID: 1
   Task Content ID: 1
   Assigned to Personnel: 1 (علی احمدی)

🎯 Mission:
   Mission ID: 1
   Mission Title: ماموریت تست - 2025-12-25 15:00
   Mission Prompt ID: 2
   Mission Content ID: 2
   Assigned to Personnel: 1 (علی احمدی)
```

## مرحله 3: تست Task

### 3.1 مشاهده Task در ربات

1. پرسنل در ربات ماموریت دستور `/status` را ارسال می‌کند
2. باید Task خود را ببیند

### 3.2 ارسال لینک نتیجه

1. پرسنل در ربات ماموریت لینک نتیجه را ارسال می‌کند:
   ```
   https://example.com/task-result/12345
   ```
2. سیستم به صورت خودکار:
   - لینک را در جدول `tasks` ذخیره می‌کند
   - وضعیت را به `pending_approval` تغییر می‌دهد
   - پیام را در گروه تایید ارسال می‌کند

### 3.3 تایید Task

1. در گروه تایید، به پیام reply کنید
2. کلمه "تایید" را ارسال کنید
3. سیستم:
   - وضعیت را به `approved` تغییر می‌دهد
   - `approved_by_chat_id` را ثبت می‌کند
   - امتیاز به پرسنل اضافه می‌شود

## مرحله 4: تست Mission

### 4.1 آپلود آموزش‌ها با ربات مدیا

1. به ربات مدیا بروید
2. دستور زیر را ارسال کنید:
   ```
   /upload_mission_1
   ```
3. سپس فایل‌های آموزشی را ارسال کنید:
   - عکس
   - ویدیو
   - صوت
   - PDF
4. برای پایان آپلود:
   ```
   /done
   ```

### 4.2 ارسال آموزش‌ها به پرسنل

در ربات مدیا دستور زیر را ارسال کنید:
```
/send_training_1_to_1
```
(ماموریت 1 به پرسنل 1)

یا پرسنل در ربات ماموریت:
```
/get_training
```

### 4.3 ارسال لینک نتیجه

1. پرسنل در ربات ماموریت لینک نتیجه را ارسال می‌کند:
   ```
   https://example.com/mission-result/12345
   ```
2. سیستم به صورت خودکار:
   - لینک را در جدول `mission_personnel` ذخیره می‌کند
   - وضعیت را به `pending_approval` تغییر می‌دهد
   - پیام را در گروه تایید ارسال می‌کند

### 4.4 تایید Mission

1. در گروه تایید، به پیام reply کنید
2. کلمه "تایید" را ارسال کنید
3. سیستم:
   - وضعیت را به `approved` تغییر می‌دهد
   - `approved_by_chat_id` را ثبت می‌کند
   - امتیاز به پرسنل اضافه می‌شود

## مرحله 5: بررسی در دیتابیس

### بررسی Task

```sql
-- مشاهده Task
SELECT * FROM tasks WHERE id = 1;

-- مشاهده Prompt مربوط به Task
SELECT * FROM prompts WHERE task_id = 1;

-- مشاهده Content مربوط به Task (اگر وجود دارد)
SELECT * FROM contents WHERE id IN (
    SELECT content_id FROM task_contents WHERE task_id = 1
);
```

### بررسی Mission

```sql
-- مشاهده Mission
SELECT * FROM missions WHERE id = 1;

-- مشاهده Prompt مربوط به Mission
SELECT * FROM prompts WHERE mission_id = 1;

-- مشاهده Content مربوط به Mission
SELECT c.*, mc.sort_order
FROM contents c
JOIN mission_contents mc ON c.id = mc.content_id
WHERE mc.mission_id = 1
ORDER BY mc.sort_order;

-- مشاهده پرسنل assign شده
SELECT mp.*, p.first_name, p.last_name
FROM mission_personnel mp
JOIN personnel p ON mp.personnel_id = p.id
WHERE mp.mission_id = 1;
```

### بررسی تاییدکننده

```sql
-- برای Task
SELECT approved_by_chat_id, approved_at, status
FROM tasks
WHERE id = 1 AND status = 'approved';

-- برای Mission
SELECT approved_by_chat_id, approved_at, status
FROM mission_personnel
WHERE mission_id = 1 AND personnel_id = 1 AND status = 'approved';
```

## مرحله 6: بررسی امتیازات

```sql
-- مشاهده امتیاز کل پرسنل
SELECT 
    p.id,
    p.first_name,
    p.last_name,
    (
        SELECT COALESCE(SUM(points), 0)
        FROM tasks
        WHERE assigned_user_id = p.id AND task_status = 'approved'
    ) +
    (
        SELECT COALESCE(SUM(m.points), 0)
        FROM missions m
        JOIN mission_personnel mp ON m.id = mp.mission_id
        WHERE mp.personnel_id = p.id AND mp.status = 'approved'
    ) as total_points
FROM personnel p
WHERE p.id = 1;
```

## نکات مهم

1. **Queue Worker**: برای ارسال ترتیبی محتواها، باید Queue Worker در حال اجرا باشد:
   ```bash
   php artisan queue:work
   ```

2. **Webhook**: مطمئن شوید که webhook‌های ربات‌ها به درستی تنظیم شده‌اند

3. **گروه تایید**: ربات باید در گروه عضو باشد و دسترسی ارسال پیام داشته باشد

4. **Environment Variables**: تمام متغیرهای محیطی باید به درستی تنظیم شوند

## عیب‌یابی

### مشکل: Seeder اجرا نمی‌شود
- مطمئن شوید که migrations اجرا شده‌اند
- چک کنید که Tenant و Personnel وجود دارند

### مشکل: Task یا Mission assign نمی‌شود
- چک کنید که پرسنل وجود دارد
- بررسی کنید که foreign key constraints درست هستند

### مشکل: آموزش‌ها ارسال نمی‌شود
- Queue Worker را اجرا کنید
- چک کنید که `personnel_id` در `bot_users.settings` ذخیره شده باشد

## دستورات مفید

```bash
# مشاهده لاگ‌ها
tail -f storage/logs/laravel.log

# اجرای Queue Worker
php artisan queue:work

# پاک کردن Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# مشاهده Routes
php artisan route:list | grep mission
php artisan route:list | grep task
```

## موفق باشید! 🚀

