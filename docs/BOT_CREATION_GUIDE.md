# راهنمای ساخت ربات جدید برای Bot Mother

> **⚠️ مهم**: این سند حاوی تمام اصول و قوانین ساخت ربات جدید است. **حتماً** قبل از شروع کار مطالعه کنید!

## 📋 فهرست مطالب

- [اصول کلی](#اصول-کلی)
- [ساختار فایل‌ها](#ساختار-فایلها)
- [مراحل ساخت ربات جدید](#مراحل-ساخت-ربات-جدید)
- [خطاهای رایج و راه حل](#خطاهای-رایج-و-راه-حل)
- [چک‌لیست نهایی](#چکلیست-نهایی)

---

## 🎯 اصول کلی

### 1. همیشه از الگوهای موجود استفاده کنید

قبل از ساخت ربات جدید:
- ✅ **Controller های موجود** را بررسی کنید (QuranWordController, PresenterBotController, etc.)
- ✅ **Route های موجود** را در `routes/api.php` نگاه کنید
- ✅ **Webhook Endpoint ها** را در جدول `webhook_endpoints` چک کنید

### 2. قوانین مسیریابی (Routing)

⚠️ **بسیار مهم:**

```php
// ❌ اشتباه - بدون /api/
Route::post('/webhook-my-bot', [MyBotController::class, 'webhook']);

// ✅ درست - با /api/
Route::post('/api/webhook-my-bot', [MyBotController::class, 'webhook']);
```

**نکته مهم:** تمام route های ربات‌ها باید با `/api/` شروع شوند!

### 3. قوانین Webhook Endpoint

در جدول `webhook_endpoints`:

```php
[
    'endpoint_id' => 'my-bot',
    'name' => 'My Bot',
    'route' => 'api/webhook-my-bot',  // ✅ با api/ شروع می‌شود
    'requires_bot_mother_id' => true,
    'requires_token' => true,          // ✅ همیشه true (برای امنیت)
    'requires_language' => false,
    'supports_multiple_languages' => true,
    'is_active' => 1,
]
```

**⚠️ نکات کلیدی:**
- `route` باید با `api/` شروع شود
- `requires_token` باید **همیشه** `true` باشد (برای امنیت)
- `endpoint_id` باید منحصر به فرد باشد

---

## 📁 ساختار فایل‌ها

برای هر ربات جدید باید این فایل‌ها ایجاد شوند:

```
app/
├── Http/
│   └── Controllers/
│       └── MyBotController.php           # کنترلر اصلی
├── Services/
│   ├── MyBotService.php                  # Interface
│   └── MyBotServiceImpl.php              # Implementation
├── Repositories/
│   ├── MyBotRepository.php               # Interface
│   └── MyBotRepositoryImpl.php           # Implementation
├── Models/
│   └── MyBotRecord.php                   # مدل دیتابیس
└── Helpers/
    └── MyBotHelper.php                   # (اختیاری) توابع کمکی

database/
├── migrations/
│   └── YYYY_MM_DD_create_my_bot_tables.php
└── seeders/
    └── MyBotWebhookEndpointSeeder.php

routes/
└── api.php                                # ثبت Route

docs/
└── features/
    └── MY_BOT.md                         # مستندات فیچر

lang/
├── fa/bot.php                            # ترجمه فارسی
├── en/bot.php                            # ترجمه انگلیسی
└── [other languages]/bot.php            # سایر زبان‌ها
```

---

## 🚀 مراحل ساخت ربات جدید

### مرحله 1️⃣: طراحی دیتابیس

```bash
php artisan make:migration create_my_bot_records_table
```

**نکات:**
- جدول باید `bot_mother_id` داشته باشد
- برای ربات‌های چندزبانه، `language_code` اضافه کنید
- همیشه `timestamps()` اضافه کنید

### مرحله 2️⃣: ایجاد Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyBotRecord extends Model
{
    protected $fillable = [
        'bot_user_id',
        'bot_mother_id',
        'data',
        // ...
    ];

    // Relations
    public function botUser()
    {
        return $this->belongsTo(BotUsers::class, 'bot_user_id');
    }
}
```

### مرحله 3️⃣: ایجاد Repository

**Interface:**
```php
<?php

namespace App\Interfaces\Repositories;

interface MyBotRepository
{
    public function create(array $data);
    public function findByUser(int $userId);
    // ...
}
```

**Implementation:**
```php
<?php

namespace App\Repositories;

use App\Interfaces\Repositories\MyBotRepository;
use App\Models\MyBotRecord;
use Illuminate\Support\Facades\Log;

class MyBotRepositoryImpl implements MyBotRepository
{
    public function create(array $data)
    {
        Log::info('Creating my bot record', $data);
        return MyBotRecord::create($data);
    }
    
    // ...
}
```

### مرحله 4️⃣: ایجاد Service

**Interface:**
```php
<?php

namespace App\Interfaces\Services;

interface MyBotService
{
    public function processMessage(string $text, int $userId): array;
    // ...
}
```

**Implementation:**
```php
<?php

namespace App\Services;

use App\Interfaces\Services\MyBotService;
use App\Interfaces\Repositories\MyBotRepository;
use Illuminate\Support\Facades\Log;

class MyBotServiceImpl implements MyBotService
{
    public function __construct(
        private MyBotRepository $repository
    ) {}

    public function processMessage(string $text, int $userId): array
    {
        Log::info('Processing message', ['text' => $text, 'user_id' => $userId]);
        
        // Business logic here
        
        return ['success' => true];
    }
}
```

### مرحله 5️⃣: ایجاد Controller

**⚠️ نکات مهم Controller:**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;  // ✅ استفاده از Request معمولی
use App\Interfaces\Services\MyBotService;
use Illuminate\Support\Facades\Log;
use Telegram;

class MyBotController extends Controller
{
    public function __construct(
        private MyBotService $service
    ) {}

    public function webhook(Request $request)
    {
        $startTime = microtime(true);
        
        Log::info('🤖 [MyBot] Webhook received', [
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);

        try {
            // دریافت پارامترها
            $type = $request->input('origin', 'telegram');
            $botMotherId = $request->input('bot_mother_id');
            $botId = $request->input('bot_id');
            
            // ✅ ایجاد Bot Instance با توکن از دیتابیس
            $bot = $this->createBotInstance($request, $type, $botId);
            
            if (!$bot) {
                Log::error('❌ [MyBot] Could not create bot instance');
                return response()->json(['status' => 'error'], 200);
            }

            // ✅ لاگ ساده (نه LogHelper)
            Log::info('📥 [MyBot] Request details', [
                'type' => $type,
                'bot_id' => $botId,
                'bot_mother_id' => $botMotherId
            ]);

            // پردازش پیام
            $chatId = $bot->ChatID();
            $text = $bot->Text();
            
            // استفاده از Service
            $result = $this->service->processMessage($text, $chatId);
            
            // ارسال پاسخ
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => trans('bot.success_message')
            ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ [MyBot] Request processed', [
                'processing_time_ms' => $processingTime
            ]);

            return response()->json(['status' => 'ok'], 200);
            
        } catch (\Exception $e) {
            Log::error('❌ [MyBot] Exception occurred', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * ✅ ایجاد Bot Instance با توکن از دیتابیس
     */
    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        // اولویت 1: توکن از query string
        if ($request->has('token')) {
            $token = $request->input('token');
            Log::info('🔑 [MyBot] Using token from query string');
        } 
        // اولویت 2: توکن از دیتابیس
        elseif ($botId) {
            $bot = \App\Models\Bot::find($botId);
            if ($bot) {
                $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
                Log::info('🔑 [MyBot] Using token from database', ['bot_id' => $botId]);
            }
        }

        if (!$token) {
            Log::error('❌ [MyBot] No token found');
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }
}
```

**❌ خطاهای رایج در Controller:**

1. **استفاده از `LogHelper` با `Request` معمولی:**
```php
// ❌ اشتباه
LogHelper::log($request, $type, $bot);  // TypeError!

// ✅ درست
Log::info('📥 [MyBot] Request details', ['type' => $type]);
```

2. **فراموش کردن توکن از دیتابیس:**
```php
// ❌ اشتباه - فقط از env
$token = env('MY_BOT_TOKEN');

// ✅ درست - از دیتابیس
$bot = Bot::find($botId);
$token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
```

### مرحله 6️⃣: ثبت در AppServiceProvider

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    // Repository
    $this->app->bind(
        \App\Interfaces\Repositories\MyBotRepository::class,
        \App\Repositories\MyBotRepositoryImpl::class
    );

    // Service
    $this->app->bind(
        \App\Interfaces\Services\MyBotService::class,
        \App\Services\MyBotServiceImpl::class
    );
}
```

### مرحله 7️⃣: ایجاد Route

```php
// routes/api.php

use App\Http\Controllers\MyBotController;

// ✅ با /api/ در ابتدا
Route::post('/api/webhook-my-bot', [MyBotController::class, 'webhook']);
```

### مرحله 8️⃣: ایجاد Webhook Endpoint Seeder

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
                'route' => 'api/webhook-my-bot',  // ✅ با api/
                'description' => 'My Bot - Description here',
                'requires_bot_mother_id' => true,
                'requires_token' => true,          // ✅ همیشه true
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for My Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for My Bot already exists.');
        }
    }
}
```

### مرحله 9️⃣: اضافه کردن ترجمه‌ها

در **همه** فایل‌های زبان در `lang/*/bot.php`:

```php
// lang/fa/bot.php
'my_bot_welcome' => 'خوش آمدید به ربات من',
'my_bot_success' => 'عملیات با موفقیت انجام شد',

// lang/en/bot.php
'my_bot_welcome' => 'Welcome to My Bot',
'my_bot_success' => 'Operation completed successfully',

// و برای همه زبان‌های دیگر...
```

**⚠️ نکته:** باید برای **تمام 15 زبان موجود** ترجمه اضافه کنید:
- fa, en, ar-IQ, az, bs, de-DE, es, fr, he, pt-BR, pt-PT, ru, tr, ur, zh-CN

### مرحله 🔟: مستندسازی

ایجاد فایل `docs/features/MY_BOT.md`:

```markdown
# My Bot

## توضیحات
توضیح کامل درباره ربات...

## نحوه استفاده
...

## فایل‌های مرتبط
- `app/Http/Controllers/MyBotController.php`
- `app/Services/MyBotServiceImpl.php`
- `app/Repositories/MyBotRepositoryImpl.php`
...

## API Endpoints
- `POST /api/webhook-my-bot`

## تست
...
```

و اضافه کردن به `README.md`:

```markdown
## Features

- [My Bot](./docs/features/MY_BOT.md)
```

---

## ❌ خطاهای رایج و راه حل

### 1. خطای TypeError در LogHelper

**علت:**
```php
LogHelper::log($request, $type, $bot);  // $request از نوع Request است نه BotRequest
```

**راه حل:**
```php
Log::info('📥 [MyBot] Request details', [
    'type' => $type,
    'bot_id' => $request->input('bot_id')
]);
```

### 2. 404 Not Found

**علت:**
- Route بدون `/api/` ثبت شده
- Seeder اجرا نشده
- Cache پاک نشده

**راه حل:**
```bash
# چک کردن route
php artisan route:list | grep my-bot

# اجرای seeder
php artisan db:seed --class=MyBotWebhookEndpointSeeder

# پاک کردن cache
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

### 3. Webhook URL اشتباه (بدون /)

**علت:** در Seeder یا WebhookEndpointHelper مشکل وجود دارد

**راه حل:**
```php
// در Seeder
'route' => 'api/webhook-my-bot',  // با api/ شروع می‌شود

// در WebhookEndpointHelper (قبلاً فیکس شده)
$url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint['route'], '/');
```

### 4. توکن یافت نشد

**علت:** توکن از دیتابیس گرفته نمی‌شود

**راه حل:**
```php
// در Controller
private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
{
    // اولویت 1: از query string
    if ($request->has('token')) {
        return $type === 'bale' ? 
            new Telegram($request->input('token'), 'bale') : 
            new Telegram($request->input('token'));
    }
    
    // اولویت 2: از دیتابیس
    if ($botId) {
        $bot = \App\Models\Bot::find($botId);
        if ($bot) {
            $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
        }
    }
    
    return null;
}
```

### 5. requires_token = 0

**علت:** در Seeder `requires_token` را `false` یا `0` گذاشته‌اید

**راه حل:**
```php
'requires_token' => true,  // ✅ همیشه true
```

---

## ✅ چک‌لیست نهایی

قبل از commit و deploy:

### قبل از شروع:
- [ ] Controller های موجود را بررسی کردم
- [ ] Route های موجود را چک کردم
- [ ] Webhook Endpoint های موجود را دیدم

### فایل‌ها:
- [ ] Migration ایجاد شده
- [ ] Model ایجاد شده (با Relations)
- [ ] Repository Interface ایجاد شده
- [ ] Repository Implementation ایجاد شده
- [ ] Service Interface ایجاد شده
- [ ] Service Implementation ایجاد شده
- [ ] Controller ایجاد شده
- [ ] Seeder ایجاد شده

### تنظیمات:
- [ ] Repository در AppServiceProvider ثبت شده
- [ ] Service در AppServiceProvider ثبت شده
- [ ] Route با `/api/` در `api.php` ثبت شده
- [ ] Seeder اجرا شده
- [ ] `requires_token` = `true` است

### ترجمه:
- [ ] کلیدهای ترجمه به **همه 15 زبان** اضافه شده
- [ ] از `trans()` یا `__()` استفاده شده (نه hard-coded text)

### مستندات:
- [ ] فایل `docs/features/MY_BOT.md` ایجاد شده
- [ ] لینک در `README.md` اضافه شده

### تست:
- [ ] Migration اجرا شده: `php artisan migrate`
- [ ] Seeder اجرا شده: `php artisan db:seed --class=MyBotWebhookEndpointSeeder`
- [ ] Route چک شده: `php artisan route:list | grep my-bot`
- [ ] Cache پاک شده: `php artisan cache:clear && php artisan route:clear`
- [ ] Webhook URL درست است (با curl تست شده)
- [ ] ربات در Bot Mother ساخته شده
- [ ] به ربات `/start` زده شده
- [ ] لاگ چک شده: `tail -f storage/logs/laravel.log`

### Webhook:
- [ ] Webhook با توکن و bot_id درست ست شده
- [ ] `getWebhookInfo` چک شده (با curl یا BotHelper)
- [ ] Pending updates = 0

---

## 📚 منابع مفید

- [SOLID Principles](./PROJECT_RULES.md#اصول-solid)
- [Repository Pattern](./PROJECT_RULES.md#repository-pattern)
- [Service Pattern](./PROJECT_RULES.md#service-pattern)
- [Logging Guide](./LOGGING-GUIDE.md)
- [Translation Guide](./PROJECT_RULES.md#ترجمه-و-بینالمللیسازی)

---

## 🎯 نکات پایانی

1. **همیشه از الگوهای موجود پیروی کنید**
2. **Route ها باید با `/api/` شروع شوند**
3. **`requires_token` همیشه `true` باشد**
4. **از `LogHelper` با `Request` معمولی استفاده نکنید**
5. **توکن را از دیتابیس بگیرید**
6. **همه ترجمه‌ها را اضافه کنید (15 زبان)**
7. **مستندات را فراموش نکنید**
8. **قبل از commit چک‌لیست را بررسی کنید**

---

**آخرین به‌روزرسانی:** 2026-01-06

**نویسنده:** AI Assistant (برای پروژه Bot Generator)
