---
name: ایجاد ابزارهای تست و توسعه webhook
overview: ""
todos:
  - id: aff203c6-7cc8-4a1a-8398-2a540bac77f0
    content: استخراج تست‌های واقعی از فایل Insomnia و تبدیل به curl commands
    status: pending
  - id: 637a9586-f542-4a69-a7e7-825cdd83bd8e
    content: ایجاد فایل sample-webhooks.json با نمونه‌های واقعی از Insomnia
    status: pending
  - id: f3ecc1d2-877a-49b5-8079-c011892e0be5
    content: ایجاد WebhookMockHelper بر اساس ساختار واقعی update های Insomnia
    status: pending
  - id: bcaf2c49-03f8-43cf-a632-23c06238ba04
    content: ایجاد WebhookDevHelper برای شبیه‌سازی و لاگ کردن
    status: pending
  - id: 35bcf231-6004-4d09-8bcc-3ec2ce76dd2d
    content: نوشتن تست‌های Laravel Feature با داده‌های واقعی
    status: pending
  - id: 58d230d7-0414-46ca-8ca8-bae74994ed35
    content: ایجاد README برای راهنمای استفاده
    status: pending
isProject: false
---

# ایجاد ابزارهای تست و توسعه webhook

## اهداف

1. ایجاد فایل‌های curl برای تست سریع webhook‌ها در حالت لوکال
2. نوشتن تست‌های Laravel Feature برای webhook‌ها
3. ایجاد Mock Helper برای شبیه‌سازی دکمه‌ها و callback query
4. ایجاد Helper برای آسان‌تر کردن توسعه webhook‌ها

## فایل‌های ایجاد شده

### 1. فایل curl برای تست webhook‌ها

**مسیر**: `tests/curl/webhook-tests.sh`

این فایل شامل curl commands برای تست webhook‌های مختلف:

- `/api/webhook-weather` - تست ربات هواشناسی
- `/api/webhook-quran-ayat` - تست ربات قرآن
- `/api/webhook-quran-word` - تست ربات کلمه به کلمه قرآن
- `/api/webhook-hadith` - تست ربات حدیث
- `/api/webhook-nahj` - تست ربات نهج البلاغه
- `/api/webhook-bot-mother` - تست ربات مادر
- `/api/webhook-blog` - تست ربات بلاگ

هر curl شامل:

- ساختار کامل update message از Telegram/Bale
- Query parameters مورد نیاز (origin, token, language, bot_mother_id)
- مثال‌های مختلف برای پیام‌های متنی و callback query

### 2. Mock Helper برای شبیه‌سازی

**مسیر**: `app/Helpers/WebhookMockHelper.php`

این Helper شامل متدهای:

- `mockTelegramUpdate($text, $chatId, $userId)` - ساختار update برای پیام متنی
- `mockTelegramCallbackQuery($callbackData, $chatId, $messageId)` - ساختار callback query
- `mockBaleUpdate($text, $chatId, $userId)` - ساختار update برای Bale
- `mockInlineKeyboardClick($callbackData)` - شبیه‌سازی کلیک روی دکمه
- `mockButtonMessage($buttonText, $callbackData)` - ساختار پیام با دکمه

### 3. تست‌های Laravel Feature

**مسیر**: `tests/Feature/WebhookTest.php`

تست‌های سریع برای:

- تست webhook‌های مختلف با داده‌های mock
- تست پاسخ‌های صحیح
- تست validation
- تست callback query handling

### 4. Helper برای توسعه

**مسیر**: `app/Helpers/WebhookDevHelper.php`

Helper برای آسان‌تر کردن توسعه:

- `simulateWebhook($endpoint, $updateData, $queryParams)` - شبیه‌سازی webhook بدون نیاز به curl
- `createTestUpdate($text, $type)` - ساختار update برای تست
- `createCallbackQueryUpdate($callbackData)` - ساختار callback query برای تست
- `logWebhookRequest($request, $response)` - لاگ کردن درخواست‌ها در حالت توسعه

### 5. فایل نمونه برای تست دستی

**مسیر**: `tests/curl/sample-webhook.json`

فایل JSON شامل نمونه‌های مختلف update برای استفاده در curl یا تست‌ها

## ساختار فایل‌ها

```
tests/
├── curl/
│   ├── webhook-tests.sh          # فایل curl برای تست webhook‌ها
│   └── sample-webhook.json       # نمونه‌های JSON update
├── Feature/
│   └── WebhookTest.php           # تست‌های Feature برای webhook‌ها

app/Helpers/
├── WebhookMockHelper.php         # Helper برای mock کردن update‌ها
└── WebhookDevHelper.php          # Helper برای توسعه آسان‌تر
```

## نحوه استفاده

### تست با curl:

```bash
# اجرای فایل curl
bash tests/curl/webhook-tests.sh

# یا تست یک webhook خاص
curl -X POST http://localhost:8000/api/webhook-weather?origin=bale&token=YOUR_TOKEN \
  -H "Content-Type: application/json" \
  -d @tests/curl/sample-webhook.json
```

### تست با Laravel:

```bash
php artisan test --filter WebhookTest
```

### استفاده در کد:

```php
use App\Helpers\WebhookMockHelper;
use App\Helpers\WebhookDevHelper;

// ساختار update برای تست
$update = WebhookMockHelper::mockTelegramUpdate('/start', 123456, 789012);

// شبیه‌سازی webhook
$response = WebhookDevHelper::simulateWebhook(
    '/api/webhook-weather',
    $update,
    ['origin' => 'bale', 'token' => 'test_token']
);
```

## نکات مهم

- تمام curl commands از localhost:8000 استفاده می‌کنند (قابل تغییر)
- Token ها باید در `.env` یا به صورت query parameter ارسال شوند
- در حالت development، لاگ‌ها در console نمایش داده می‌شوند
- Mock Helper ها ساختار کامل Telegram/Bale update را می‌سازند