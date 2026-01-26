# 🔧 راهنمای سریع رفع مشکل ربات یک پیکسل کتاب

## ⚠️ مشکل: ربات پیام نمی‌دهد

### علت احتمالی:
ربات ساخته شده اما `endpoint_id` ثبت نشده یا webhook ثبت نشده است.

---

## 🚀 راه حل سریع (3 مرحله)

### مرحله 1: پیدا کردن Bot ID

```bash
php artisan tinker
```

```php
// پیدا کردن آخرین ربات‌های ساخته شده
\App\Models\Bot::orderBy('id', 'desc')->limit(5)->get(['id', 'bale_bot_name', 'telegram_bot_name', 'endpoint_id', 'created_at']);

// یا اگر می‌دانید نام ربات
\App\Models\Bot::where('bale_bot_name', 'like', '%<نام_ربات>%')
    ->orWhere('telegram_bot_name', 'like', '%<نام_ربات>%')
    ->get(['id', 'bale_bot_name', 'telegram_bot_name', 'endpoint_id']);
```

**شناسه ربات را یادداشت کنید** (مثلاً: `38`)

---

### مرحله 2: اصلاح endpoint_id (اگر لازم است)

```php
$botId = 38; // شناسه ربات شما
$bot = \App\Models\Bot::find($botId);

if ($bot && $bot->endpoint_id !== 'book-pixel') {
    $bot->endpoint_id = 'book-pixel';
    $bot->save();
    echo "✅ endpoint_id اصلاح شد\n";
} else {
    echo "ℹ️ endpoint_id درست است\n";
}
```

---

### مرحله 3: ثبت Webhook

#### روش A: استفاده از Command (ساده‌ترین)

```bash
php artisan book-pixel:reregister 38
```

#### روش B: دستی از طریق Tinker

```php
$botId = 38; // شناسه ربات شما
$bot = \App\Models\Bot::find($botId);

if (!$bot) {
    echo "❌ ربات یافت نشد!\n";
    exit;
}

// گرفتن توکن و نوع
$type = $bot->bale_bot_token ? 'bale' : 'telegram';
$token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
$botMotherId = $bot->bot_mother_id ?? 1;
$language = $bot->language_code ?? 'fa';

// اطمینان از endpoint_id
if ($bot->endpoint_id !== 'book-pixel') {
    $bot->endpoint_id = 'book-pixel';
    $bot->save();
}

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

---

## ✅ تست

### 1. بررسی Webhook

```php
$bot = \App\Models\Bot::find(38);
$token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
$type = $bot->bale_bot_token ? 'bale' : 'telegram';

$webhookInfo = \App\Helpers\BotHelper::checkWebhookInfo($token, $type);
print_r($webhookInfo);
```

**باید ببینید:**
- `ok = true`
- `result['url']` باید URL شما باشد

### 2. تست ربات

1. به ربات بروید
2. `/start` بزنید
3. باید پیام خوش‌آمدگویی را ببینید

### 3. بررسی لاگ‌ها

```bash
tail -f storage/logs/laravel.log | grep "BookPixel"
```

---

## 🚨 اگر هنوز کار نمی‌کند

### بررسی 1: آیا ربات در دیتابیس است؟

```php
\App\Models\Bot::find(38);
```

اگر `null` است، ربات را دوباره از طریق ربات مادر بسازید.

### بررسی 2: آیا Route درست است؟

```bash
php artisan route:list | grep book-pixel
```

باید ببینید:
```
POST api/webhook-book-pixel
```

### بررسی 3: آیا Controller درست کار می‌کند؟

لاگ‌ها را بررسی کنید:
```bash
tail -f storage/logs/laravel.log | grep "BookPixel"
```

اگر هیچ لاگی نمی‌بینید، webhook ثبت نشده است.

---

## 📝 خلاصه دستورات

```bash
# 1. پیدا کردن bot_id
php artisan tinker
>>> \App\Models\Bot::orderBy('id', 'desc')->limit(5)->get(['id', 'bale_bot_name', 'endpoint_id']);

# 2. ثبت مجدد webhook
php artisan book-pixel:reregister <BOT_ID>

# 3. بررسی webhook
php artisan tinker
>>> $bot = \App\Models\Bot::find(<BOT_ID>);
>>> \App\Helpers\BotHelper::checkWebhookInfo($bot->bale_bot_token, 'bale');

# 4. مشاهده لاگ‌ها
tail -f storage/logs/laravel.log | grep "BookPixel"
```
