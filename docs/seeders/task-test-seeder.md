# Seeder تست کامل تسک (TaskTestSeeder)

## 📝 توضیحات

این Seeder یک سناریوی کامل تست را ایجاد می‌کند که می‌توانید با آن فرآیند کامل تسک را از صفر تا صد تست کنید.

## 🎯 چه چیزهایی ایجاد می‌کند؟

1. **Tenant**: یک Tenant با نام "تست"
2. **Personnel**: یک پرسنل با:
   - نام: علی احمدی
   - کد ملی: 1234567890
   - شماره موبایل: 09123456789
   - درجه: سرباز صفر
3. **BotUsers**: 
   - یک BotUser برای تلگرام (chat_id: 123456789)
   - یک BotUser برای بله (chat_id: 123456789)
   - هر دو با `personnel_id` در settings
4. **Tasks**:
   - یک Task با وضعیت `pending_approval` (برای تست تایید)
   - یک Task با وضعیت `reserved` (برای تست رزرو)
   - یک Task با وضعیت `approved` (برای تست تسک تایید شده)

## 🚀 نحوه استفاده

### اجرای Seeder

```bash
php artisan db:seed --class=TaskTestSeeder
```

یا اگر می‌خواهید همه Seeder ها را اجرا کنید:

```bash
php artisan db:seed
```

(باید در `DatabaseSeeder.php` اضافه شود)

### بررسی نتایج

بعد از اجرای Seeder، می‌توانید با دستور زیر بررسی کنید:

```bash
php artisan debug:task-approval --detail
```

## 📊 داده‌های ایجاد شده

### Personnel
- ID: (خودکار)
- نام: علی
- نام خانوادگی: احمدی
- کد ملی: 1234567890
- شماره موبایل: 09123456789
- درجه: سرباز صفر

### BotUsers
- Chat ID: 123456789
- Origin: telegram و bale
- Settings: `{"personnel_id": <personnel_id>, "registration_step": "completed"}`

### Tasks
1. **Task pending_approval**:
   - وضعیت: `pending_approval`
   - Approval Message ID: 12345
   - Points: 10
   - Final Link: https://example.com/test-task-link

2. **Task reserved**:
   - وضعیت: `reserved`
   - Points: 15

3. **Task approved**:
   - وضعیت: `approved`
   - Points: 20
   - Approved At: 30 دقیقه پیش

## 🧪 تست فرآیند کامل

### 1. تست ربات ثبت‌نام
- به ربات ثبت‌نام `/start` بزنید
- باید پیام "👋 سلام! ربات ثبت‌نام آماده است." را ببینید

### 2. تست ربات ماموریت
- از لینک ایجاد شده توسط ربات ثبت‌نام استفاده کنید
- یا مستقیماً `/start?personnel_id=<personnel_id>` بزنید
- باید پیام "👋 سلام! ربات ماموریت آماده است." را ببینید

### 3. تست ربات تایید
- در گروه تایید، به پیام با ID 12345 reply کنید با "تایید"
- باید تسک تایید شود

## ⚠️ نکات مهم

1. **Chat ID**: Chat ID های استفاده شده (123456789) فقط برای تست هستند. در production باید از Chat ID های واقعی استفاده کنید.

2. **Approval Message ID**: Message ID 12345 یک عدد تستی است. در واقعیت باید از Message ID واقعی پیام در گروه استفاده کنید.

3. **Tenant**: اگر Tenant "تست" از قبل وجود داشته باشد، از همان استفاده می‌شود.

4. **Personnel**: اگر Personnel با کد ملی 1234567890 از قبل وجود داشته باشد، از همان استفاده می‌شود.

## 🔄 پاک کردن داده‌های تست

اگر می‌خواهید داده‌های تست را پاک کنید:

```sql
-- حذف Tasks
DELETE FROM tasks WHERE task_name LIKE 'تسک تستی%' OR task_name LIKE 'تسک رزرو شده' OR task_name LIKE 'تسک تایید شده';

-- حذف BotUsers
DELETE FROM bot_users WHERE chat_id = '123456789';

-- حذف Personnel
DELETE FROM personnels WHERE national_code = '1234567890';

-- حذف Tenant (اگر فقط برای تست است)
DELETE FROM tenants WHERE tenant_name = 'تست';
```

## 📝 افزودن به DatabaseSeeder

برای اینکه این Seeder به صورت خودکار اجرا شود، در `database/seeders/DatabaseSeeder.php` اضافه کنید:

```php
public function run(): void
{
    // ... سایر Seeder ها
    
    $this->call(TaskTestSeeder::class);
}
```

