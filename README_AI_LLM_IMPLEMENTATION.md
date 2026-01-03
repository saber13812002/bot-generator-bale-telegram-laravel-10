# راهنمای پیاده‌سازی سیستم انتخاب و ثبت هوش مصنوعی برای ماموریت‌ها

این راهنما مراحل اجرای تغییرات جدید برای سیستم انتخاب و ثبت هوش مصنوعی (AI/LLM) را توضیح می‌دهد.

## 📋 خلاصه تغییرات

این پیاده‌سازی شامل موارد زیر است:

1. **جدول جدید `ai_llms`**: برای ذخیره لیست هوش مصنوعی‌ها
2. **فیلد `ai_id` در جدول `missions`**: برای تعیین AI پیشنهادی برای هر ماموریت
3. **فیلد `selected_ai_id` در جدول `mission_personnel`**: برای ثبت AI انتخابی کاربر
4. **ارسال خالص Prompt و Content**: بدون توضیحات اضافی برای کپی کردن
5. **UI انتخاب AI**: امکان انتخاب AI از لیست برای کاربران
6. **مدیریت AI**: دستورات ربات ادمین برای اضافه و مشاهده AI‌ها

## 🚀 مراحل اجرا

### مرحله 1: پشتیبان‌گیری از دیتابیس

```bash
# پشتیبان‌گیری از دیتابیس (قبل از هر تغییر)
php artisan backup:run
# یا
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

### مرحله 2: Pull کردن تغییرات از Git

```bash
# اطمینان از اینکه در branch درست هستید
git status

# Pull کردن آخرین تغییرات
git pull origin main
# یا
git pull origin master
```

### مرحله 3: نصب وابستگی‌های جدید (در صورت وجود)

```bash
composer install --no-dev --optimize-autoloader
```

### مرحله 4: اجرای Migrations

```bash
# اجرای migrations جدید
php artisan migrate

# بررسی وضعیت migrations
php artisan migrate:status
```

**Migrations جدید:**
- `2025_12_26_202946_create_ai_llms_table.php`
- `2025_12_26_203017_add_ai_id_to_missions_table.php`
- `2025_12_26_203040_add_selected_ai_id_to_mission_personnel_table.php`

### مرحله 5: Seed کردن داده‌های اولیه AI

```bash
# اجرای Seeder برای اضافه کردن AI‌های پیش‌فرض
php artisan db:seed --class=AiLlmSeeder
```

**AI‌های پیش‌فرض که اضافه می‌شوند:**
- کلادی
- چت جی‌بی‌تی
- دیب سیک
- مونیکا
- جمنای
- کوبالیت
- نوت بک ال ام

### مرحله 6: پاک کردن Cache

```bash
# پاک کردن cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### مرحله 7: به‌روزرسانی Autoload

```bash
composer dump-autoload
```

### مرحله 8: بررسی صحت اجرا

```bash
# بررسی وضعیت migrations
php artisan migrate:status

# بررسی وجود AI‌ها در دیتابیس
php artisan tinker
# سپس در tinker:
\App\Models\AiLlm::count();
\App\Models\AiLlm::all();
exit
```

### مرحله 9: تست عملکرد

1. **تست ربات ماموریت:**
   - درخواست یک ماموریت
   - بررسی ارسال خالص Prompt و Content
   - تست انتخاب AI

2. **تست ربات ادمین:**
   - دستور `/list_ai` برای مشاهده لیست
   - دستور `/add_ai [نام]` برای اضافه کردن AI جدید

## 📝 دستورات ربات ادمین

پس از اجرای تغییرات، ربات ادمین دستورات زیر را پشتیبانی می‌کند:

- `/add_ai [نام]` - اضافه کردن هوش مصنوعی جدید
  - مثال: `/add_ai GPT-4`
  
- `/list_ai` - نمایش لیست هوش مصنوعی‌ها

## ⚠️ نکات مهم

1. **پشتیبان‌گیری:** حتماً قبل از اجرای migrations از دیتابیس پشتیبان بگیرید.

2. **زمان اجرا:** اجرای migrations ممکن است چند ثانیه طول بکشد. در صورت وجود داده‌های زیاد، ممکن است بیشتر طول بکشد.

3. **بررسی Webhook:** بعد از deploy، حتماً webhook ربات‌ها را بررسی کنید:
   ```bash
   # برای Telegram
   curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
   
   # برای Bale
   curl https://tapi.bale.ai/bot{TOKEN}/getWebhookInfo
   ```

4. **لاگ‌ها:** در صورت بروز مشکل، لاگ‌ها را بررسی کنید:
   ```bash
   tail -f storage/logs/laravel.log
   ```

5. **تست در محیط Development:** قبل از اجرا در Production، حتماً در محیط Development تست کنید.

## 🔄 Rollback (در صورت نیاز)

در صورت بروز مشکل، می‌توانید migrations را rollback کنید:

```bash
# Rollback آخرین migration
php artisan migrate:rollback

# Rollback چند migration
php artisan migrate:rollback --step=3

# Rollback همه migrations
php artisan migrate:reset
```

**⚠️ هشدار:** Rollback باعث حذف داده‌ها می‌شود. حتماً قبل از rollback از دیتابیس پشتیبان بگیرید.

## 📊 بررسی تغییرات در دیتابیس

برای بررسی تغییرات در دیتابیس:

```sql
-- بررسی جدول ai_llms
SELECT * FROM ai_llms;

-- بررسی فیلد ai_id در missions
SELECT id, title, ai_id FROM missions LIMIT 10;

-- بررسی فیلد selected_ai_id در mission_personnel
SELECT id, mission_id, personnel_id, selected_ai_id, status 
FROM mission_personnel 
LIMIT 10;
```

## 🐛 عیب‌یابی

### مشکل: Migration اجرا نمی‌شود

```bash
# بررسی وضعیت migrations
php artisan migrate:status

# اجرای force (فقط در صورت نیاز)
php artisan migrate --force
```

### مشکل: Seeder اجرا نمی‌شود

```bash
# بررسی وجود کلاس Seeder
php artisan db:seed --class=AiLlmSeeder --dry-run

# اجرای دستی در tinker
php artisan tinker
\App\Models\AiLlm::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
```

### مشکل: ربات کار نمی‌کند

1. بررسی webhook
2. بررسی لاگ‌ها
3. بررسی تنظیمات env
4. بررسی دسترسی به دیتابیس

## ✅ چک‌لیست نهایی

- [ ] پشتیبان‌گیری از دیتابیس انجام شد
- [ ] تغییرات از Git pull شدند
- [ ] Migrations اجرا شدند
- [ ] Seeder اجرا شد
- [ ] Cache پاک شد
- [ ] Autoload به‌روزرسانی شد
- [ ] ربات‌ها تست شدند
- [ ] Webhook بررسی شد
- [ ] لاگ‌ها بررسی شدند

## 📞 پشتیبانی

در صورت بروز مشکل، لاگ‌ها و پیام خطا را ذخیره کرده و با تیم توسعه تماس بگیرید.

---

**تاریخ پیاده‌سازی:** 2025-12-26  
**نسخه:** 1.0.0

