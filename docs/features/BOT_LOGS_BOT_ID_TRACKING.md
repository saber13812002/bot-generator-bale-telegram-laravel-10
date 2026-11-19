# ردیابی bot_id و bot_mother_id در لاگ‌های ربات

## 📋 خلاصه

این فیچر امکان ردیابی کامل `bot_id` و `bot_mother_id` در تمام لاگ‌های سیستم را فراهم می‌کند. با این قابلیت، می‌توان آمار استفاده از ربات‌ها را به تفکیک هر ربات و ربات مادر محاسبه کرد.

**تاریخ پیاده‌سازی:** 2026-01-03  
**وضعیت:** ✅ تکمیل شده

## 🎯 اهداف

1. ذخیره `bot_id` و `bot_mother_id` در تمام لاگ‌های سیستم
2. استفاده از `bot_id` از request parameter در لاگ‌های جدید (اولویت اول)
3. امکان به‌روزرسانی لاگ‌های قدیمی با اطلاعات `bot_id` و `bot_mother_id`
4. ثبت لاگ webhook registration با اطلاعات کامل

## 📁 فایل‌های مرتبط

### Helper Classes
- `app/Helpers/LogHelper.php` - کلاس اصلی برای لاگینگ ربات‌ها
  - متد `log()` - ثبت لاگ با `bot_id` و `bot_mother_id`
  - متد `findBotIdFromToken()` - پیدا کردن `bot_id` از token (fallback)

### Commands
- `app/Console/Commands/UpdateBotLogsBotId.php` - Command برای به‌روزرسانی لاگ‌های قدیمی
  - استفاده: `php artisan bot-logs:update-bot-id`
  - گزینه‌ها:
    - `--dry-run` - فقط نمایش بدون به‌روزرسانی
    - `--batch-size=1000` - اندازه batch برای پردازش

### Models
- `app/Models/BotLog.php` - مدل لاگ‌های ربات
- `app/Models/Bot.php` - مدل ربات‌ها

### Controllers & Helpers (بهبود لاگینگ)
- `app/Helpers/BotHelper.php` - متد `defineBotInDbThenSetWebHook()` - لاگ webhook registration
- `app/Http/Controllers/BotMotherController.php` - لاگ webhook registration
- `app/Console/Commands/TestQuranBotWebhook.php` - لاگ webhook test
- `app/Console/Commands/ReRegisterAllBots.php` - لاگ re-registration

### Database
- `database/migrations/2023_04_13_140528_create_bot_logs_table.php`
  - فیلد `bot_id` (unsignedInteger, nullable)
  - فیلد `bot_mother_id` (unsignedBigInteger, nullable)

## 🔄 تغییرات انجام شده

### 1. بهبود LogHelper

**فایل:** `app/Helpers/LogHelper.php`

#### تغییرات در متد `log()`:

```php
// قبل از تغییر
$log->bot_mother_id = $request->input('bot_mother_id') ?? 0;
$botId = self::findBotIdFromToken($request, $type);
$log->bot_id = $botId;

// بعد از تغییر
// استفاده از bot_mother_id از request (اولویت اول)
$botMotherId = $request->input('bot_mother_id') ?? $request->query('bot_mother_id') ?? 0;
$log->bot_mother_id = $botMotherId;

// پیدا کردن bot_id: اول از request (اولویت اول)، سپس از token
$botId = $request->input('bot_id') ?? $request->query('bot_id');
if (!$botId) {
    $botId = self::findBotIdFromToken($request, $type);
}
$log->bot_id = $botId;
```

**مزایا:**
- اولویت با `bot_id` از request parameter (دقیق‌تر)
- پشتیبانی از query string و POST body
- Fallback به `findBotIdFromToken()` در صورت نبود `bot_id` در request

### 2. Command برای به‌روزرسانی لاگ‌های قدیمی

**فایل:** `app/Console/Commands/UpdateBotLogsBotId.php`

این Command برای به‌روزرسانی `bot_id` و `bot_mother_id` در لاگ‌های موجود ایجاد شده است.

#### روش‌های پیدا کردن bot_id:

1. **روش اول (دقیق‌ترین):** استفاده از `bot_mother_id`, `type`, `language` و `endpoint_id`
2. **روش دوم:** استفاده از `bot_mother_id`, `type` و `language`
3. **روش سوم:** استفاده از `bot_mother_id` و `type` (اگر فقط یک ربات وجود دارد)

#### استفاده:

```bash
# حالت dry-run (فقط نمایش)
php artisan bot-logs:update-bot-id --dry-run

# به‌روزرسانی واقعی
php artisan bot-logs:update-bot-id

# با تنظیم batch size برای عملکرد بهتر
php artisan bot-logs:update-bot-id --batch-size=1000
```

**نکته:** به دلیل تعداد زیاد لاگ‌ها (188754+)، اجرای Command ممکن است زمان‌بر باشد.

### 3. بهبود لاگ‌های Webhook Registration

لاگ‌های webhook registration در مکان‌های زیر بهبود یافته‌اند:

#### BotHelper::defineBotInDbThenSetWebHook()
```php
Log::info('✅ BotHelper - Webhook verified successfully', [
    'bot_id' => $botItem->id,
    'bot_mother_id' => $botMotherId,
    'type' => $type,
    'language' => $language,
    // ...
]);
```

#### BotMotherController::handleTokenInput()
```php
Log::info('Bot webhook registered via Bot Mother', [
    'bot_id' => $botItem->id,
    'bot_mother_id' => $botMotherId,
    'type' => $botType,
    'language' => $language,
    'endpoint_id' => $endpointId,
    'webhook_url' => $webhookUrl,
]);
```

#### TestQuranBotWebhook & ReRegisterAllBots
لاگ‌ها شامل `bot_id` و `bot_mother_id` شده‌اند.

## 📊 ساختار دیتابیس

### جدول bot_logs

فیلدهای مرتبط با این فیچر:

```sql
bot_id INT(10) UNSIGNED NULL
bot_mother_id BIGINT(20) UNSIGNED NULL
```

این فیلدها از قبل در جدول وجود داشتند و فقط منطق پر کردن آن‌ها بهبود یافته است.

## 🔍 نحوه استفاده

### برای لاگ‌های جدید

هیچ کار اضافی لازم نیست! سیستم به صورت خودکار:
- `bot_id` را از request parameter می‌خواند
- `bot_mother_id` را از request parameter می‌خواند
- در صورت نبود، از token استفاده می‌کند (fallback)

### برای به‌روزرسانی لاگ‌های قدیمی

```bash
# ابتدا با dry-run تست کنید
php artisan bot-logs:update-bot-id --dry-run

# سپس به‌روزرسانی واقعی را انجام دهید
php artisan bot-logs:update-bot-id --batch-size=1000
```

## 📈 مزایا

1. **آمار دقیق‌تر:** امکان محاسبه آمار استفاده بر اساس `bot_id` و `bot_mother_id`
2. **ردیابی بهتر:** ردیابی تمام فعالیت‌های هر ربات به صورت جداگانه
3. **تحلیل آسان‌تر:** امکان فیلتر و تحلیل لاگ‌ها بر اساس ربات خاص
4. **سازگاری با گذشته:** امکان به‌روزرسانی لاگ‌های قدیمی

## 🔮 استفاده‌های آینده

با این اطلاعات می‌توان:
- آمار استفاده روزانه هر ربات را محاسبه کرد
- تعداد کاربران منحصر به فرد هر ربات را محاسبه کرد
- آمار دستورات استفاده شده در هر ربات را محاسبه کرد
- آمار بر اساس ربات مادر (bot_mother_id) را محاسبه کرد

## 🧪 تست

برای تست این فیچر:

1. یک webhook به یک ربات ارسال کنید
2. لاگ را در `storage/logs/laravel.log` بررسی کنید
3. در جدول `bot_logs` بررسی کنید که `bot_id` و `bot_mother_id` پر شده‌اند

## ⚠️ نکات مهم

1. **اولویت با request parameter:** سیستم اول از `bot_id` در request استفاده می‌کند، سپس از token
2. **به‌روزرسانی لاگ‌های قدیمی:** Command ممکن است زمان‌بر باشد (بسته به تعداد لاگ‌ها)
3. **Fallback:** در صورت نبود `bot_id` در request، از `findBotIdFromToken()` استفاده می‌شود

## 🔗 لینک‌های مرتبط

- [README اصلی](../../README.md)
- [راهنمای لاگینگ](../LOGGING-GUIDE.md)
- [مستندات Bot Model](../../app/Models/Bot.php)
- [مستندات BotLog Model](../../app/Models/BotLog.php)

---

**آخرین بروزرسانی:** 2026-01-03
