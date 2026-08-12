# ربات ارتباط با نماینده مجلس (mp-contact)

ربات تلگرام/بله برای دریافت پیام‌های مردمی (تیکتینگ)، نظرسنجی موافق/مخالف با نظر توصیفی، و مدیریت کامل از داخل چت با **دکمه‌های شیشه‌ای (Inline Keyboard)**.

## Endpoint

| فیلد | مقدار |
|------|--------|
| `endpoint_id` | `webhook-mp-contact` |
| Route | `POST /api/webhook-mp-contact` |
| Seeder | `MpContactWebhookEndpointSeeder` |

Query پارامترها مثل سایر ربات‌ها: `origin`, `token`, (اختیاری) `bot_id`.

## نقش‌ها

- **کاربر عادی:** ارسال تیکت، پیگیری با کد، رأی و نظر در نظرسنجی‌ها، دستور `/admin_kiye`
- **ادمین محلی:** جدول `mp_contact_admins`؛ owner ربات به‌عنوان ادمین اصلی sync می‌شود
- **`/admin_kiye`:** درخواست ادمین دوم/سوم محلی (جدا از `/adminkie` سراسری پلتفرم)

## منوی کاربر

1. ارسال پیام به نماینده → کد پیگیری ۸ کاراکتری
2. پیگیری وضعیت تیکت (فقط تیکت‌های همان chat)
3. نظرسنجی‌های فعال → موافق/مخالف (یک‌بار) + ثبت نظر توصیفی + کد رسید

## منوی ادمین

1. پیام‌های دریافتی — صفحه‌بندی ۱۰تایی + تغییر وضعیت `pending` / `reviewing` / `closed`
2. مدیریت نظرسنجی‌ها — ایجاد، ویرایش، فعال/غیرفعال، نتایج، صفحه‌بندی ۵تایی
3. مدیریت ادمین‌ها — فوروارد پیام خصوصی برای افزودن/حذف + تأیید/رد درخواست‌های `/admin_kiye`

## جداول

- `mp_contact_admins`
- `mp_contact_admin_requests`
- `mp_contact_tickets`
- `mp_contact_polls`
- `mp_contact_poll_votes`
- `mp_contact_poll_comments`

بدون Foreign Key به جدول `bots` (فقط index).

## فایل‌های کلیدی

- `app/Http/Controllers/MpContactBotController.php`
- `app/Services/MpContactBotServiceImpl.php`
- `app/Interfaces/Services/MpContactBotService.php`

## راه‌اندازی

```bash
php artisan migrate
php artisan db:seed --class=MpContactWebhookEndpointSeeder
```

سپس یک ردیف در `bots` با `endpoint_id = webhook-mp-contact` و توکن بسازید و webhook را ست کنید.
