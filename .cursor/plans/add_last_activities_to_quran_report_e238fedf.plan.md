---
name: Add Last Activities to Quran Report
overview: افزودن بخش آخرین فعالیت‌های کاربر به گزارش روزانه قرآن. نمایش آخرین 3 دستور آیه که کاربر ارسال کرده و نمایش آخرین آیه با پیام "ادامه بده".
todos: []
---

#افزودن آخرین فعالیت‌های کاربر به گزارش قرآن

## هدف

افزودن بخش "آخرین فعالیت‌ها" به گزارش روزانه که شامل:

1. آخرین 3 دستور آیه که کاربر ارسال کرده (مثل `/sure76ayah2`)
2. نمایش آنها با فرمت خوانا (سوره 76، آیه 2)
3. نمایش آخرین آیه با پیام "ادامه بده" و دستور آیه بعدی

## تغییرات مورد نیاز

### 1. اضافه کردن متد برای گرفتن آخرین فعالیت‌ها

در `app/Services/QuranBotUserRankingServiceImpl.php`:

- متد جدید `getLastVerseActivities($chatId)` که:
- از جدول `bot_logs` آخرین 3 رکورد را می‌گیرد
- فیلتر: `chat_id`, `webhook_endpoint_uri = 'webhook-quran-word'`, `is_command = true`
- فیلتر: `text` باید شامل regex `/\/sure[0-9]+ayah[0-9]+/` باشد
- مرتب‌سازی بر اساس `created_at DESC`
- برای هر رکورد از `StringHelper::getSureAyeByRegex()` استفاده می‌کند

### 2. اضافه کردن متد برای فرمت کردن فعالیت‌ها

- متد `formatActivity($text)` که:
- دستور را parse می‌کند (مثل `/sure76ayah2`)
- فرمت خوانا برمی‌گرداند (مثل "سوره 76، آیه 2")
- اگر parse نشد، دستور اصلی را برمی‌گرداند

### 3. اضافه کردن متد برای محاسبه آیه بعدی

- متد `getNextAyahCommand($sure, $ayah)` که:
- آیه بعدی را محاسبه می‌کند
- اگر آخرین آیه سوره بود، سوره بعدی را برمی‌گرداند
- دستور کامل را برمی‌گرداند (مثل `/sure76ayah3`)

### 4. تغییر متد `userStatisticPerDayReport`

در `app/Services/QuranBotUserRankingServiceImpl.php`:

- قبل از بخش حدیث، بخش "آخرین فعالیت‌ها" را اضافه کنیم:
  ```javascript
      📚 آخرین فعالیت‌های شما:
      
      1️⃣ سوره 76، آیه 2 (/sure76ayah2)
      2️⃣ سوره 75، آیه 5 (/sure75ayah5)
      3️⃣ سوره 74، آیه 8 (/sure74ayah8)
  ```




- بعد از بخش حدیث، بخش "ادامه بده" را اضافه کنیم:
  ```javascript
      📖 آخرین آیه‌ای که می‌خوندی: سوره 76، آیه 2
      ادامه بده: /sure76ayah3
  ```




## فایل‌های مورد تغییر

1. `app/Services/QuranBotUserRankingServiceImpl.php`

- اضافه کردن متد `getLastVerseActivities($chatId)`
- اضافه کردن متد `formatActivity($text)`
- اضافه کردن متد `getNextAyahCommand($sure, $ayah)`
- تغییر متد `userStatisticPerDayReport($chatId, $rank)`

## منطق پیاده‌سازی

### گرفتن آخرین فعالیت‌ها

```php
$lastActivities = BotLog::whereChatId($chatId)
    ->whereWebhookEndpointUri('webhook-quran-word')
    ->where('is_command', true)
    ->whereRaw("text REGEXP ?", ['/\/sure[0-9]+ayah[0-9]+/'])
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get(['text', 'created_at']);
```



### Parse کردن دستور

- استفاده از `StringHelper::getSureAyeByRegex($text)` که `[$sure, $ayah]` برمی‌گرداند

### محاسبه آیه بعدی

- اگر `$ayah < maxAyah` → آیه بعدی همان سوره
- اگر `$ayah == maxAyah` → سوره بعدی، آیه 1
- استفاده از `QuranHelper::getLastAyeBySurehId($sure)` برای گرفتن آخرین آیه سوره

## نکات مهم

1. اگر کاربر کمتر از 3 فعالیت داشته باشد، فقط همان تعداد نمایش داده می‌شود
2. اگر آخرین فعالیت parse نشد، فقط دستور نمایش داده می‌شود