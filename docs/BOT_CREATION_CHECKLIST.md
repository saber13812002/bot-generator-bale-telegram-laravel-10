# ✅ چک‌لیست ساخت ربات جدید

> استفاده سریع: این چک‌لیست را قبل، حین و بعد از ساخت ربات جدید چک کنید.

## قبل از شروع

- [ ] فایل `docs/BOT_CREATION_GUIDE.md` را مطالعه کردم
- [ ] فایل `PROJECT_RULES.md` را مطالعه کردم
- [ ] `.cursorrules` را مطالعه کردم
- [ ] Controller های موجود را بررسی کردم (QuranWordController, PresenterBotController, etc.)
- [ ] Route های موجود را در `routes/api.php` چک کردم
- [ ] جدول `webhook_endpoints` را مشاهده کردم

---

## مرحله 1: طراحی

- [ ] نام ربات مشخص شده: `____________`
- [ ] `endpoint_id` مشخص شده: `____________`
- [ ] Route مشخص شده: `api/webhook-____________`
- [ ] ساختار دیتابیس طراحی شده
- [ ] Business Logic مشخص شده

---

## مرحله 2: ایجاد فایل‌ها

### دیتابیس
- [ ] Migration ایجاد شده
- [ ] Model ایجاد شده
- [ ] Relations تعریف شده

### Repository
- [ ] Repository Interface ایجاد شده (`app/Interfaces/Repositories/`)
- [ ] Repository Implementation ایجاد شده (`app/Repositories/`)
- [ ] در AppServiceProvider ثبت شده

### Service
- [ ] Service Interface ایجاد شده (`app/Interfaces/Services/`)
- [ ] Service Implementation ایجاد شده (`app/Services/`)
- [ ] در AppServiceProvider ثبت شده

### Controller
- [ ] Controller ایجاد شده (`app/Http/Controllers/`)
- [ ] متد `webhook()` تعریف شده
- [ ] متد `createBotInstance()` با الگوی صحیح تعریف شده
- [ ] از `LogHelper` با `Request` معمولی استفاده نشده ✅
- [ ] توکن از دیتابیس گرفته می‌شود ✅

### Route
- [ ] Route در `routes/api.php` ثبت شده
- [ ] Route با `/api/` شروع می‌شود ✅

### Seeder
- [ ] Webhook Endpoint Seeder ایجاد شده
- [ ] `route` با `api/` شروع می‌شود ✅
- [ ] `requires_token` = `true` است ✅
- [ ] `endpoint_id` منحصر به فرد است

---

## مرحله 3: ترجمه

- [ ] کلیدهای ترجمه به `lang/fa/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/en/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/ar-IQ/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/az/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/bs/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/de-DE/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/es/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/fr/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/he/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/pt-BR/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/pt-PT/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/ru/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/tr/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/ur/bot.php` اضافه شده
- [ ] کلیدهای ترجمه به `lang/zh-CN/bot.php` اضافه شده

---

## مرحله 4: مستندات

- [ ] فایل `docs/features/MY_BOT.md` ایجاد شده
- [ ] شامل: توضیحات، نحوه استفاده، فایل‌های مرتبط، API Endpoints
- [ ] لینک در `README.md` اضافه شده (بخش Features)

---

## مرحله 5: تست محلی (Local)

- [ ] Migration اجرا شده: `php artisan migrate`
- [ ] Seeder اجرا شده: `php artisan db:seed --class=MyBotWebhookEndpointSeeder`
- [ ] Endpoint در جدول `webhook_endpoints` ثبت شده
- [ ] Route لیست شده: `php artisan route:list | grep my-bot`
- [ ] Cache پاک شده: `php artisan cache:clear && php artisan route:clear`

---

## مرحله 6: Deploy به Production

- [ ] فایل‌ها به سرور آپلود شده
- [ ] Migration اجرا شده: `php artisan migrate`
- [ ] Seeder اجرا شده: `php artisan db:seed --class=MyBotWebhookEndpointSeeder`
- [ ] Cache پاک شده: `php artisan cache:clear && php artisan route:clear && php artisan config:clear`
- [ ] Route چک شده: `php artisan route:list | grep my-bot`

---

## مرحله 7: تست ربات

### ساخت ربات
- [ ] ربات در Bot Mother ساخته شده
- [ ] Webhook URL درست است (شامل `/api/` و `token` و `bot_id`)
- [ ] Webhook با موفقیت ست شده (چک با `getWebhookInfo`)
- [ ] `pending_update_count` = 0 است

### تست تلگرام
- [ ] به ربات تلگرام `/start` زده شده
- [ ] ربات پاسخ می‌دهد
- [ ] لاگ بررسی شده: `tail -f storage/logs/laravel.log | grep -i mybot`
- [ ] خطایی وجود ندارد

### تست بله
- [ ] به ربات بله `/start` زده شده
- [ ] ربات پاسخ می‌دهد
- [ ] لاگ بررسی شده
- [ ] خطایی وجود ندارد

---

## مرحله 8: بررسی نهایی

### لاگ
- [ ] هیچ خطای `TypeError` وجود ندارد
- [ ] هیچ خطای `404 Not Found` وجود ندارد
- [ ] هیچ خطای `500 Internal Server Error` وجود ندارد
- [ ] توکن از دیتابیس گرفته می‌شود (لاگ: "Using token from database")

### Webhook
- [ ] `getWebhookInfo` چک شده:
  ```bash
  # تلگرام
  curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
  
  # بله
  curl https://tapi.bale.ai/bot{TOKEN}/getWebhookInfo
  ```
- [ ] URL درست است (با `/api/`)
- [ ] `pending_update_count` = 0

### مستندات
- [ ] README.md به‌روزرسانی شده
- [ ] فایل feature documentation کامل است
- [ ] CHANGELOG.md به‌روز شده (اگر وجود دارد)

---

## ✅ تایید نهایی

- [ ] همه موارد بالا چک شده است
- [ ] ربات در تلگرام و بله تست شده
- [ ] لاگ بررسی شده و خطایی وجود ندارد
- [ ] مستندات کامل است
- [ ] Code review انجام شده
- [ ] آماده Commit است

---

## 🎯 یادآوری نهایی

قبل از commit:

```bash
# بررسی Route ها
php artisan route:list | grep my-bot

# بررسی Endpoint
php artisan tinker
>>> DB::table('webhook_endpoints')->where('endpoint_id', 'my-bot')->first();

# بررسی ترجمه‌ها
>>> trans('bot.my_new_key');

# تست curl
curl -X POST "https://bots.pardisania.ir/api/webhook-my-bot?token=TEST&bot_mother_id=1&origin=telegram&bot_id=1" \
  -H "Content-Type: application/json" \
  -d '{"message":{"chat":{"id":123},"text":"/start"}}'
```

---

**تاریخ:** ____________

**توسعه‌دهنده:** ____________

**نسخه:** ____________

---

## 📚 منابع مفید

- [راهنمای کامل ساخت ربات](./BOT_CREATION_GUIDE.md)
- [قوانین پروژه](../PROJECT_RULES.md)
- [.cursorrules](../.cursorrules)
