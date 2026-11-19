---
name: رفع مشکلات دستور قرآن بات و استاتیستیکس
overview: "رفع سه مشکل اصلی: 1) دستور `/quran_bots` فقط برای فارسی و انگلیسی کار می‌کند (خطای syntax در فایل‌های ترجمه)، 2) دستور `/quran_bots` با دیتابیس کار نمی‌کند (مشکل در `getBotsFromDatabase` به خاطر `bot_id` همیشه 1 در `BotLog`)، 3) استاتیستیکس کار نمی‌کند (همان مشکل دیتابیس)."
todos: []
---

# رفع مشکلات دستور قرآن بات و استاتیستیکس

## مشکلات شناسایی شده

1. **خطای syntax در فایل‌های ترجمه**: خطای `ParseError` در `lang/az/bot.php:169` باعث می‌شود ترجمه لود نشود و فقط فارسی و انگلیسی کار کند.
2. **مشکل `bot_id` در `BotLog`**: در `LogHelper::log` خط 32، `bot_id` همیشه `1` ست می‌شود که باعث می‌شود `getBotsFromDatabase` نتواند ربات‌های قرآن واقعی را پیدا کند.
3. **مشکل در `getBotsFromDatabase`**: این متد به `bot_id` در `BotLog` وابسته است، اما چون همه لاگ‌ها `bot_id = 1` دارند، نمی‌تواند ربات‌های قرآن را پیدا کند.
4. **مشکل در statistics**: همان مشکل `getBotsFromDatabase` است.

## راه حل‌ها

### 1. رفع مشکل `bot_id` در `LogHelper`

**فایل**: `app/Helpers/LogHelper.php`

- تغییر `LogHelper::log` تا `bot_id` را از `token` و `type` پیدا کند
- استفاده از جدول `bots` برای پیدا کردن `bot_id` بر اساس `token` و `type`
- اگر `bot_id` پیدا نشد، از `1` استفاده کند (برای Bot Mother)

### 2. بازنویسی `getBotsFromDatabase`

**فایل**: `app/Services/QuranBotsIntroductionService.php`

- تغییر روش جستجو: به جای استفاده از `bot_id` در `BotLog`، مستقیماً از جدول `bots` استفاده کند
- فیلتر کردن ربات‌های قرآن بر اساس:
- `bot_mother_id`
- `type` (telegram/bale)
- وجود `token` و `bot_name`
- وجود لاگ در `BotLog` با `webhook_endpoint_uri = 'webhook-quran-word'`
- دریافت `language` از `BotLog` برای هر ربات

### 3. رفع مشکل syntax در فایل‌های ترجمه

**فایل**: `lang/az/bot.php` و سایر فایل‌های ترجمه

- بررسی خط 169 در `lang/az/bot.php`
- بررسی سایر فایل‌های ترجمه برای خطاهای مشابه
- رفع خطاهای syntax

### 4. بهبود error handling در `buildMessage`

**فایل**: `app/Services/QuranBotsIntroductionService.php`

- اطمینان از اینکه `try-catch` همه `trans()` calls را پوشش می‌دهد
- استفاده از fallback برای ترجمه‌های ناموفق

## فایل‌های تغییر یافته

1. `app/Helpers/LogHelper.php` - رفع `bot_id`
2. `app/Services/QuranBotsIntroductionService.php` - بازنویسی `getBotsFromDatabase`
3. `lang/az/bot.php` - رفع syntax error (اگر وجود داشته باشد)
4. سایر فایل‌های ترجمه (در صورت نیاز)

## تست‌ها

1. تست دستور `/quran_bots` برای همه زبان‌ها
2. تست دستور `/quran_bots` با `database_only`
3. تست دستور `/quran_bots` با `both`
4. تست دستور `/statistics`
5. بررسی لاگ‌ها برای اطمینان از ذخیره صحیح `bot_id`