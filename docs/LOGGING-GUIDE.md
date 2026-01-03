# راهنمای لاگینگ (Logging Guide)

این سند راهنمای کامل استفاده از سیستم لاگینگ در پروژه است.

## 📋 انواع لاگ

در این پروژه از دو نوع لاگ استفاده می‌شود:

1. **Bot Logs**: لاگ‌های مربوط به ربات‌ها که در دیتابیس ذخیره می‌شوند
2. **Application Logs**: لاگ‌های عمومی اپلیکیشن که در فایل ذخیره می‌شوند

## 🤖 Bot Logs (LogHelper)

برای لاگ کردن پیام‌های دریافتی از ربات‌ها از `LogHelper` استفاده می‌کنیم.

### استفاده

```php
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;

public function index(BotRequest $request)
{
    $bot = new Telegram($token, 'bale');
    
    try {
        // لاگ کردن پیام دریافتی
        LogHelper::log($request, $type, $bot);
    } catch (Exception $e) {
        // در صورت خطا در لاگ، از Application Log استفاده می‌کنیم
        Log::info('LogHelper error: ' . $e->getMessage());
    }
    
    // باقی کد...
}
```

### چه چیزهایی لاگ می‌شوند؟

- `webhook_endpoint_uri`: مسیر endpoint وب‌هوک
- `bot_mother_id`: شناسه ربات مادر
- `language`: زبان
- `command_type`: نوع دستور
- `locale`: locale فعلی
- `type`: نوع ربات (telegram, bale, ...)
- `text`: متن پیام (تا 199 کاراکتر)
- `is_command`: آیا پیام یک دستور است؟
- `channel_group_type`: نوع کانال/گروه
- `bot_id`: شناسه ربات
- `chat_id`: شناسه چت

### بررسی لاگ قبلی

```php
// بررسی آیا آخرین لاگ وجود دارد
[$lastStatus, $phrase] = LogHelper::isLastLogAvailable($request, $bot);
```

## 📝 Application Logs (Laravel Log Facade)

برای لاگ کردن رویدادهای عمومی اپلیکیشن از `Log` facade استفاده می‌کنیم.

### سطوح لاگ

- `Log::emergency()` - فوری
- `Log::alert()` - هشدار
- `Log::critical()` - بحرانی
- `Log::error()` - خطا
- `Log::warning()` - هشدار
- `Log::notice()` - اطلاع
- `Log::info()` - اطلاعات
- `Log::debug()` - دیباگ

### استفاده در Controllers

```php
use Illuminate\Support\Facades\Log;

public function index(Request $request)
{
    try {
        Log::info('Processing request', ['user_id' => $request->user()->id]);
        
        // کد شما...
        
        Log::info('Request processed successfully');
        return response()->json(['success' => true]);
        
    } catch (Exception $e) {
        Log::error('Error processing request', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json(['error' => 'خطا رخ داد'], 500);
    }
}
```

### استفاده در Services

```php
use Illuminate\Support\Facades\Log;

public function processData(array $data): array
{
    // لاگ شروع
    Log::info('Processing data', ['data' => $data]);
    
    try {
        // پردازش داده‌ها
        $result = $this->repository->save($data);
        
        // لاگ موفقیت
        Log::info('Data processed successfully', [
            'id' => $result->id,
            'data' => $data
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        // لاگ خطا
        Log::error('Error processing data', [
            'error' => $e->getMessage(),
            'data' => $data,
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e;
    }
}
```

### استفاده در Repositories

```php
use Illuminate\Support\Facades\Log;

public function save(array $data): Model
{
    Log::debug('Saving to database', ['data' => $data]);
    
    try {
        $model = Model::create($data);
        Log::debug('Saved successfully', ['id' => $model->id]);
        return $model;
    } catch (Exception $e) {
        Log::error('Database save failed', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        throw $e;
    }
}
```

## 🎯 قوانین لاگینگ

### چه زمانی لاگ بنویسیم؟

✅ **باید لاگ داشته باشد:**
- شروع و پایان توابع مهم
- موفقیت‌های مهم (مثل ثبت‌نام، پرداخت، ...)
- تمام خطاها (Exception ها)
- عملیات‌های حساس (مثل تغییر اطلاعات کاربر)
- دسترسی‌های مهم (مثل login، تغییر رمز عبور)

✅ **می‌تواند لاگ داشته باشد:**
- عملیات‌های معمولی (با سطح debug)
- اطلاعات مفید برای debugging

❌ **نباید لاگ داشته باشد:**
- اطلاعات حساس (مثل رمز عبور، token ها)
- لاگ‌های غیرضروری که فایل لاگ را بزرگ می‌کنند

### فرمت لاگ

```php
// ✅ خوب - واضح و با context
Log::info('User registered', [
    'user_id' => $user->id,
    'email' => $user->email
]);

// ❌ بد - بدون context
Log::info('User registered');

// ✅ خوب - با جزئیات خطا
Log::error('Failed to send email', [
    'user_id' => $user->id,
    'error' => $e->getMessage(),
    'email' => $user->email
]);

// ❌ بد - بدون جزئیات
Log::error('Failed to send email');
```

### Context در لاگ

همیشه context مناسب اضافه کنید:

```php
Log::info('Processing payment', [
    'user_id' => $user->id,
    'amount' => $amount,
    'payment_method' => $paymentMethod,
    'transaction_id' => $transactionId
]);
```

### محافظت از اطلاعات حساس

❌ **اشتباه:**
```php
Log::info('User login', [
    'password' => $request->password, // هرگز!
    'token' => $secretToken // هرگز!
]);
```

✅ **درست:**
```php
Log::info('User login', [
    'user_id' => $user->id,
    'email' => $user->email,
    // اطلاعات حساس را لاگ نکنید
]);
```

## 📍 محل فایل‌های لاگ

- **Application Logs**: `storage/logs/laravel.log`
- **Daily Logs**: `storage/logs/laravel-YYYY-MM-DD.log`
- **Bot Logs**: در جدول `bot_logs` در دیتابیس

## ⚙️ تنظیمات لاگ

تنظیمات لاگ در `config/logging.php` انجام می‌شود.

### تغییر سطح لاگ

در فایل `.env`:
```env
LOG_LEVEL=debug  # emergency, alert, critical, error, warning, notice, info, debug
```

### تغییر Channel

```env
LOG_CHANNEL=daily  # single, daily, stack, ...
```

## 🔍 بررسی لاگ‌ها

### بررسی لاگ‌های فایلی

```bash
# مشاهده آخرین خطوط لاگ
tail -f storage/logs/laravel.log

# جستجو در لاگ
grep "error" storage/logs/laravel.log

# مشاهده لاگ‌های امروز
cat storage/logs/laravel-$(date +%Y-%m-%d).log
```

### بررسی Bot Logs

```php
use App\Models\BotLog;

// دریافت لاگ‌های یک ربات
$logs = BotLog::where('bot_id', $botId)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// دریافت لاگ‌های یک کاربر
$logs = BotLog::where('chat_id', $chatId)
    ->orderBy('created_at', 'desc')
    ->get();
```

## 📊 نمونه‌های کاربردی

### Controller با لاگ کامل

```php
use Illuminate\Support\Facades\Log;
use App\Services\SomeService;

public function store(Request $request, SomeService $service)
{
    Log::info('Request received', [
        'endpoint' => 'store',
        'user_id' => $request->user()->id ?? null,
        'data' => $request->except(['password', 'token'])
    ]);
    
    try {
        $result = $service->process($request->validated());
        
        Log::info('Request processed successfully', [
            'result_id' => $result->id ?? null
        ]);
        
        return response()->json($result, 201);
        
    } catch (ValidationException $e) {
        Log::warning('Validation failed', [
            'errors' => $e->errors()
        ]);
        
        return response()->json(['errors' => $e->errors()], 422);
        
    } catch (Exception $e) {
        Log::error('Error processing request', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return response()->json(['error' => 'خطای سرور'], 500);
    }
}
```

### Service با لاگ کامل

```php
use Illuminate\Support\Facades\Log;

public function createUser(array $data): User
{
    Log::info('Creating user', ['email' => $data['email'] ?? null]);
    
    try {
        // Validation
        $this->validateUserData($data);
        
        // Create user
        $user = $this->repository->create($data);
        
        Log::info('User created successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        
        return $user;
        
    } catch (ValidationException $e) {
        Log::warning('User creation validation failed', [
            'errors' => $e->errors(),
            'data' => $data
        ]);
        throw $e;
        
    } catch (Exception $e) {
        Log::error('User creation failed', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        throw $e;
    }
}

private function validateUserData(array $data): void
{
    Log::debug('Validating user data');
    // Validation logic...
}
```

## 🎓 Best Practices

1. **همیشه context اضافه کنید**: لاگ بدون context مفید نیست
2. **از سطح مناسب استفاده کنید**: error برای خطا، info برای اطلاعات مهم
3. **اطلاعات حساس را لاگ نکنید**: رمز عبور، token ها، ...
4. **لاگ را خوانا بنویسید**: پیام‌های واضح و قابل فهم
5. **استفاده از array برای context**: به جای string concatenation
6. **لاگ خطاها را کامل بنویسید**: message, trace, file, line
7. **لاگ موفقیت‌های مهم**: برای audit trail
8. **از Bot Logs برای پیام‌های ربات استفاده کنید**: LogHelper
9. **از Application Logs برای بقیه استفاده کنید**: Log facade
10. **لاگ‌های debug را در production کم کنید**: استفاده از LOG_LEVEL

---

**آخرین بروزرسانی**: تاریخ آخرین تغییر


