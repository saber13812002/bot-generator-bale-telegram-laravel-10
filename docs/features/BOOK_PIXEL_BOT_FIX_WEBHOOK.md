# راهنمای رفع مشکل Webhook ربات یک پیکسل کتاب

## 🔍 تشخیص مشکل

اگر ربات پیام نمی‌دهد، احتمالاً webhook ثبت نشده است.

### مرحله 1: پیدا کردن Bot ID

```bash
php artisan tinker
```

```php
// پیدا کردن ربات‌های book-pixel
\App\Models\Bot::where('endpoint_id', 'book-pixel')->get(['id', 'bale_bot_name', 'telegram_bot_name']);

// یا اگر endpoint_id ثبت نشده، با نام پیدا کنید
\App\Models\Bot::where('bale_bot_name', 'like', '%book%')
    ->orWhere('telegram_bot_name', 'like', '%book%')
    ->get(['id', 'bale_bot_name', 'telegram_bot_name', 'endpoint_id']);
```

### مرحله 2: بررسی Webhook

```php
$bot = \App\Models\Bot::find(<BOT_ID>);
$token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
$type = $bot->bale_bot_token ? 'bale' : 'telegram';

// بررسی webhook
\App\Helpers\BotHelper::checkWebhookInfo($token, $type);
```

**اگر webhook ثبت نشده:**
- `url` خالی است
- یا `ok = false`

---

## 🔧 راه حل: ثبت مجدد Webhook

### روش 1: استفاده از Command (ساده‌ترین)

```bash
# پیدا کردن bot_id (از مرحله 1)
php artisan book-pixel:reregister <BOT_ID>
```

**مثال:**
```bash
php artisan book-pixel:reregister 38
```

### روش 2: دستی از طریق Tinker

```bash
php artisan tinker
```

```php
$botId = 38; // شناسه ربات شما
$bot = \App\Models\Bot::find($botId);

if (!$bot) {
    echo "❌ ربات یافت نشد!\n";
    exit;
}

// اگر endpoint_id درست نیست، اصلاح کنید
if ($bot->endpoint_id !== 'book-pixel') {
    $bot->endpoint_id = 'book-pixel';
    $bot->save();
    echo "✅ endpoint_id اصلاح شد\n";
}

// گرفتن توکن و نوع
$type = $bot->bale_bot_token ? 'bale' : 'telegram';
$token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
$botMotherId = $bot->bot_mother_id ?? 1;
$language = $bot->language_code ?? 'fa';

// ساخت webhook URL
$webhookUrl = \App\Helpers\WebhookEndpointHelper::createWebhookUrl(
    'book-pixel',
    $bot,
    $type,
    $language,
    $botMotherId
);

echo "📝 Webhook URL: {$webhookUrl}\n";

// ثبت webhook
$telegramBot = new \Telegram($token, $type);
$result = $telegramBot->setWebhook($webhookUrl);

if ($result['ok']) {
    echo "✅ Webhook ثبت شد!\n";
    
    // به‌روزرسانی وضعیت در دیتابیس
    if ($type === 'bale') {
        $bot->bale_webhook_is_set = 1;
    } else {
        $bot->telegram_webhook_is_set = 1;
    }
    $bot->save();
    
    // بررسی webhook
    $webhookInfo = \App\Helpers\BotHelper::checkWebhookInfo($token, $type);
    echo "🔗 Webhook URL: " . ($webhookInfo['result']['url'] ?? 'N/A') . "\n";
    echo "📊 Pending updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
} else {
    echo "❌ خطا: " . ($result['description'] ?? 'Unknown error') . "\n";
}
```

### روش 3: از طریق SQL (اگر command کار نکرد)

```sql
-- پیدا کردن bot_id
SELECT id, bale_bot_name, telegram_bot_name, endpoint_id
FROM bots
WHERE endpoint_id = 'book-pixel'
   OR bale_bot_name LIKE '%book%'
   OR telegram_bot_name LIKE '%book%';

-- اگر endpoint_id درست نیست، اصلاح کنید
UPDATE bots
SET endpoint_id = 'book-pixel'
WHERE id = <BOT_ID>;
```

سپس از روش 1 یا 2 استفاده کنید.

---

## ✅ تست بعد از ثبت Webhook

### 1. بررسی Webhook

```bash
php artisan tinker
```

```php
$bot = \App\Models\Bot::find(<BOT_ID>);
$token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
$type = $bot->bale_bot_token ? 'bale' : 'telegram';

$webhookInfo = \App\Helpers\BotHelper::checkWebhookInfo($token, $type);
print_r($webhookInfo);
```

**باید ببینید:**
- `ok = true`
- `result['url']` باید URL شما باشد
- `result['pending_update_count']` تعداد پیام‌های در انتظار

### 2. تست ربات

1. به ربات بروید
2. `/start` بزنید
3. باید پیام خوش‌آمدگویی را ببینید

### 3. بررسی لاگ‌ها

```bash
tail -f storage/logs/laravel.log | grep "BookPixel"
```

**باید لاگ‌های زیر را ببینید:**
```
🤖 [BookPixel] Webhook received
📥 [BookPixel] Request details
📨 [BookPixel] Message received
✅ [BookPixel] Request processed
```

---

## 🚨 اگر هنوز کار نمی‌کند

### بررسی 1: Route

```bash
php artisan route:list | grep book-pixel
```

باید ببینید:
```
POST api/webhook-book-pixel
```

### بررسی 2: Controller

```bash
php artisan tinker
```

```php
// تست دستی controller
$request = new \Illuminate\Http\Request();
$request->merge([
    'origin' => 'bale',
    'bot_mother_id' => 1,
    'bot_id' => <BOT_ID>,
    'token' => '<TOKEN>'
]);

// این فقط برای تست است - در production استفاده نکنید
```

### بررسی 3: بررسی URL Webhook

```bash
php artisan tinker
```

```php
$bot = \App\Models\Bot::find(<BOT_ID>);
$webhookUrl = \App\Helpers\WebhookEndpointHelper::createWebhookUrl(
    'book-pixel',
    $bot,
    'bale', // یا 'telegram'
    'fa',
    1
);

echo "Webhook URL: {$webhookUrl}\n";
```

**بررسی کنید:**
- URL با `https://` شروع می‌شود
- شامل `bot_id` است
- شامل `token` است
- شامل `origin` است
- شامل `bot_mother_id` است

---

## 📝 خلاصه دستورات

```bash
# 1. پیدا کردن bot_id
php artisan tinker
>>> \App\Models\Bot::where('endpoint_id', 'book-pixel')->value('id');

# 2. ثبت مجدد webhook
php artisan book-pixel:reregister <BOT_ID>

# 3. بررسی webhook
php artisan tinker
>>> $bot = \App\Models\Bot::find(<BOT_ID>);
>>> \App\Helpers\BotHelper::checkWebhookInfo($bot->bale_bot_token, 'bale');

# 4. مشاهده لاگ‌ها
tail -f storage/logs/laravel.log | grep "BookPixel"
```

---

## ✅ چک‌لیست نهایی

- [ ] ربات در دیتابیس وجود دارد
- [ ] `endpoint_id = 'book-pixel'` است
- [ ] Webhook ثبت شده است
- [ ] Webhook URL درست است
- [ ] Route درست است
- [ ] Controller درست کار می‌کند
- [ ] لاگ‌ها نشان می‌دهند که webhook دریافت می‌شود
- [ ] ربات به پیام‌ها پاسخ می‌دهد
