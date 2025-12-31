---
name: Improve Zero Activity Messages and Suggest Random Verse
overview: بهبود پیام‌های گزارش زمانی که امروز و دیروز هر دو صفر هستند و پیشنهاد آیه رندوم از فعالیت‌های امروز دیگر کاربران
todos: []
---

# بهبود پیام‌های صفر و پیشنهاد آیه رندوم

## هدف

بهبود تجربه کاربری در گزارش روزانه زمانی که:

1. امروز و دیروز هر دو صفر هستند
2. کاربر هیچ لاگ فعالیتی ندارد

## تغییرات مورد نیاز

### 1. اضافه کردن کلیدهای ترجمه جدید

در همه فایل‌های `lang/{locale}/bot.php`:

- `'you had no reading today and yesterday'` - شما امروز و دیروز هیچ مطالعه‌ای نداشتید
- `'suggested verse from other users today'` - آیه پیشنهادی از فعالیت‌های امروز دیگر کاربران
- `'start from here'` - از اینجا شروع کنید

### 2. ایجاد متد جدید در `QuranHelper`

متد `getRandomVerseFromTodayActivities()`:

- دریافت یک آیه رندوم از فعالیت‌های امروز دیگر کاربران
- فیلتر کردن لاگ‌های امروز که با الگوی `/sure[0-9]+ayah[0-9]+/` مطابقت دارند
- حذف لاگ‌های کاربر فعلی
- انتخاب رندوم یکی از آن‌ها
- برگرداندن command (مثل `/sure2ayah3`)

### 3. تغییر `QuranBotUserRankingServiceImpl.php`

در متد `userStatisticPerDayReport`:

- بررسی اگر `$count_today == 0 && $count_yesterday == 0`:
- نمایش پیام "شما امروز و دیروز هیچ مطالعه‌ای نداشتید"
- بررسی وجود لاگ برای کاربر:
    - اگر لاگ دارد: استفاده از آخرین آیه خوانده شده
    - اگر لاگ ندارد: استفاده از آیه رندوم از فعالیت‌های امروز دیگر کاربران
- پیشنهاد شروع از آیه با پیام "از اینجا شروع کنید"

### 4. منطق پیشنهاد آیه

```javascript
if (count_today == 0 && count_yesterday == 0):
    نمایش پیام "شما امروز و دیروز هیچ مطالعه‌ای نداشتید"
    
    lastActivities = getLastVerseActivities(chatId)
    if (lastActivities.count() > 0):
        // استفاده از آخرین آیه خوانده شده
        lastActivity = lastActivities.first()
        nextCommand = getNextAyahCommand(lastActivity)
        نمایش "از اینجا شروع کنید: {nextCommand}"
    else:
        // استفاده از آیه رندوم از دیگر کاربران
        randomVerse = getRandomVerseFromTodayActivities(chatId)
        if (randomVerse):
            نمایش "آیه پیشنهادی از فعالیت‌های امروز دیگر کاربران: {randomVerse}"
            نمایش "از اینجا شروع کنید: {randomVerse}"
        else:
            // اگر هیچ فعالیتی از دیگر کاربران نبود، پیشنهاد آیه اول
            نمایش "از اینجا شروع کنید: /sure1ayah1"
```



## فایل‌های مورد تغییر

1. `app/Helpers/QuranHelper.php`

- متد جدید `getRandomVerseFromTodayActivities(string $excludeChatId): ?string`

2. `app/Services/QuranBotUserRankingServiceImpl.php`

- تغییر منطق در متد `userStatisticPerDayReport` برای حالت صفر بودن هر دو

3. همه فایل‌های `lang/{locale}/bot.php` (15 زبان)

- اضافه کردن کلیدهای ترجمه جدید

## مثال خروجی

### حالت 1: کاربر لاگ دارد

```javascript
📊 گزارش فعالیت شما

🏆 رتبه شما در 30 روز گذشته است: 99

📖 استفاده امروزی شما از این ربات: 0 آیه
💬 شما امروز و دیروز هیچ مطالعه‌ای نداشتید

📖 آخرین آیه‌ای که می‌خوندی: سوره 76، آیه 2
از اینجا شروع کنید: /sure76ayah3
```



### حالت 2: کاربر لاگ ندارد، اما دیگر کاربران فعالیت دارند

```javascript
📊 گزارش فعالیت شما

🏆 رتبه شما در 30 روز گذشته است: 99

📖 استفاده امروزی شما از این ربات: 0 آیه
💬 شما امروز و دیروز هیچ مطالعه‌ای نداشتید

💡 آیه پیشنهادی از فعالیت‌های امروز دیگر کاربران: /sure2ayah15
از اینجا شروع کنید: /sure2ayah15
```



### حالت 3: هیچ کاربری فعالیت ندارد

```javascript
📊 گزارش فعالیت شما

🏆 رتبه شما در 30 روز گذشته است: 99

📖 استفاده امروزی شما از این ربات: 0 آیه
💬 شما امروز و دیروز هیچ مطالعه‌ای نداشتید

از اینجا شروع کنید: /sure1ayah1
```



## نکات مهم

1. بررسی دقیق شرط `count_today == 0 && count_yesterday == 0`
2. متد `getRandomVerseFromTodayActivities` باید فقط از فعالیت‌های امروز استفاده کند