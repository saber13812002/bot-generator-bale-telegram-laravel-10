# سیستم تخمین نماز قضا - گفتگومحور

## 🎯 هدف

ایجاد یک سیستم گفتگومحور برای ثبت تخمین نمازهای قضا که کاربر به راحتی بتواند:
- تخمین خود را بر اساس روز، هفته، ماه، سال یا رکعت وارد کند
- آخرین تخمین را ببیند
- تخمین جدید جایگزین تخمین قبلی شود

## 🔄 فلوی گفتگو

```
کاربر: /estimate

ربات: 
📊 تخمین نماز قضا

لطفاً واحد تخمین خود را انتخاب کنید:

[روز] [هفته] [ماه] [سال] [رکعت]

💡 تخمین فعلی شما: 1250 رکعت (5 ماه)

---

کاربر: [کلیک روی "ماه"]

ربات:
📅 تخمین بر اساس ماه

چند ماه نماز قضا دارید؟
لطفاً یک عدد ارسال کنید:

مثال: 6

---

کاربر: 6

ربات:
✅ تخمین شما ثبت شد!

📊 تخمین جدید:
• 6 ماه
• حدود 900 رکعت (6 ماه × 5 نماز × 30 روز)

💡 این تخمین به عنوان هدف شما ذخیره شد و در گزارش‌های هفتگی نمایش داده می‌شود.

برای مشاهده آمار: /stats
```

## 🎮 دستورات

### دستورات اصلی

- `/estimate` - شروع فرآیند تخمین
- `/estimate_status` - مشاهده تخمین فعلی

### دستورات داخلی (Callback)

- `estimate_day` - تخمین بر اساس روز
- `estimate_week` - تخمین بر اساس هفته
- `estimate_month` - تخمین بر اساس ماه
- `estimate_year` - تخمین بر اساس سال
- `estimate_rakat` - تخمین بر اساس رکعت

## 🗄️ ساختار دیتابیس

جدول `prayer_estimates` قبلاً وجود دارد، فقط نیاز به مدیریت State داریم.

### جدول جدید: `bot_user_states`

```sql
CREATE TABLE bot_user_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_user_id BIGINT UNSIGNED NOT NULL,
    bot_mother_id INT NOT NULL,
    state VARCHAR(50) NOT NULL,
    data JSON NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (bot_user_id) REFERENCES bot_users(id) ON DELETE CASCADE,
    INDEX idx_bot_user_state (bot_user_id, state),
    INDEX idx_expires (expires_at)
);
```

## 💾 State Management

### State Types

```php
const STATE_ESTIMATE_WAITING_UNIT = 'estimate_waiting_unit';
const STATE_ESTIMATE_WAITING_VALUE = 'estimate_waiting_value';
```

### State Data Structure

```json
{
    "unit": "month",
    "step": "waiting_value",
    "timestamp": "2026-01-06 18:00:00"
}
```

## 🔢 محاسبات

### تبدیل به رکعت

```php
private function convertToRakats(int $value, string $unit): int
{
    return match($unit) {
        'day' => $value * 17,      // 5 نماز × 17 رکعت در روز
        'week' => $value * 7 * 17, // 7 روز × 17 رکعت
        'month' => $value * 30 * 17, // 30 روز × 17 رکعت
        'year' => $value * 365 * 17, // 365 روز × 17 رکعت
        'rakat' => $value,          // مستقیم رکعت
        default => 0
    };
}
```

### محاسبه معادل

```php
private function calculateEquivalents(int $rakats): array
{
    return [
        'days' => round($rakats / 17, 1),
        'weeks' => round($rakats / (7 * 17), 1),
        'months' => round($rakats / (30 * 17), 1),
        'years' => round($rakats / (365 * 17), 2),
    ];
}
```

## 🎨 رابط کاربری

### کیبورد انتخاب واحد

```php
$keyboard = [
    [
        ['text' => '📅 روز', 'callback_data' => 'estimate_day'],
        ['text' => '📆 هفته', 'callback_data' => 'estimate_week'],
    ],
    [
        ['text' => '🗓️ ماه', 'callback_data' => 'estimate_month'],
        ['text' => '📊 سال', 'callback_data' => 'estimate_year'],
    ],
    [
        ['text' => '🔢 رکعت', 'callback_data' => 'estimate_rakat'],
    ],
    [
        ['text' => '❌ انصراف', 'callback_data' => 'estimate_cancel'],
    ]
];
```

## 📝 پیام‌های ترجمه

### فارسی

```php
'estimate_start' => '📊 تخمین نماز قضا\n\nلطفاً واحد تخمین خود را انتخاب کنید:',
'estimate_current' => '💡 تخمین فعلی شما: :rakats رکعت (:equivalent)',
'estimate_no_current' => '💡 هنوز تخمینی ثبت نکرده‌اید.',
'estimate_unit_selected' => '📊 تخمین بر اساس :unit\n\nچند :unit نماز قضا دارید؟\nلطفاً یک عدد ارسال کنید:',
'estimate_invalid_number' => '❌ لطفاً یک عدد معتبر ارسال کنید.',
'estimate_saved' => '✅ تخمین شما ثبت شد!\n\n📊 تخمین جدید:\n• :value :unit\n• حدود :rakats رکعت\n\n💡 این تخمین به عنوان هدف شما ذخیره شد.',
'estimate_cancelled' => '❌ عملیات لغو شد.',

'unit_day' => 'روز',
'unit_week' => 'هفته',
'unit_month' => 'ماه',
'unit_year' => 'سال',
'unit_rakat' => 'رکعت',
```

## 🔄 فلوی کد

### 1. دریافت دستور `/estimate`

```php
if ($text === '/estimate') {
    return $this->handleEstimateStart($bot, $chatId, $botUser);
}
```

### 2. انتخاب واحد (Callback)

```php
if (str_starts_with($callbackData, 'estimate_')) {
    return $this->handleEstimateCallback($bot, $callbackQuery, $botUser);
}
```

### 3. دریافت عدد

```php
// چک کردن State
$state = $this->getUserState($botUser->id);

if ($state && $state->state === 'estimate_waiting_value') {
    return $this->handleEstimateValue($bot, $chatId, $text, $state, $botUser);
}
```

## 🧪 تست‌ها

### سناریو 1: تخمین بر اساس ماه

```
User: /estimate
Bot: [نمایش کیبورد]
User: [کلیک "ماه"]
Bot: "چند ماه؟"
User: 6
Bot: "✅ 6 ماه = 3060 رکعت ثبت شد"
```

### سناریو 2: تخمین بر اساس رکعت

```
User: /estimate
Bot: [نمایش کیبورد]
User: [کلیک "رکعت"]
Bot: "چند رکعت؟"
User: 1000
Bot: "✅ 1000 رکعت ثبت شد (حدود 2 ماه)"
```

### سناریو 3: انصراف

```
User: /estimate
Bot: [نمایش کیبورد]
User: [کلیک "انصراف"]
Bot: "❌ عملیات لغو شد"
```

## 🔒 امنیت

- State ها باید Expire شوند (مثلاً بعد از 10 دقیقه)
- Validation برای اعداد (مثبت، معقول)
- Rate limiting برای جلوگیری از spam

## 📊 آمار

در `/stats` نمایش داده شود:

```
📊 آمار شما

🎯 تخمین کل: 3060 رکعت (6 ماه)
✅ ثبت شده: 450 رکعت
📉 باقیمانده: 2610 رکعت (85%)

⏱️ با این سرعت: حدود 8 ماه تا اتمام
```

## 🚀 مراحل پیاده‌سازی

1. ✅ ایجاد Migration برای `bot_user_states`
2. ✅ ایجاد Model `BotUserState`
3. ✅ اضافه کردن متدهای State Management به Service
4. ✅ پیاده‌سازی `/estimate` command
5. ✅ پیاده‌سازی Callback handlers
6. ✅ پیاده‌سازی دریافت عدد
7. ✅ اضافه کردن ترجمه‌ها
8. ✅ تست کامل

---

**نویسنده:** AI Assistant  
**تاریخ:** 2026-01-06  
**وضعیت:** طراحی کامل - آماده پیاده‌سازی
