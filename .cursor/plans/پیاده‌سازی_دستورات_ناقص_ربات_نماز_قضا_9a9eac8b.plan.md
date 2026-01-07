---
name: پیاده‌سازی دستورات ناقص ربات نماز قضا
overview: "پیاده‌سازی دستورات ناقص که در Help ذکر شده‌اند اما در Controller تعریف نشده‌اند: `/estimate_status`, `/set_email`, `/email_settings` و سیستم کامل تایید ایمیل با state management."
todos:
  - id: estimate_status
    content: پیاده‌سازی دستور /estimate_status برای نمایش تخمین فعلی
    status: pending
  - id: set_email_alias
    content: اضافه کردن /set_email به عنوان alias برای /email
    status: pending
  - id: email_settings_display
    content: پیاده‌سازی کامل /email_settings برای نمایش تنظیمات فعلی
    status: pending
  - id: email_state_management
    content: پیاده‌سازی state management برای دریافت ایمیل (email_waiting_address)
    status: pending
    dependencies:
      - set_email_alias
  - id: email_verification_code
    content: ایجاد Mailable و ارسال کد تایید 6 رقمی به ایمیل
    status: pending
    dependencies:
      - email_state_management
  - id: email_code_verification
    content: پیاده‌سازی تایید کد و فعال‌سازی گزارش (email_waiting_code state)
    status: pending
    dependencies:
      - email_verification_code
  - id: email_translations
    content: اضافه کردن ترجمه‌های فارسی و انگلیسی برای تمام پیام‌های جدید
    status: pending
---

# پیاده‌سازی دستورات ناقص ربات نماز قضا

## مشکل

در Help دستورات، چند دستور ذکر شده که در Controller پیاده‌سازی نشده‌اند:

- `/estimate_status` - مشاهده تخمین فعلی
- `/set_email` - تنظیم ایمیل (فقط `/email` وجود دارد)
- `/email_settings` - تنظیمات ایمیل (فقط "coming soon" نمایش می‌دهد)
- سیستم تایید ایمیل (state management برای دریافت ایمیل و کد تایید)

## راه‌حل

### 1. پیاده‌سازی `/estimate_status`

**فایل:** `app/Http/Controllers/PrayerBotController.php`

- اضافه کردن handler برای `/estimate_status`
- دریافت تخمین فعلی از Service
- نمایش تخمین با فرمت مناسب (رکعات + معادل)
- اگر تخمینی وجود نداشت، پیام مناسب نمایش دهد
```php
// در handleTextMessage:
if ($text === '/estimate_status' || $text === 'وضعیت تخمین') {
    $this->handleEstimateStatus($bot, $chatId, $type);
    return;
}
```


### 2. پیاده‌سازی `/set_email` (به عنوان alias)

**فایل:** `app/Http/Controllers/PrayerBotController.php`

- اضافه کردن handler برای `/set_email` که همان `handleEmailCommand` را صدا بزند
- یا تغییر `handleEmailCommand` برای پشتیبانی از هر دو دستور
```php
// در handleTextMessage:
if ($text === '/set_email' || $text === '/email' || $text === 'ایمیل') {
    $botUser = BotUsers::firstOrNew($chatId, $botMotherId, $type);
    $this->handleEmailCommand($bot, $chatId, $botUser, $type, $botMotherId);
    return;
}
```


### 3. پیاده‌سازی کامل `/email_settings`

**فایل:** `app/Http/Controllers/PrayerBotController.php`

- نمایش تنظیمات فعلی کاربر (ایمیل، فرکانس، وضعیت تایید)
- کیبورد برای تغییر فرکانس (daily/weekly/monthly/never)
- امکان تغییر ایمیل
- امکان لغو اشتراک
```php
protected function handleEmailSettings($bot, int $chatId, $botUser, string $type): void
{
    // نمایش تنظیمات فعلی
    // کیبورد برای تغییرات
}
```


### 4. سیستم تایید ایمیل با State Management

**فایل:** `app/Http/Controllers/PrayerBotController.php`

- State: `email_waiting_address` - منتظر دریافت ایمیل
- State: `email_waiting_code` - منتظر دریافت کد تایید
- ارسال کد 6 رقمی به ایمیل
- تایید کد و فعال‌سازی گزارش

**مراحل:**

1. کاربر `/set_email` می‌زند
2. State: `email_waiting_address` ست می‌شود
3. کاربر ایمیل را ارسال می‌کند
4. کد 6 رقمی تولید و به ایمیل ارسال می‌شود
5. State: `email_waiting_code` ست می‌شود
6. کاربر کد را ارسال می‌کند
7. کد تایید می‌شود و `email_verified_at` ست می‌شود

### 5. اضافه کردن متدهای Service برای ایمیل

**فایل:** `app/Services/PrayerBotServiceImpl.php` یا Service جدید

- `sendEmailVerificationCode($email, $code)` - ارسال کد به ایمیل
- `verifyEmailCode($chatId, $code)` - تایید کد
- `updateEmailSettings($chatId, $settings)` - به‌روزرسانی تنظیمات

### 6. ترجمه‌ها

**فایل:** `lang/fa/bot.php` و `lang/en/bot.php`

- پیام‌های مربوط به `/estimate_status`
- پیام‌های مربوط به `/email_settings`
- پیام‌های مربوط به تایید ایمیل
- پیام‌های خطا

## فایل‌های تغییر یافته

1. `app/Http/Controllers/PrayerBotController.php`

   - اضافه کردن `handleEstimateStatus()`
   - تغییر `handleEmailCommand()` برای پشتیبانی از state management
   - کامل کردن `handleEmailSettings()`
   - اضافه کردن handlers برای state های ایمیل

2. `app/Services/PrayerBotServiceImpl.php` (یا Service جدید)

   - متدهای مربوط به ایمیل

3. `lang/fa/bot.php` و `lang/en/bot.php`

   - ترجمه‌های جدید

4. `app/Mail/EmailVerificationMail.php` (جدید)

   - Mailable برای ارسال کد تایید

## ترتیب پیاده‌سازی

1. `/estimate_status` (ساده‌ترین)
2. `/set_email` alias
3. `/email_settings` (نمایش تنظیمات)
4. State management برای دریافت ایمیل
5. ارسال کد تایید
6. تایید کد

## نکات مهم

- استفاده از State Management موجود (`BotUserState`)
- Validation ایمیل (format)
- کد 6 رقمی تصادفی
- انقضای کد (مثلاً 10 دقیقه)
- Rate limiting برای جلوگیری از spam
- لاگ کردن تمام مراحل