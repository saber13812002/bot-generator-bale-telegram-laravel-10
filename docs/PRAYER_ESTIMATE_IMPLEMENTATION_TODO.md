# TODO: پیاده‌سازی سیستم تخمین گفتگومحور

## ✅ کارهای انجام شده

### 1. مستندسازی
- ✅ `docs/features/PRAYER_ESTIMATE_CONVERSATION.md` - طراحی کامل سیستم
- ✅ `docs/BOT_CREATION_GUIDE.md` - راهنمای کامل ساخت ربات
- ✅ `docs/BOT_CREATION_CHECKLIST.md` - چک‌لیست عملی
- ✅ `docs/QUICK_REFERENCE.md` - مرجع سریع
- ✅ `.cursorrules` - قوانین برای AI

### 2. دیتابیس
- ✅ Migration: `2026_01_06_222103_create_bot_user_states_table.php`
- ✅ Model: `app/Models/BotUserState.php`

### 3. Service Interface
- ✅ متدهای State Management به `PrayerBotService` اضافه شده

### 4. فیکس‌های قبلی
- ✅ `PrayerBotController.php` - حذف LogHelper و استفاده از Log
- ✅ `PrayerBotWebhookEndpointSeeder.php` - route با api/ و requires_token = true

---

## 🚧 کارهای باقیمانده

### مرحله 1: Service Implementation

فایل: `app/Services/PrayerBotServiceImpl.php`

```php
// متدهای زیر را اضافه کنید:

public function setState(
    int $botUserId,
    int $botMotherId,
    string $state,
    ?array $data = null,
    int $expiresInMinutes = 10
) {
    // پاک کردن state قبلی
    BotUserState::where('bot_user_id', $botUserId)->delete();
    
    // ایجاد state جدید
    return BotUserState::create([
        'bot_user_id' => $botUserId,
        'bot_mother_id' => $botMotherId,
        'state' => $state,
        'data' => $data,
        'expires_at' => now()->addMinutes($expiresInMinutes),
    ]);
}

public function getState(int $botUserId, ?string $state = null)
{
    $query = BotUserState::where('bot_user_id', $botUserId)->active();
    
    if ($state) {
        $query->where('state', $state);
    }
    
    return $query->latest()->first();
}

public function clearState(int $botUserId, ?string $state = null): bool
{
    $query = BotUserState::where('bot_user_id', $botUserId);
    
    if ($state) {
        $query->where('state', $state);
    }
    
    return $query->delete() > 0;
}

public function clearExpiredStates(): int
{
    return BotUserState::expired()->delete();
}

public function convertToRakats(int $value, string $unit): int
{
    return match($unit) {
        'day' => $value * 17,        // 5 نماز × 17 رکعت در روز
        'week' => $value * 7 * 17,   // 7 روز × 17 رکعت
        'month' => $value * 30 * 17, // 30 روز × 17 رکعت
        'year' => $value * 365 * 17, // 365 روز × 17 رکعت
        'rakat' => $value,           // مستقیم رکعت
        default => 0
    };
}

public function calculateEquivalents(int $rakats): array
{
    return [
        'days' => round($rakats / 17, 1),
        'weeks' => round($rakats / (7 * 17), 1),
        'months' => round($rakats / (30 * 17), 1),
        'years' => round($rakats / (365 * 17), 2),
    ];
}
```

### مرحله 2: Controller - دستور /estimate

فایل: `app/Http/Controllers/PrayerBotController.php`

```php
// در متد webhook، بعد از چک کردن دستورات اصلی:

// دستور تخمین
if ($text === '/estimate') {
    return $this->handleEstimateStart($bot, $chatId, $botUser, $type);
}

// چک کردن state برای دریافت عدد
$state = $this->service->getState($botUser->id);
if ($state && $state->state === 'estimate_waiting_value') {
    return $this->handleEstimateValue($bot, $chatId, $text, $state, $botUser, $type);
}

// متدهای جدید:

private function handleEstimateStart($bot, int $chatId, $botUser, string $type)
{
    Log::info('📊 [PrayerBot] Estimate start', ['chat_id' => $chatId]);
    
    // دریافت تخمین فعلی
    $currentEstimate = $this->service->getProgress($chatId, $type);
    
    $message = trans('bot.estimate_start') . "\n\n";
    
    if ($currentEstimate) {
        $message .= trans('bot.estimate_current', [
            'rakats' => $currentEstimate['estimate'],
            'equivalent' => $this->formatEquivalent($currentEstimate['estimate'])
        ]);
    } else {
        $message .= trans('bot.estimate_no_current');
    }
    
    $keyboard = [
        [
            ['text' => '📅 ' . trans('bot.unit_day'), 'callback_data' => 'estimate_day'],
            ['text' => '📆 ' . trans('bot.unit_week'), 'callback_data' => 'estimate_week'],
        ],
        [
            ['text' => '🗓️ ' . trans('bot.unit_month'), 'callback_data' => 'estimate_month'],
            ['text' => '📊 ' . trans('bot.unit_year'), 'callback_data' => 'estimate_year'],
        ],
        [
            ['text' => '🔢 ' . trans('bot.unit_rakat'), 'callback_data' => 'estimate_rakat'],
        ],
        [
            ['text' => '❌ ' . trans('bot.cancel'), 'callback_data' => 'estimate_cancel'],
        ]
    ];
    
    $bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $message,
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ]);
    
    return response()->json(['status' => 'ok'], 200);
}

private function handleEstimateCallback($bot, array $callbackQuery, $botUser, string $type, int $botMotherId)
{
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $callbackData = $callbackQuery['data'];
    
    // حذف کیبورد
    $bot->editMessageReplyMarkup([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'reply_markup' => json_encode(['inline_keyboard' => []])
    ]);
    
    if ($callbackData === 'estimate_cancel') {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => trans('bot.estimate_cancelled')
        ]);
        return response()->json(['status' => 'ok'], 200);
    }
    
    // استخراج واحد
    $unit = str_replace('estimate_', '', $callbackData);
    
    // ست کردن state
    $this->service->setState(
        $botUser->id,
        $botMotherId,
        'estimate_waiting_value',
        ['unit' => $unit],
        10 // 10 دقیقه
    );
    
    $unitName = trans('bot.unit_' . $unit);
    $message = trans('bot.estimate_unit_selected', ['unit' => $unitName]);
    
    $bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $message
    ]);
    
    return response()->json(['status' => 'ok'], 200);
}

private function handleEstimateValue($bot, int $chatId, string $text, $state, $botUser, string $type)
{
    // چک کردن عدد بودن
    if (!is_numeric($text) || $text <= 0) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => trans('bot.estimate_invalid_number')
        ]);
        return response()->json(['status' => 'ok'], 200);
    }
    
    $value = (int) $text;
    $unit = $state->getData('unit');
    
    // تبدیل به رکعت
    $rakats = $this->service->convertToRakats($value, $unit);
    
    // ذخیره تخمین
    $this->service->setEstimate($chatId, $rakats, "$value $unit");
    
    // پاک کردن state
    $this->service->clearState($botUser->id);
    
    // ارسال پیام تایید
    $unitName = trans('bot.unit_' . $unit);
    $message = trans('bot.estimate_saved', [
        'value' => $value,
        'unit' => $unitName,
        'rakats' => number_format($rakats)
    ]);
    
    $bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $message
    ]);
    
    return response()->json(['status' => 'ok'], 200);
}

private function formatEquivalent(int $rakats): string
{
    $equivalents = $this->service->calculateEquivalents($rakats);
    
    if ($equivalents['years'] >= 1) {
        return round($equivalents['years'], 1) . ' ' . trans('bot.unit_year');
    } elseif ($equivalents['months'] >= 1) {
        return round($equivalents['months'], 1) . ' ' . trans('bot.unit_month');
    } elseif ($equivalents['weeks'] >= 1) {
        return round($equivalents['weeks'], 1) . ' ' . trans('bot.unit_week');
    } else {
        return round($equivalents['days'], 1) . ' ' . trans('bot.unit_day');
    }
}
```

### مرحله 3: Callback Query Handler

در متد `webhook`، قبل از پردازش پیام، callback query را چک کنید:

```php
// پردازش callback query (دکمه‌های inline)
$update = $request->json()->all() ?? $request->all();
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackData = $callbackQuery['data'] ?? '';
    
    // Estimate callbacks
    if (str_starts_with($callbackData, 'estimate_')) {
        return $this->handleEstimateCallback($bot, $callbackQuery, $botUser, $type, $botMotherId);
    }
    
    // سایر callback ها...
}
```

### مرحله 4: ترجمه‌ها

فایل: `lang/fa/bot.php`

```php
// تخمین
'estimate_start' => '📊 تخمین نماز قضا\n\nلطفاً واحد تخمین خود را انتخاب کنید:',
'estimate_current' => '💡 تخمین فعلی شما: :rakats رکعت (:equivalent)',
'estimate_no_current' => '💡 هنوز تخمینی ثبت نکرده‌اید.',
'estimate_unit_selected' => '📊 تخمین بر اساس :unit\n\nچند :unit نماز قضا دارید؟\nلطفاً یک عدد ارسال کنید:\n\nمثال: 6',
'estimate_invalid_number' => '❌ لطفاً یک عدد معتبر (بزرگتر از صفر) ارسال کنید.',
'estimate_saved' => '✅ تخمین شما ثبت شد!\n\n📊 تخمین جدید:\n• :value :unit\n• حدود :rakats رکعت\n\n💡 این تخمین به عنوان هدف شما ذخیره شد و در گزارش‌های هفتگی نمایش داده می‌شود.\n\nبرای مشاهده آمار: /stats',
'estimate_cancelled' => '❌ عملیات لغو شد.',

// واحدها
'unit_day' => 'روز',
'unit_week' => 'هفته',
'unit_month' => 'ماه',
'unit_year' => 'سال',
'unit_rakat' => 'رکعت',

// عمومی
'cancel' => 'انصراف',
```

**نکته:** این ترجمه‌ها را برای **همه 15 زبان** اضافه کنید!

### مرحله 5: Command برای پاک کردن State های منقضی شده

فایل جدید: `app/Console/Commands/ClearExpiredStates.php`

```bash
php artisan make:command ClearExpiredStates
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Interfaces\Services\PrayerBotService;

class ClearExpiredStates extends Command
{
    protected $signature = 'states:clear-expired';
    protected $description = 'پاک کردن state های منقضی شده';

    public function __construct(
        private PrayerBotService $service
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('پاک کردن state های منقضی شده...');
        
        $count = $this->service->clearExpiredStates();
        
        $this->info("✅ $count state پاک شد.");
        
        return 0;
    }
}
```

و در `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // ...
    
    // پاک کردن state های منقضی شده هر ساعت
    $schedule->command('states:clear-expired')->hourly();
}
```

### مرحله 6: تست

```bash
# 1. Migration
php artisan migrate

# 2. Cache
php artisan cache:clear
php artisan route:clear

# 3. تست با ربات
# - /estimate
# - انتخاب واحد
# - ارسال عدد
# - چک کردن /stats
```

---

## 📋 چک‌لیست نهایی

- [ ] Service Implementation کامل شده
- [ ] Controller متدها اضافه شده
- [ ] Callback Query Handler اضافه شده
- [ ] ترجمه‌ها برای همه 15 زبان اضافه شده
- [ ] Command برای پاک کردن State ها ایجاد شده
- [ ] Migration اجرا شده
- [ ] تست با ربات انجام شده
- [ ] لاگ بررسی شده
- [ ] مستندات به‌روز شده

---

## 🎯 نکات مهم

1. **State Expiration**: State ها بعد از 10 دقیقه منقضی می‌شوند
2. **Validation**: حتماً عدد را validate کنید (مثبت و معقول)
3. **Cleanup**: Command `states:clear-expired` را هر ساعت اجرا کنید
4. **Logging**: همه مراحل را لاگ کنید
5. **User Experience**: پیام‌های واضح و راهنما ارسال کنید

---

**تاریخ ایجاد:** 2026-01-06  
**وضعیت:** در حال پیاده‌سازی  
**اولویت:** متوسط
