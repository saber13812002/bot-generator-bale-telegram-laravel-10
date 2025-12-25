# 🧪 سناریوی تست کامل ربات ادمین ثبت‌نام پرسنل

این فایل شامل دستورات و مراحل تست کامل فیچر ربات ادمین است.

## 📋 مراحل تست

### 1️⃣ آماده‌سازی دیتابیس

#### 1.1 اطمینان از وجود Tenant
```bash
php artisan db:seed --class=TenantSeeder
```

#### 1.2 Seed کردن اطلاعات پرسنل (با داده‌های نمونه)
```bash
php artisan db:seed --class=PersonnelSeeder
```

**نکته:** اگر می‌خواهید اطلاعات واقعی خود را seed کنید، ابتدا فایل `database/seeders/PersonnelSeeder.php` را ویرایش کرده و اطلاعات خود را اضافه کنید.

### 2️⃣ تنظیمات Environment Variables

اطمینان حاصل کنید که در فایل `.env` مقادیر زیر تنظیم شده‌اند:

```env
# ربات ادمین ثبت‌نام پرسنل - Telegram
PERSONNEL_ADMIN_BOT_TOKEN_TELEGRAM=your_telegram_bot_token
PERSONNEL_ADMIN_BOT_TENANT_ID_TELEGRAM=1

# ربات ادمین ثبت‌نام پرسنل - Bale
PERSONNEL_ADMIN_BOT_TOKEN_BALE=your_bale_bot_token
PERSONNEL_ADMIN_BOT_TENANT_ID_BALE=1

# Chat ID ادمین‌ها (برای دسترسی به ربات ادمین)
CHAT_ID_ACCOUNT_1_SABER=your_chat_id
CHAT_ID_ACCOUNT_2_SABER=your_chat_id
SUPER_ADMIN_CHAT_ID_TELEGRAM=your_chat_id
SUPER_ADMIN_CHAT_ID_BALE=your_chat_id

# دامنه پروژه
APP_URL=https://your-domain.com
```

### 3️⃣ تنظیم Webhook

#### 3.1 برای Telegram
```bash
curl -X POST "https://api.telegram.org/bot{YOUR_TELEGRAM_BOT_TOKEN}/setWebhook" -H "Content-Type: application/json" -d '{"url":"https://your-domain.com/api/webhook-personnel-admin?origin=telegram&bot_mother_id=1&token={YOUR_TELEGRAM_BOT_TOKEN}"}'
```

#### 3.2 برای Bale
```bash
curl -X POST "https://tapi.bale.ai/bot{YOUR_BALE_BOT_TOKEN}/setWebhook" -H "Content-Type: application/json" -d '{"url":"https://your-domain.com/api/webhook-personnel-admin?origin=bale&bot_mother_id=1&token={YOUR_BALE_BOT_TOKEN}"}'
```

#### 3.3 بررسی Webhook (برای اطمینان)
```bash
# Telegram
curl "https://api.telegram.org/bot{YOUR_TELEGRAM_BOT_TOKEN}/getWebhookInfo"

# Bale
curl "https://tapi.bale.ai/bot{YOUR_BALE_BOT_TOKEN}/getWebhookInfo"
```

### 4️⃣ تست دستورات ربات

در ربات ادمین (Telegram یا Bale) دستورات زیر را امتحان کنید:

#### 4.1 دستور `/start`
- باید پیام خوش‌آمدگویی و لیست دستورات را نشان دهد
- باید نام تننت را نمایش دهد

#### 4.2 دستور `/today` یا `امروز`
- باید لیست ثبت‌نام‌های امروز را نشان دهد
- اگر امروز ثبت‌نامی نداشته باشید، باید پیام "هیچ ثبت‌نامی برای امروز وجود ندارد" را ببینید

#### 4.3 دستور `/all` یا `همه` یا `کل`
- باید لیست کامل تمام ثبت‌نام‌ها را نشان دهد
- باید اطلاعات پرسنل‌هایی که با سیدر ایجاد شده‌اند را ببینید

#### 4.4 دستور `/help` یا `راهنما`
- باید راهنمای کامل دستورات را نمایش دهد

### 5️⃣ تست ثبت‌نام جدید (برای مشاهده در لیست امروز)

#### 5.1 ثبت‌نام یک پرسنل جدید
از ربات ثبت‌نام (`/webhook-personnel-registration`) یک ثبت‌نام جدید انجام دهید.

#### 5.2 بررسی در ربات ادمین
بعد از ثبت‌نام، در ربات ادمین دستور `/today` را بزنید:
- باید ثبت‌نام جدید در لیست امروز ظاهر شود
- دستور `/all` هم باید ثبت‌نام جدید را در ابتدای لیست نشان دهد

### 6️⃣ بررسی لاگ‌ها

برای مشاهده لاگ‌های سیستم:

```bash
# لاگ‌های Laravel
tail -f storage/logs/laravel.log

# یا اگر از log rotate استفاده می‌کنید
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

**لاگ‌های مهم برای بررسی:**
- `🔔 Personnel Admin Bot - Webhook received` - دریافت webhook
- `📨 Personnel Admin Bot - Message received` - دریافت پیام
- `📊 Personnel Admin Bot - Today command processed` - پردازش دستور امروز
- `📋 Personnel Admin Bot - All command processed` - پردازش دستور همه
- `✅ Personnel Admin Bot - Message processed successfully` - موفقیت آمیز بودن
- `❌ Personnel Admin Bot - Error occurred` - خطاها

### 7️⃣ تست دسترسی (Security Test)

#### 7.1 تست با کاربر غیرادمین
با یک حساب غیرادمین در ربات پیام بزنید:
- باید پیام "❌ شما دسترسی به این ربات ندارید" را دریافت کنید

#### 7.2 تست با کاربر ادمین
با یک حساب ادمین (chat_id که در `AdminHelper` تعریف شده) در ربات پیام بزنید:
- باید بتوانید دستورات را اجرا کنید

## ✅ Checklist تست

- [ ] Tenant در دیتابیس وجود دارد
- [ ] اطلاعات پرسنل با سیدر seed شده‌اند
- [ ] Environment variables تنظیم شده‌اند
- [ ] Webhook برای Telegram تنظیم شده است
- [ ] Webhook برای Bale تنظیم شده است
- [ ] Webhook به درستی کار می‌کند (با getWebhookInfo چک شده)
- [ ] دستور `/start` کار می‌کند
- [ ] دستور `/today` کار می‌کند
- [ ] دستور `/all` کار می‌کند
- [ ] دستور `/help` کار می‌کند
- [ ] ثبت‌نام جدید در لیست امروز ظاهر می‌شود
- [ ] ثبت‌نام جدید در لیست همه ظاهر می‌شود
- [ ] کاربر غیرادمین نمی‌تواند از ربات استفاده کند
- [ ] لاگ‌ها به درستی ثبت می‌شوند

## 🐛 مشکلات احتمالی و راه‌حل

### مشکل: Webhook تنظیم نمی‌شود
- بررسی کنید که دامنه درست است
- بررسی کنید که SSL certificate معتبر است
- بررسی کنید که سرور از خارج قابل دسترسی است

### مشکل: پیام "خطا: تنظیمات ربات ناقص است"
- بررسی کنید که `PERSONNEL_ADMIN_BOT_TENANT_ID_TELEGRAM` یا `PERSONNEL_ADMIN_BOT_TENANT_ID_BALE` در `.env` تنظیم شده است
- بررسی کنید که Tenant با این ID در دیتابیس وجود دارد

### مشکل: پیام "❌ شما دسترسی به این ربات ندارید"
- بررسی کنید که chat_id شما در `AdminHelper::isAdmin()` تعریف شده است
- بررسی کنید که environment variables مربوط به chat_id ها تنظیم شده‌اند

### مشکل: لیست خالی است
- بررسی کنید که Tenant ID درست است
- بررسی کنید که پرسنل‌ها با `tenant_id` صحیح ایجاد شده‌اند
- با دستور SQL بررسی کنید: `SELECT * FROM personnel WHERE tenant_id = 1;`

## 📊 دستورات SQL برای بررسی

```sql
-- بررسی Tenant
SELECT * FROM tenants;

-- بررسی پرسنل‌های یک Tenant
SELECT * FROM personnel WHERE tenant_id = 1;

-- تعداد پرسنل‌های امروز
SELECT COUNT(*) FROM personnel 
WHERE tenant_id = 1 
AND DATE(created_at) = CURDATE();

-- لیست پرسنل‌های امروز
SELECT * FROM personnel 
WHERE tenant_id = 1 
AND DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

## 📝 گزارش تست

بعد از اجرای تست، لطفاً موارد زیر را گزارش دهید:

1. ✅ مواردی که موفق بودند
2. ❌ مواردی که خطا داشتند
3. 📋 لاگ‌های خطا (از `storage/logs/laravel.log`)
4. 🔍 پیام‌های خطا در ربات
5. 💡 پیشنهادات برای بهبود

