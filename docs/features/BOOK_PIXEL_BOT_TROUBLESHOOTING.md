# راهنمای عیب‌یابی ربات یک پیکسل کتاب

## 🔍 مشکل: ربات پیام نمی‌دهد

### بررسی 1: آیا webhook ثبت شده است؟

```bash
# بررسی webhook ربات
php artisan tinker
>>> $bot = \App\Models\Bot::where('endpoint_id', 'book-pixel')->first();
>>> $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
>>> $type = $bot->bale_bot_token ? 'bale' : 'telegram';
>>> \App\Helpers\BotHelper::checkWebhookInfo($token, $type);
```

**اگر webhook ثبت نشده:**
```bash
# ثبت مجدد webhook
php artisan book-pixel:reregister <BOT_ID>
```

### بررسی 2: آیا ربات در دیتابیس وجود دارد؟

```sql
SELECT id, bale_bot_name, telegram_bot_name, endpoint_id, bale_webhook_is_set, telegram_webhook_is_set
FROM bots
WHERE endpoint_id = 'book-pixel';
```

**اگر ربات وجود ندارد:**
- ربات را دوباره از طریق ربات مادر بسازید

### بررسی 3: بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های ربات
tail -f storage/logs/laravel.log | grep "BookPixel"
```

**لاگ‌های مورد انتظار:**
```
🤖 [BookPixel] Webhook received
📥 [BookPixel] Request details
📨 [BookPixel] Message received
✅ [BookPixel] Request processed
```

**اگر لاگی نمی‌بینید:**
- webhook ثبت نشده است
- یا URL درست نیست

### بررسی 4: بررسی Route

```bash
php artisan route:list | grep book-pixel
```

باید این route را ببینید:
```
POST api/webhook-book-pixel
```

### بررسی 5: تست دستی Webhook

```bash
# گرفتن توکن ربات
php artisan tinker
>>> $bot = \App\Models\Bot::where('endpoint_id', 'book-pixel')->first();
>>> echo $bot->bale_bot_token ?? $bot->telegram_bot_token;

# تست webhook با curl
curl -X POST "https://bots.pardisania.ir/api/webhook-book-pixel?token=<TOKEN>&bot_mother_id=1&origin=bale&bot_id=<BOT_ID>" \
  -H "Content-Type: application/json" \
  -d '{"message":{"chat":{"id":123456},"text":"/start"}}'
```

---

## 🔧 راه حل‌های سریع

### راه حل 1: ثبت مجدد Webhook

```bash
# پیدا کردن bot_id
php artisan tinker
>>> \App\Models\Bot::where('endpoint_id', 'book-pixel')->value('id');

# ثبت مجدد
php artisan book-pixel:reregister <BOT_ID>
```

### راه حل 2: بررسی و اصلاح endpoint_id

```sql
-- اگر endpoint_id درست نیست
UPDATE bots 
SET endpoint_id = 'book-pixel' 
WHERE id = <BOT_ID>;
```

### راه حل 3: بررسی توکن

```sql
-- بررسی توکن
SELECT id, bale_bot_token, telegram_bot_token, endpoint_id
FROM bots
WHERE id = <BOT_ID>;
```

---

## 📝 چک‌لیست عیب‌یابی

- [ ] ربات در دیتابیس وجود دارد
- [ ] `endpoint_id = 'book-pixel'` است
- [ ] توکن ربات موجود است
- [ ] Webhook ثبت شده است (`bale_webhook_is_set = 1` یا `telegram_webhook_is_set = 1`)
- [ ] Route درست است (`/api/webhook-book-pixel`)
- [ ] لاگ‌ها نشان می‌دهند که webhook دریافت می‌شود
- [ ] Controller درست کار می‌کند

---

## 🚨 مشکلات رایج

### مشکل 1: "ربات پیام نمی‌دهد"

**علت:** Webhook ثبت نشده یا URL درست نیست

**راه حل:**
```bash
php artisan book-pixel:reregister <BOT_ID>
```

### مشکل 2: "لاگی نمی‌بینم"

**علت:** Webhook اصلاً به سرور نمی‌رسد

**راه حل:**
1. بررسی کنید که webhook درست ثبت شده:
```bash
php artisan tinker
>>> $bot = \App\Models\Bot::find(<BOT_ID>);
>>> $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
>>> \App\Helpers\BotHelper::checkWebhookInfo($token, 'bale');
```

2. اگر webhook ثبت نشده، دوباره ثبت کنید

### مشکل 3: "خطای 404"

**علت:** Route درست نیست

**راه حل:**
```bash
php artisan route:clear
php artisan route:cache
php artisan route:list | grep book-pixel
```

### مشکل 4: "خطای No token found"

**علت:** توکن در دیتابیس نیست یا `bot_id` درست نیست

**راه حل:**
1. بررسی کنید که `bot_id` در URL webhook درست است
2. بررسی کنید که توکن در دیتابیس موجود است

---

## 📞 دستورات مفید

```bash
# بررسی ربات‌های book-pixel
php artisan tinker
>>> \App\Models\Bot::where('endpoint_id', 'book-pixel')->get(['id', 'bale_bot_name', 'telegram_bot_name']);

# بررسی webhook
php artisan tinker
>>> $bot = \App\Models\Bot::find(<BOT_ID>);
>>> $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
>>> \App\Helpers\BotHelper::checkWebhookInfo($token, 'bale');

# ثبت مجدد webhook
php artisan book-pixel:reregister <BOT_ID>

# مشاهده لاگ‌ها
tail -f storage/logs/laravel.log | grep "BookPixel"

# پاک کردن cache
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

---

## ✅ بعد از رفع مشکل

1. به ربات بروید و `/start` بزنید
2. باید پیام خوش‌آمدگویی را ببینید
3. لاگ‌ها را بررسی کنید:
```bash
tail -f storage/logs/laravel.log | grep "BookPixel"
```
