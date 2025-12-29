# راهنمای کامل تست سیستم ماموریت‌ها

این راهنما مراحل کامل تست سیستم ماموریت‌ها را به ترتیب توضیح می‌دهد.

## مرحله 1: اجرای Migrations و Seeders

```bash
# 1. اجرای migrations
php artisan migrate

# 2. اجرای seeders پایه
php artisan db:seed --class=TenantSeeder
php artisan db:seed --class=PersonnelSeeder
php artisan db:seed --class=PromptSeeder
php artisan db:seed --class=ContentSeeder

# 3. اجرای seeder تست ماموریت
php artisan db:seed --class=MissionTestSeeder
```

بعد از اجرای `MissionTestSeeder`، شما یک ماموریت کامل با:
- یک Content
- یک Prompt
- یک Mission که به یک یا دو پرسنل assign شده است

## مرحله 2: تنظیمات Environment Variables

مطمئن شوید که در فایل `.env` این متغیرها تنظیم شده‌اند:

```env
# ربات ماموریت
MISSION_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
MISSION_BOT_TOKEN_BALE=your_bale_bot_token

# ربات مدیا ماموریت
MISSION_MEDIA_BOT_TOKEN_TELEGRAM=your_media_bot_token
MISSION_MEDIA_BOT_TOKEN_BALE=your_media_bot_token

# گروه تایید
MISSION_APPROVAL_GROUP_CHAT_ID=your_group_chat_id
```

## مرحله 3: آپلود آموزش‌ها با ربات مدیا

1. به ربات مدیا ماموریت بروید (Telegram یا Bale)
2. دستور زیر را ارسال کنید (به جای `{mission_id}` شناسه ماموریت را بگذارید):
   ```
   /upload_mission_{mission_id}
   ```
   مثال: `/upload_mission_1`

3. سپس فایل‌های آموزشی را ارسال کنید:
   - **عکس**: یک تصویر ارسال کنید
   - **ویدیو**: یک ویدیو ارسال کنید
   - **صوت**: یک فایل صوتی ارسال کنید
   - **PDF**: یک فایل PDF ارسال کنید
   - **متن**: یک پیام متنی ارسال کنید

4. سیستم به صورت خودکار:
   - فایل را دریافت می‌کند
   - URL فایل را از Telegram/Bale API می‌گیرد
   - یک Content جدید ایجاد می‌کند
   - آن را به Mission متصل می‌کند

**نکته**: می‌توانید چندین فایل را یکی پس از دیگری ارسال کنید. هر فایل به ترتیب به Mission اضافه می‌شود.

## مرحله 4: ارسال آموزش‌ها به پرسنل

### روش 1: از طریق ربات مدیا (برای ادمین)

در ربات مدیا دستور زیر را ارسال کنید:
```
/get_training_{mission_id}
```
مثال: `/get_training_1`

این دستور لیست آموزش‌های موجود را نشان می‌دهد.

### روش 2: از طریق ربات ماموریت (برای پرسنل)

1. پرسنل باید در ربات ماموریت ثبت‌نام کرده باشد
2. پرسنل دستور `/get_training` را ارسال می‌کند
3. سیستم به صورت خودکار:
   - ماموریت فعال پرسنل را پیدا می‌کند
   - Prompt را ارسال می‌کند
   - تمام محتواهای آموزشی را به ترتیب ارسال می‌کند

**نکته**: ارسال محتواها به صورت ترتیبی و از طریق Queue انجام می‌شود.

## مرحله 5: انجام ماموریت و ارسال لینک نتیجه

1. پرسنل آموزش‌ها را مشاهده می‌کند
2. ماموریت را انجام می‌دهد
3. در ربات ماموریت، **لینک نتیجه** را ارسال می‌کند

مثال لینک:
```
https://example.com/result/12345
```

4. سیستم به صورت خودکار:
   - لینک را در جدول `mission_personnel` ذخیره می‌کند
   - وضعیت را به `pending_approval` تغییر می‌دهد
   - پیام تایید را در گروه تایید ارسال می‌کند

## مرحله 6: تایید یا رد در گروه

1. در گروه تایید، پیامی با این فرمت ارسال می‌شود:
   ```
   📋 ماموریت جدید برای تایید:
   
   شناسه ماموریت: 1
   عنوان: ماموریت تست
   کاربر: علی احمدی
   کد ملی: 1234567890
   امتیاز: 50
   لینک: https://example.com/result/12345
   
   برای تایید، کلمه 'تایید' را به این پیام reply کنید.
   برای رد، پیام خود را به این پیام reply کنید.
   ```

2. برای **تایید**:
   - به پیام reply کنید
   - کلمه "تایید" را ارسال کنید
   - سیستم:
     - وضعیت را به `approved` تغییر می‌دهد
     - `approved_by_chat_id` را ثبت می‌کند
     - `approved_at` را ثبت می‌کند
     - امتیاز به پرسنل اضافه می‌شود

3. برای **رد**:
   - به پیام reply کنید
   - دلیل رد را ارسال کنید
   - سیستم:
     - وضعیت را به `rejected` تغییر می‌دهد
     - `rejection_reason` را ثبت می‌کند
     - `rejected_at` را ثبت می‌کند

## مرحله 7: بررسی نتایج

### بررسی در دیتابیس

```sql
-- مشاهده ماموریت
SELECT * FROM missions WHERE id = 1;

-- مشاهده پرسنل‌های assign شده
SELECT mp.*, p.first_name, p.last_name 
FROM mission_personnel mp
JOIN personnel p ON mp.personnel_id = p.id
WHERE mp.mission_id = 1;

-- مشاهده محتواهای آموزشی
SELECT c.*, mc.sort_order
FROM contents c
JOIN mission_contents mc ON c.id = mc.content_id
WHERE mc.mission_id = 1
ORDER BY mc.sort_order;

-- مشاهده پرامپت
SELECT * FROM prompts WHERE mission_id = 1;
```

### بررسی از طریق API

```bash
# لیست ماموریت‌های موجود
curl http://your-domain/api/missions

# جزئیات یک ماموریت
curl http://your-domain/api/missions/1
```

## عیب‌یابی

### مشکل: ربات مدیا فایل را دریافت نمی‌کند
- مطمئن شوید که ربات مدیا به درستی تنظیم شده است
- چک کنید که webhook درست ست شده باشد
- لاگ‌ها را بررسی کنید: `storage/logs/laravel.log`

### مشکل: آموزش‌ها به پرسنل ارسال نمی‌شود
- مطمئن شوید که پرسنل در ربات ثبت‌نام کرده است
- چک کنید که `personnel_id` در settings ذخیره شده باشد
- Queue را بررسی کنید: `php artisan queue:work`

### مشکل: لینک در گروه تایید ارسال نمی‌شود
- مطمئن شوید که `MISSION_APPROVAL_GROUP_CHAT_ID` در `.env` تنظیم شده است
- چک کنید که ربات در گروه عضو باشد
- بررسی کنید که ربات دسترسی ارسال پیام در گروه را داشته باشد

### مشکل: تایید/رد کار نمی‌کند
- مطمئن شوید که `TaskApprovalController` به درستی کار می‌کند
- چک کنید که reply به پیام درست انجام می‌شود
- لاگ‌ها را بررسی کنید

## دستورات مفید

```bash
# مشاهده لاگ‌ها
tail -f storage/logs/laravel.log

# اجرای Queue Worker
php artisan queue:work

# پاک کردن Cache
php artisan cache:clear
php artisan config:clear

# مشاهده Routes
php artisan route:list | grep mission
```

## نکات مهم

1. **Queue**: برای ارسال ترتیبی محتواها، باید Queue Worker در حال اجرا باشد
2. **Webhook**: مطمئن شوید که webhook‌های ربات‌ها به درستی تنظیم شده‌اند
3. **Permissions**: ربات باید دسترسی لازم در گروه تایید را داشته باشد
4. **Environment**: تمام متغیرهای محیطی باید به درستی تنظیم شوند

## پشتیبانی

در صورت بروز مشکل، لاگ‌ها را بررسی کنید و اطلاعات زیر را جمع‌آوری کنید:
- خطای دقیق از لاگ
- شناسه ماموریت
- شناسه پرسنل
- شناسه چت گروه تایید


