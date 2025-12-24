# مستندات ربات ثبت‌نام پرسنل

## نصب و راه‌اندازی

### ۱. اجرای مایگریشن‌ها

```bash
php artisan migrate
```

### ۲. اجرای سیدر برای ایجاد تننت صابر

```bash
php artisan db:seed --class=TenantSeeder
```

یا می‌توانید این سیدر را به `DatabaseSeeder` اضافه کنید.

### ۳. تنظیمات محیط (.env)

برای استفاده از ربات ثبت‌نام، باید توکن‌های ربات‌های تلگرام و بله را در فایل `.env` اضافه کنید:

```env
PERSONNEL_REGISTRATION_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
PERSONNEL_REGISTRATION_BOT_TOKEN_BALE=your_bale_bot_token

# نام کاربری ربات‌ها (برای ارسال لینک‌ها)
PERSONNEL_BALE_BOT_USERNAME=your_bale_bot_username
PERSONNEL_TELEGRAM_BOT_USERNAME=your_telegram_bot_username
```

### ۴. تنظیم وب‌هوک

برای تنظیم وب‌هوک ربات‌ها، باید از API تلگرام و بله استفاده کنید:

**برای تلگرام:**
```
POST https://api.telegram.org/bot{TOKEN}/setWebhook
Body: {"url": "https://your-domain.com/api/webhook-personnel-registration?origin=telegram&bot_mother_id=1&token={TOKEN}&language=fa"}
```

**برای بله:**
```
POST https://tapi.bale.ai/bot{TOKEN}/setWebhook
Body: {"url": "https://your-domain.com/api/webhook-personnel-registration?origin=bale&bot_mother_id=1&token={TOKEN}&language=fa"}
```

## ساختار دیتابیس

### جدول `tenants`
- `id`: شناسه منحصر به فرد
- `tenant_name`: نام تننت
- `created_at`: تاریخ ایجاد
- `updated_at`: تاریخ به‌روزرسانی

### جدول `personnel`
- `id`: شناسه منحصر به فرد
- `first_name`: نام
- `last_name`: نام خانوادگی
- `national_code`: کد ملی (10 رقم، یکتا)
- `phone_number`: شماره موبایل
- `tenant_id`: شناسه تننت (ارجاع به جدول tenants)
- `rank`: درجه کاربر (پیش‌فرض: "سرباز صفر")
- `created_at`: تاریخ ثبت
- `updated_at`: تاریخ به‌روزرسانی

### جدول `personnel_message_queues`
این جدول برای ارسال مطالب به کاربران استفاده می‌شود (برای استفاده در مراحل بعدی):
- `id`: شناسه منحصر به فرد
- `personnel_id`: شناسه پرسنل (ارجاع به جدول personnel)
- `message_content`: محتوای پیام
- `status`: وضعیت (`queue`, `sent`, `error`)
- `error_message`: پیام خطا (در صورت بروز خطا)
- `created_at`: تاریخ ایجاد
- `updated_at`: تاریخ به‌روزرسانی

## فرآیند ثبت‌نام

۱. کاربر دستور `/start` را ارسال می‌کند
۲. ربات پیام خوش‌آمدگویی را ارسال می‌کند
۳. کاربر اطلاعات زیر را به ترتیب وارد می‌کند:
   - نام
   - نام خانوادگی
   - کد ملی (10 رقم)
   - شماره موبایل (فرمت استاندارد: 09123456789)
۴. اطلاعات برای تایید نمایش داده می‌شود
۵. کاربر "تایید" یا "لغو" را ارسال می‌کند
۶. در صورت تایید، اطلاعات در دیتابیس ذخیره می‌شود
۷. کاربر به درجه "سرباز صفر" منصوب می‌شود
۸. لینک‌های اختصاصی ربات‌های بله و تلگرام برای کاربر ارسال می‌شود

## Route

```
POST /api/webhook-personnel-registration
```

**پارامترهای مورد نیاز:**
- `origin`: نوع ربات (`telegram` یا `bale`)
- `bot_mother_id`: شناسه ربات مادر
- `token`: توکن ربات (اختیاری)
- `language`: زبان (پیش‌فرض: `fa`)

## نکات مهم

- تمامی پرسنل به صورت پیش‌فرض به تننت "صابر" اختصاص داده می‌شوند
- کد ملی باید یکتا باشد (چک می‌شود)
- شماره موبایل باید به فرمت استاندارد باشد (09xxxxxxxxx)
- درجه پیش‌فرض برای همه کاربران جدید "سرباز صفر" است
- لینک‌های ربات‌ها شامل پارامتر `personnel_id` هستند برای شناسایی کاربر

## تست

برای تست ربات:

۱. ربات را در تلگرام یا بله اجرا کنید
۲. دستور `/start` را ارسال کنید
۳. مراحل ثبت‌نام را دنبال کنید
۴. اطلاعات را تایید کنید
۵. بررسی کنید که اطلاعات در جدول `personnel` ذخیره شده‌اند

