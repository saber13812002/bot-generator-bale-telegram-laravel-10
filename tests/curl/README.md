# راهنمای استفاده از تست‌های Webhook

این دایرکتوری شامل ابزارهای تست و توسعه webhook‌های ربات است که بر اساس تست‌های واقعی از Insomnia ایجاد شده‌اند.

## فایل‌ها

- `webhook-tests.sh` - فایل bash شامل curl commands برای تست webhook‌ها
- `sample-webhooks.json` - نمونه‌های JSON update برای استفاده در curl یا تست‌ها

## پیش‌نیازها

- Laravel application در حال اجرا روی `http://localhost:8000`
- دسترسی به terminal/bash
- curl نصب شده

## نحوه استفاده

### 1. تست با curl (دستورات آماده)

#### اجرای تمام تست‌ها:

```bash
# تنظیم متغیرهای محیطی (اختیاری)
export BOT_TOKEN="your_token_here"
export BOT_MOTHER_ID=1
export LANGUAGE=fa

# اجرای فایل تست
bash tests/curl/webhook-tests.sh
```

#### تست یک webhook خاص:

```bash
# تست webhook با پیام "salam"
curl -X POST "http://localhost:8000/api/webhook-bot-mother?origin=bale&token=YOUR_TOKEN&bot_mother_id=1&language=fa" \
  -H "Content-Type: application/json" \
  -d @tests/curl/sample-webhooks.json
```

### 2. استفاده از فایل JSON نمونه

فایل `sample-webhooks.json` شامل نمونه‌های مختلف update است:

```json
{
  "salam": { ... },
  "start": { ... },
  "new_bot": { ... },
  "send_token": { ... },
  "callback_query": { ... }
}
```

برای استفاده:

```bash
# استفاده از یک نمونه خاص
curl -X POST "http://localhost:8000/api/webhook-bot-mother?origin=bale&token=YOUR_TOKEN&bot_mother_id=1&language=fa" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 1,
    "message": {
      "text": "salam",
      ...
    }
  }'
```

### 3. تست با Laravel Feature Tests

```bash
# اجرای تمام تست‌های webhook
php artisan test --filter WebhookTest

# اجرای یک تست خاص
php artisan test --filter test_webhook_salam_message
```

### 4. استفاده در کد PHP

#### استفاده از WebhookMockHelper:

```php
use App\Helpers\WebhookMockHelper;

// ساختار update برای پیام متنی
$update = WebhookMockHelper::mockBaleUpdate('salam');

// ساختار update برای دستور /start
$update = WebhookMockHelper::mockStartCommand();

// ساختار callback query (کلیک روی دکمه)
$update = WebhookMockHelper::mockCallbackQuery('/1');

// ساختار update برای ارسال token
$update = WebhookMockHelper::mockTokenUpdate('737102910:...');
```

#### استفاده از WebhookDevHelper:

```php
use App\Helpers\WebhookDevHelper;

// شبیه‌سازی webhook بدون نیاز به curl
$response = WebhookDevHelper::simulateWebhook(
    '/api/webhook-bot-mother',
    $update,
    [
        'origin' => 'bale',
        'token' => 'test_token',
        'bot_mother_id' => 1,
        'language' => 'fa'
    ]
);

// تست سریع
$response = WebhookDevHelper::quickTest(
    '/api/webhook-bot-mother',
    'salam',
    ['origin' => 'bale', 'token' => 'test_token']
);

// تست callback query
$response = WebhookDevHelper::testCallbackQuery(
    '/api/webhook-quran-word',
    '/1',
    ['origin' => 'bale', 'token' => 'test_token']
);
```

## لیست Webhook‌های قابل تست

### ربات مادر (Bot Mother)
- `/api/webhook-bot-mother` - دریافت پیام‌های ربات مادر
- `/api/webhook-bot-get-id` - دریافت شناسه ربات
- `/api/webhook-bot-children` - دریافت پیام‌های ربات‌های فرزند

### ربات قرآن
- `/api/webhook-quran-ayat` - ربات قرآن (آیه به آیه)
- `/api/webhook-quran-word` - ربات قرآن (کلمه به کلمه)

### ربات‌های دیگر
- `/api/webhook-weather` - ربات هواشناسی
- `/api/webhook-hadith` - ربات حدیث
- `/api/webhook-nahj` - ربات نهج البلاغه
- `/api/webhook-blog` - ربات بلاگ

## Query Parameters مورد نیاز

تمام webhook‌ها نیاز به پارامترهای زیر دارند:

- `origin` (required) - نوع پیام‌رسان: `bale`, `telegram`, `gap`, `soroosh`
- `token` (required) - توکن ربات
- `bot_mother_id` (required) - شناسه ربات مادر
- `language` (optional) - زبان: `fa`, `en`, `ar-IQ`, و غیره

## نمونه‌های تست

### تست 1: پیام ساده

```bash
curl -X POST "http://localhost:8000/api/webhook-bot-mother?origin=bale&token=YOUR_TOKEN&bot_mother_id=1&language=fa" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 1,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "salam"
    }
  }'
```

### تست 2: Callback Query (کلیک روی دکمه)

```bash
curl -X POST "http://localhost:8000/api/webhook-quran-word?origin=bale&token=YOUR_TOKEN&bot_mother_id=1&language=fa" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 6,
    "callback_query": {
      "id": "cq_123456789",
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "message": {
        "message_id": -1515335176,
        "chat": {
          "id": 485750575,
          "type": "private"
        }
      },
      "data": "/1"
    }
  }'
```

## نکات مهم

1. **Token ها**: باید در `.env` تنظیم شوند یا به صورت query parameter ارسال شوند
2. **Chat ID**: از تست‌های واقعی Insomnia استفاده شده (485750575)
3. **Development Mode**: در حالت `local`، لاگ‌ها به صورت خودکار نمایش داده می‌شوند
4. **Validation**: تمام webhook‌ها validation دارند و باید پارامترهای مورد نیاز ارسال شوند

## عیب‌یابی

### مشکل: 422 Unprocessable Entity
- بررسی کنید که تمام پارامترهای required ارسال شده‌اند
- بررسی کنید که `origin` یکی از مقادیر مجاز باشد

### مشکل: 500 Internal Server Error
- لاگ‌های Laravel را بررسی کنید
- بررسی کنید که دیتابیس و سرویس‌های مورد نیاز در حال اجرا هستند

### مشکل: Timeout
- بررسی کنید که Laravel application در حال اجرا است
- بررسی کنید که URL صحیح است (`http://localhost:8000`)

## منابع بیشتر

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Bale Bot API](https://dev.bale.ai)


