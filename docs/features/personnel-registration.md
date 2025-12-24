# فیچر ثبت‌نام پرسنل (Personnel Registration)

## 📝 توضیحات

این فیچر امکان ثبت‌نام پرسنل جدید را از طریق ربات‌های پیام‌رسان (بله و تلگرام) فراهم می‌کند. کاربران می‌توانند با ارسال دستور `/start` در ربات، فرآیند ثبت‌نام را شروع کرده و اطلاعات شخصی خود (نام، نام خانوادگی، کد ملی، شماره موبایل) را وارد کنند.

## 🎯 اهداف

- ثبت‌نام پرسنل جدید از طریق ربات
- اعتبارسنجی اطلاعات وارد شده
- ذخیره اطلاعات در دیتابیس
- ارسال لینک ربات‌های اختصاصی به پرسنل ثبت‌نام شده

## 📂 مسیر فایل‌ها

### Controllers
- `app/Http/Controllers/PersonnelRegistrationController.php`

### Models
- `app/Models/Personnel.php`
- `app/Models/BotUsers.php`
- `app/Models/Tenant.php`

### Helpers
- `app/Helpers/BotHelper.php`
- `app/Helpers/LogHelper.php`

### Routes
- `routes/api.php` - Route: `/api/webhook-personnel-registration`

### Migrations
- `database/migrations/xxxx_create_personnels_table.php`
- `database/migrations/xxxx_create_tenants_table.php`

### Seeders
- `database/seeders/TenantSeeder.php`

### Configuration
- `config/bot.php` - تنظیمات base URL ربات‌ها

### Environment Variables
```env
PERSONNEL_REGISTRATION_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
PERSONNEL_REGISTRATION_BOT_TOKEN_BALE=your_bale_bot_token
PERSONNEL_BALE_BOT_USERNAME=your_bale_bot_username
PERSONNEL_TELEGRAM_BOT_USERNAME=your_telegram_bot_username
```

## 🔄 Flow ثبت‌نام

1. کاربر دستور `/start` را ارسال می‌کند
2. ربات درخواست نام می‌کند
3. کاربر نام را وارد می‌کند
4. ربات درخواست نام خانوادگی می‌کند
5. کاربر نام خانوادگی را وارد می‌کند
6. ربات درخواست کد ملی می‌کند (10 رقم)
7. کاربر کد ملی را وارد می‌کند
8. ربات درخواست شماره موبایل می‌کند
9. کاربر شماره موبایل را وارد می‌کند
10. ربات اطلاعات را نمایش می‌دهد و درخواست تایید می‌کند
11. کاربر "تایید" یا "لغو" را ارسال می‌کند
12. در صورت تایید، اطلاعات ذخیره می‌شود و لینک ربات‌های اختصاصی ارسال می‌شود

## 🔧 نحوه استفاده

### 1. نصب و راه‌اندازی

#### اجرای Migration ها
```bash
php artisan migrate
```

#### اجرای Seeder برای ایجاد Tenant صابر
```bash
php artisan db:seed --class=TenantSeeder
```

#### تنظیم Environment Variables
فایل `.env` را ویرایش کرده و توکن‌های ربات‌ها را اضافه کنید:
```env
PERSONNEL_REGISTRATION_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
PERSONNEL_REGISTRATION_BOT_TOKEN_BALE=your_bale_bot_token
PERSONNEL_BALE_BOT_USERNAME=your_bale_bot_username
PERSONNEL_TELEGRAM_BOT_USERNAME=your_telegram_bot_username
```

### 2. تنظیم Webhook

#### برای تلگرام:
```bash
POST https://api.telegram.org/bot{TOKEN}/setWebhook
Body: {
  "url": "https://your-domain.com/api/webhook-personnel-registration?origin=telegram&bot_mother_id=1&token={TOKEN}&language=fa"
}
```

#### برای بله:
```bash
POST https://tapi.bale.ai/bot{TOKEN}/setWebhook
Body: {
  "url": "https://your-domain.com/api/webhook-personnel-registration?origin=bale&bot_mother_id=1&token={TOKEN}&language=fa"
}
```

### 3. استفاده از ربات

کاربر می‌تواند در ربات دستور `/start` را ارسال کرده و مراحل ثبت‌نام را طی کند.

## 📊 ساختار دیتابیس

### جدول `personnel`
- `id`: شناسه منحصر به فرد
- `first_name`: نام
- `last_name`: نام خانوادگی
- `national_code`: کد ملی (10 رقم، یکتا)
- `phone_number`: شماره موبایل
- `tenant_id`: شناسه تننت (foreign key)
- `rank`: درجه پرسنل
- `created_at`: تاریخ ایجاد
- `updated_at`: تاریخ به‌روزرسانی

### جدول `tenants`
- `id`: شناسه منحصر به فرد
- `tenant_name`: نام تننت
- `created_at`: تاریخ ایجاد
- `updated_at`: تاریخ به‌روزرسانی

### جدول `bot_users`
- این جدول برای ذخیره وضعیت ثبت‌نام کاربر استفاده می‌شود
- تنظیمات ثبت‌نام در فیلد `settings` به صورت JSON ذخیره می‌شود

## 🔐 Validation Rules

- **نام**: نمی‌تواند خالی باشد
- **نام خانوادگی**: نمی‌تواند خالی باشد
- **کد ملی**: باید دقیقاً 10 رقم باشد و یکتا باشد
- **شماره موبایل**: باید به فرمت استاندارد (09xxxxxxxxx) باشد

## 📝 Logging

این فیچر از سیستم لاگینگ زیر استفاده می‌کند:

1. **BotLog**: برای لاگ کردن تمام پیام‌های دریافتی از ربات (از طریق `LogHelper::log()`)
2. **Application Log**: برای لاگ کردن خطاها و رویدادهای مهم (از طریق `Log::info()` و `Log::error()`)

### نمونه Log ها:
```php
// لاگ ثبت‌نام موفق
Log::info("Personnel registered: " . $personnel->id . " - " . $personnel->national_code);

// لاگ خطا
Log::error('Error saving personnel: ' . $e->getMessage());
```

## 🧪 Testing

### Unit Tests
- تست validation برای کد ملی
- تست validation برای شماره موبایل
- تست یکتایی کد ملی

### Feature Tests
- تست flow کامل ثبت‌نام
- تست handling دستورات مختلف
- تست error handling

## 🐛 Known Issues

هیچ مورد شناخته‌شده‌ای در حال حاضر وجود ندارد.

## 🔮 Future Improvements

- [ ] اضافه کردن قابلیت ویرایش اطلاعات ثبت‌نام شده
- [ ] اضافه کردن قابلیت حذف ثبت‌نام
- [ ] بهبود UI/UX پیام‌های ربات
- [ ] اضافه کردن قابلیت ارسال کد تأیید به شماره موبایل
- [ ] اضافه کردن قابلیت upload عکس برای پرسنل

## 📚 مستندات مرتبط

- [README.md اصلی](../../README.md)
- [PROJECT_ROLES.md](../../PROJECT_ROLES.md)
- [CHECKLIST.md](../../CHECKLIST.md)

## 👥 Contributors

- تیم توسعه

---

**آخرین بروزرسانی**: تاریخ آخرین تغییر

