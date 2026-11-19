# 🚀 Quick Reference - ساخت سریع ربات

> برای توسعه‌دهندگان: مرجع سریع برای ساخت ربات جدید

## 📌 نکات کلیدی (یادت نره!)

| مورد | ❌ اشتباه | ✅ درست |
|------|----------|---------|
| **Route** | `/webhook-bot` | `/api/webhook-bot` |
| **Seeder Route** | `webhook-bot` | `api/webhook-bot` |
| **requires_token** | `false` یا `0` | `true` یا `1` |
| **LogHelper** | `LogHelper::log($request, ...)` | `Log::info('...', [...])` |
| **Token** | فقط از `env()` | از دیتابیس (`Bot::find($botId)`) |
| **Hard-coded Text** | `"خوش آمدید"` | `trans('bot.welcome')` |

---

## ⚡ دستورات سریع

### ایجاد فایل‌ها

```bash
# Migration
php artisan make:migration create_my_bot_records_table

# Model
php artisan make:model MyBotRecord

# Controller
php artisan make:controller MyBotController
```

### بعد از تغییرات

```bash
# اجرای Migration
php artisan migrate

# اجرای Seeder
php artisan db:seed --class=MyBotWebhookEndpointSeeder

# پاک کردن Cache
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan optimize:clear
```

### بررسی

```bash
# چک کردن Route
php artisan route:list | grep my-bot

# چک کردن Endpoint در دیتابیس
php artisan tinker
>>> DB::table('webhook_endpoints')->where('endpoint_id', 'my-bot')->first();
>>> exit

# چک کردن Webhook (تلگرام)
curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo

# چک کردن Webhook (بله)
curl https://tapi.bale.ai/bot{TOKEN}/getWebhookInfo

# مشاهده لاگ
tail -f storage/logs/laravel.log | grep -i mybot
```

---

## 📝 Template های سریع

### 1. Controller - createBotInstance

```php
private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
{
    $token = null;

    if ($request->has('token')) {
        $token = $request->input('token');
        Log::info('🔑 Using token from query string');
    } elseif ($botId) {
        $bot = \App\Models\Bot::find($botId);
        if ($bot) {
            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            Log::info('🔑 Using token from database', ['bot_id' => $botId]);
        }
    }

    if (!$token) {
        Log::error('❌ No token found');
        return null;
    }

    return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
}
```

### 2. Controller - webhook Method

```php
public function webhook(Request $request)
{
    $startTime = microtime(true);
    
    Log::info('🤖 [MyBot] Webhook received', ['timestamp' => now()]);

    try {
        $type = $request->input('origin', 'telegram');
        $botMotherId = $request->input('bot_mother_id');
        $botId = $request->input('bot_id');
        
        $bot = $this->createBotInstance($request, $type, $botId);
        
        if (!$bot) {
            Log::error('❌ [MyBot] Could not create bot instance');
            return response()->json(['status' => 'error'], 200);
        }

        Log::info('📥 [MyBot] Request details', [
            'type' => $type,
            'bot_id' => $botId,
            'bot_mother_id' => $botMotherId
        ]);

        // Your logic here...

        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('✅ [MyBot] Completed', ['time_ms' => $processingTime]);

        return response()->json(['status' => 'ok'], 200);
        
    } catch (\Exception $e) {
        Log::error('❌ [MyBot] Exception', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return response()->json(['status' => 'error'], 200);
    }
}
```

### 3. Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MyBotWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'my-bot')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'my-bot',
                'name' => 'My Bot',
                'route' => 'api/webhook-my-bot',
                'description' => 'My Bot Description',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Created!');
        } else {
            $this->command->info('ℹ️  Already exists.');
        }
    }
}
```

### 4. Route

```php
use App\Http\Controllers\MyBotController;

Route::post('/api/webhook-my-bot', [MyBotController::class, 'webhook']);
```

### 5. Repository Interface

```php
<?php

namespace App\Interfaces\Repositories;

interface MyBotRepository
{
    public function create(array $data);
    public function findByUser(int $userId);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
}
```

### 6. Service Interface

```php
<?php

namespace App\Interfaces\Services;

interface MyBotService
{
    public function processMessage(string $text, int $userId): array;
}
```

---

## 🎯 خطاهای رایج و Fix سریع

### خطا: 404 Not Found

```bash
# چک کن
php artisan route:list | grep my-bot

# اگر نیست
php artisan route:clear
php artisan cache:clear

# دوباره چک کن
php artisan route:list | grep my-bot
```

### خطا: TypeError در LogHelper

```php
// ❌ حذف کن
LogHelper::log($request, $type, $bot);

// ✅ جایگزین کن
Log::info('📥 Request details', ['type' => $type]);
```

### خطا: Token not found

```php
// چک کن توکن از دیتابیس گرفته میشه
$bot = \App\Models\Bot::find($botId);
$token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
```

### خطا: Webhook URL بدون /api/

```php
// در Seeder چک کن
'route' => 'api/webhook-my-bot',  // باید با api/ شروع بشه
```

---

## 📋 زبان‌های پشتیبانی شده

باید برای **همه** این زبان‌ها ترجمه اضافه کنی:

```
lang/fa/bot.php       # فارسی
lang/en/bot.php       # انگلیسی
lang/ar-IQ/bot.php    # عربی عراقی
lang/az/bot.php       # آذربایجانی
lang/bs/bot.php       # بوسنیایی
lang/de-DE/bot.php    # آلمانی
lang/es/bot.php       # اسپانیایی
lang/fr/bot.php       # فرانسوی
lang/he/bot.php       # عبری
lang/pt-BR/bot.php    # پرتغالی برزیل
lang/pt-PT/bot.php    # پرتغالی پرتغال
lang/ru/bot.php       # روسی
lang/tr/bot.php       # ترکی
lang/ur/bot.php       # اردو
lang/zh-CN/bot.php    # چینی
```

---

## 🔗 لینک‌های مفید

- [📖 راهنمای کامل](./BOT_CREATION_GUIDE.md)
- [✅ چک‌لیست](./BOT_CREATION_CHECKLIST.md)
- [⚙️ .cursorrules](../.cursorrules)
- [📜 قوانین پروژه](../PROJECT_RULES.md)

---

## 💡 نکته طلایی

> **قبل از هر کاری Controller های موجود رو ببین و از همون الگو استفاده کن!**

Controller های خوب برای الگوبرداری:
- `QuranWordController.php` - کامل و پیچیده
- `PresenterBotController.php` - ساده و استاندارد
- `PrayerBotController.php` - جدیدترین (بعد از فیکس)

---

**آخرین به‌روزرسانی:** 2026-01-06
