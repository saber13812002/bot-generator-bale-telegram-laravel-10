---
name: بازطراحی الگوریتم Statistics
overview: بازطراحی کامل الگوریتم statistics با استفاده از bot_id به جای ترکیب type + language + endpoint، اعمال normalization برای language codes، و نمایش آمار برای هر bot_id جداگانه
todos:
  - id: refactor_calculate_statistics
    content: بازنویسی متد calculateStatistics() در BotMotherController برای استفاده از bot_id
    status: pending
  - id: add_normalize_language
    content: اضافه کردن متد normalizeLanguageCode() به BotMotherController
    status: pending
  - id: update_bot_filtering
    content: به‌روزرسانی منطق فیلتر کردن ربات‌های فعال بر اساس bot_id
    status: pending
  - id: update_quran_statistics
    content: به‌روزرسانی متدهای آمار در QuranBotUserRankingService برای استفاده از bot_id
    status: pending
  - id: test_statistics
    content: تست آمار با bot_id و normalization
    status: pending
---

# بازطراحی الگوریتم Statistics

## خلاصه تغییرات

الگوریتم statistics باید با تغییرات اخیر سیستم هماهنگ شود:

- استفاده از `bot_id` به جای ترکیب `type + language + endpoint`
- استفاده از `language_code` از جدول `bots` به جای `language` از `BotLog`
- اعمال normalization برای language codes (مثل `ar-IQ` -> `ar`)
- نمایش آمار برای هر `bot_id` جداگانه

## فایل‌های اصلی

### 1. `app/Http/Controllers/BotMotherController.php`

#### 1.1 بازنویسی متد `calculateStatistics()`

**تغییرات اصلی:**

- استفاده از `bot_id` برای فیلتر کردن لاگ‌ها
- استفاده از `language_code` از جدول `bots` به جای `language` از `BotLog`
- اعمال normalization برای language codes
- نمایش آمار برای هر bot_id جداگانه

**ساختار جدید:**

```php
private function calculateStatistics(int $botMotherId): array
{
    // 1. آمار کلی ربات مادر (بدون تغییر)
    
    // 2. لیست ربات‌های فعال
    $bots = Bot::where('bot_mother_id', $botMotherId)
        ->where(function($query) {
            // فقط ربات‌هایی که token دارند
        })
        ->get();
    
    // 3. برای هر ربات:
    foreach ($bots as $bot) {
        // استفاده از bot_id برای فیلتر کردن لاگ‌ها
        $baseQuery = BotLog::where('bot_id', $bot->id);
        
        // استفاده از language_code از جدول bots
        $languageCode = $bot->language_code;
        $normalizedLanguage = $this->normalizeLanguageCode($languageCode);
        
        // محاسبه آمار بر اساس bot_id
        // - کاربران یونیک
        // - تعاملات کل
        // - استارت‌ها
        // - آمار قرآنی (اگر endpoint مربوطه باشد)
    }
}
```

#### 1.2 اضافه کردن متد `normalizeLanguageCode()`

```php
private function normalizeLanguageCode(string $languageCode): string
{
    // تبدیل کدهای زبان مثل ar-IQ -> ar
    if (strpos($languageCode, '-') !== false) {
        return explode('-', $languageCode)[0];
    }
    return $languageCode;
}
```

#### 1.3 به‌روزرسانی منطق فیلتر کردن ربات‌های فعال

**قبل:**

- فیلتر بر اساس `type` و `webhook_endpoint_uri` از `BotLog`

**بعد:**

- فیلتر بر اساس وجود `bot_id` در `BotLog`
- بررسی اینکه ربات لاگ دارد: `BotLog::where('bot_id', $bot->id)->exists()`

### 2. `app/Services/QuranBotUserRankingServiceImpl.php`

#### 2.1 به‌روزرسانی متد `allUsersReportDailyWeeklyMonthly()`

**تغییرات:**

- استفاده از `bot_id` به جای `whereWebhookEndpointUri()`
- اگر `bot_id` در request موجود باشد، آمار را برای آن ربات خاص محاسبه کند
- اگر `bot_id` موجود نباشد، آمار کلی را محاسبه کند

**ساختار جدید:**

```php
public function allUsersReportDailyWeeklyMonthly($type = null, $botId = null)
{
    $baseQuery = BotLog::query();
    
    if ($botId) {
        // آمار برای ربات خاص
        $baseQuery->where('bot_id', $botId);
    } else {
        // آمار کلی برای همه ربات‌های قرآنی
        $baseQuery->where('webhook_endpoint_uri', 'webhook-quran-word');
    }
    
    // محاسبه آمار روزانه، هفتگی، ماهانه، سالانه
}
```

#### 2.2 به‌روزرسانی متد `getDailyStatistics()`

**تغییرات:**

- اضافه کردن پارامتر `$botId` (اختیاری)
- استفاده از `bot_id` برای فیلتر کردن اگر موجود باشد

### 3. Helper Methods

#### 3.1 ایجاد متد helper در `BotMotherController`

```php
/**
 * Normalize language code for statistics
 * 
 * @param string $languageCode
 * @return string
 */
private function normalizeLanguageCode(string $languageCode): string
{
    if (!$languageCode) {
        return 'fa'; // default
    }
    
    // تبدیل ar-IQ -> ar, de-DE -> de, etc.
    if (strpos($languageCode, '-') !== false) {
        return explode('-', $languageCode)[0];
    }
    
    return $languageCode;
}
```

## جزئیات پیاده‌سازی

### مرحله 1: بازنویسی `calculateStatistics()`

1. **آمار کلی ربات مادر** (بدون تغییر)

            - کل مشترکین
            - استارت در 1 هفته/ماه/سال قبل

2. **لیست ربات‌های فعال**

            - فیلتر بر اساس وجود `bot_id` در `BotLog`
            - استفاده از `language_code` از جدول `bots`

3. **برای هر ربات:**

            - استفاده از `bot_id` برای فیلتر کردن لاگ‌ها
            - محاسبه آمار بر اساس `bot_id`:
                    - کاربران یونیک کل
                    - استارت کل
                    - استارت (هفته/ماه گذشته)
                    - کاربران فعال (7 روز)
                    - کل تعاملات
                    - نرخ استفاده متوسط
            - آمار قرآنی (اگر `endpoint_id` مربوطه باشد):
                    - آیات خوانده شده

### مرحله 2: به‌روزرسانی `QuranBotUserRankingService`

1. اضافه کردن پارامتر `$botId` به متدهای آمار
2. استفاده از `bot_id` برای فیلتر کردن اگر موجود باشد
3. حفظ سازگاری با کد قدیمی (اگر `bot_id` موجود نباشد)

### مرحله 3: اعمال Normalization

1. ایجاد متد `normalizeLanguageCode()` در `BotMotherController`
2. استفاده از normalization هنگام نمایش زبان در آمار
3. استفاده از normalization هنگام فیلتر کردن لاگ‌ها (اگر نیاز باشد)

## نکات مهم

1. **سازگاری با لاگ‌های قدیمی:**

            - اگر `bot_id` در `BotLog` موجود نباشد، از روش قدیمی استفاده شود
            - بررسی `bot_id` قبل از استفاده: `whereNotNull('bot_id')`

2. **Performance:**

            - استفاده از index روی `bot_id` در جدول `bot_logs`
            - استفاده از cache برای آمار (همانند قبل)

3. **نمایش زبان:**

            - استفاده از `getLanguageDisplayName()` برای نمایش نام زبان
            - استفاده از `language_code` از جدول `bots` به جای `language` از `BotLog`

## تست

1. تست آمار کلی ربات مادر
2. تست آمار برای هر bot_id جداگانه
3. تست normalization برای language codes
4. تست سازگاری با لاگ‌های قدیمی (بدون bot_id)
5. تست performance با تعداد زیاد لاگ

## فایل‌های تغییر یافته

- `app/Http/Controllers/BotMotherController.php` - بازنویسی `calculateStatistics()`
- `app/Services/QuranBotUserRankingServiceImpl.php` - به‌روزرسانی متدهای آمار
- `app/Interfaces/Services/QuranBotUserRankingService.php` - به‌روزرسانی interface (اگر نیاز باشد)