# 📧 راهنمای کامل Email Service - همه چیز در یک جا

این راهنما شامل تمام اطلاعات لازم برای استفاده، تنظیمات، و عیب‌یابی ماژول Email Service است.

---

## 📋 فهرست مطالب

1. [معرفی](#معرفی)
2. [معماری و ساختار](#معماری-و-ساختار)
3. [نصب و تنظیمات اولیه](#نصب-و-تنظیمات-اولیه)
4. [تنظیمات .env](#تنظیمات-env)
5. [تفاوت Sandbox و Production](#تفاوت-sandbox-و-production)
6. [استفاده در کد](#استفاده-در-کد)
7. [انواع ایمیل](#انواع-ایمیل)
8. [تست و دیباگ](#تست-و-دیباگ)
9. [عیب‌یابی](#عیب‌یابی)
10. [به‌روزرسانی و نگهداری](#به‌روزرسانی-و-نگهداری)

---

## 🎯 معرفی

ماژول Email Service یک سیستم قابل استفاده مجدد برای ارسال ایمیل در ربات‌های مختلف است که:

- ✅ از الگوی **Interface** و **Abstract Class** استفاده می‌کند
- ✅ از **Dependency Injection** استفاده می‌کند
- ✅ پشتیبانی از **Mailtrap API** (Sandbox و Transactional)
- ✅ قابل توسعه برای سرویس‌های دیگر (SendGrid, Mailgun, ...)
- ✅ تست‌پذیر و قابل نگهداری

---

## 🏗️ معماری و ساختار

### ساختار فایل‌ها

```
app/
├── Interfaces/
│   └── Services/
│       └── EmailService.php              # Interface اصلی
├── Services/
│   ├── Email/
│   │   ├── AbstractEmailService.php      # کلاس Abstract
│   │   └── EmailData.php                # DTO برای داده‌های ایمیل
│   ├── MailtrapEmailServiceImpl.php     # پیاده‌سازی Mailtrap
│   └── MailtrapEmailService.php          # Wrapper برای سازگاری
└── Providers/
    └── AppServiceProvider.php            # Dependency Injection
```

### الگوی طراحی

```
EmailService (Interface)
    ↑
AbstractEmailService (Abstract Class)
    ↑
MailtrapEmailServiceImpl (Implementation)
```

### Dependency Injection

```php
// در AppServiceProvider
$this->app->bind(EmailService::class, MailtrapEmailServiceImpl::class);
```

---

## ⚙️ نصب و تنظیمات اولیه

### 1. بررسی فایل‌ها

مطمئن شوید که این فایل‌ها وجود دارند:

```bash
# Interface
app/Interfaces/Services/EmailService.php

# Abstract Class
app/Services/Email/AbstractEmailService.php

# DTO
app/Services/Email/EmailData.php

# Implementation
app/Services/MailtrapEmailServiceImpl.php

# Service Provider
app/Providers/AppServiceProvider.php
```

### 2. بررسی Views

```bash
# View های ایمیل
resources/views/emails/email-verification.blade.php
resources/views/emails/prayer-weekly-report-v1.blade.php
resources/views/emails/prayer-weekly-report-v2.blade.php
resources/views/emails/prayer-weekly-report-v3.blade.php
resources/views/emails/prayer-weekly-report-v4.blade.php
resources/views/emails/prayer-weekly-report-v5.blade.php
resources/views/emails/motivational.blade.php
```

### 3. بررسی Service Provider

در `app/Providers/AppServiceProvider.php` باید این خط وجود داشته باشد:

```php
use App\Interfaces\Services\EmailService;
use App\Services\MailtrapEmailServiceImpl;

public function register(): void
{
    // ...
    $this->app->bind(EmailService::class, MailtrapEmailServiceImpl::class);
}
```

---

## 🔧 تنظیمات .env

### تنظیمات کامل برای Production

```env
# ============================================
# Mailtrap API Configuration (Production)
# ============================================
MAILTRAP_API_TOKEN=your_transactional_api_token_here
MAILTRAP_USE_SANDBOX=false
MAILTRAP_INBOX_ID=1439975  # فقط برای Sandbox لازم است

# ============================================
# Email From Address
# ============================================
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="${APP_NAME} Bots"

# ============================================
# تنظیمات SMTP (اختیاری - اگر از SMTP استفاده نمی‌کنید)
# ============================================
# این تنظیمات برای Mailtrap API استفاده نمی‌شوند
# اما می‌توانید برای سرویس‌های دیگر نگه دارید
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=noreply@mailtrap.io
MAIL_PASSWORD=08512f3c99b39e
MAIL_ENCRYPTION=tls
```

### تنظیمات برای Sandbox (تست)

```env
# ============================================
# Mailtrap API Configuration (Sandbox)
# ============================================
MAILTRAP_API_TOKEN=your_sandbox_api_token_here
MAILTRAP_USE_SANDBOX=true
MAILTRAP_INBOX_ID=1439975  # ضروری برای Sandbox

# ============================================
# Email From Address
# ============================================
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="${APP_NAME} Bots"
```

### نکات مهم

1. **MAILTRAP_API_TOKEN**: 
   - برای Sandbox: از بخش Testing API در Mailtrap
   - برای Production: از بخش Transactional API در Mailtrap

2. **MAILTRAP_USE_SANDBOX**:
   - `true`: ایمیل‌ها در Sandbox ذخیره می‌شوند (برای تست)
   - `false`: ایمیل‌ها به inbox واقعی ارسال می‌شوند (Production)

3. **MAILTRAP_INBOX_ID**:
   - فقط برای Sandbox لازم است
   - از بخش Testing Inboxes در Mailtrap

4. **MAIL_FROM_ADDRESS**:
   - باید یک آدرس ایمیل معتبر باشد
   - برای Production باید دامنه verify شده باشد

---

## 🔄 تفاوت Sandbox و Production

### Sandbox Mode (`MAILTRAP_USE_SANDBOX=true`)

**ویژگی‌ها:**
- ✅ ایمیل‌ها به inbox واقعی ارسال **نمی‌شوند**
- ✅ ایمیل‌ها در Mailtrap Sandbox ذخیره می‌شوند
- ✅ برای تست و توسعه مناسب است
- ✅ نیاز به `MAILTRAP_INBOX_ID` دارد
- ✅ رایگان است

**استفاده:**
```env
MAILTRAP_USE_SANDBOX=true
MAILTRAP_INBOX_ID=1439975
```

**مشاهده ایمیل‌ها:**
1. به https://mailtrap.io بروید
2. وارد بخش Testing Inboxes شوید
3. Inbox با ID مربوطه را باز کنید
4. ایمیل‌های ارسال شده را ببینید

### Production Mode (`MAILTRAP_USE_SANDBOX=false`)

**ویژگی‌ها:**
- ✅ ایمیل‌ها به inbox واقعی ارسال **می‌شوند**
- ✅ نیاز به verify کردن دامنه دارد
- ✅ از Transactional API استفاده می‌کند
- ✅ ممکن است تاخیر 30-60 ثانیه‌ای داشته باشد
- ✅ نیاز به API Token از بخش Transactional دارد

**استفاده:**
```env
MAILTRAP_USE_SANDBOX=false
# MAILTRAP_INBOX_ID لازم نیست
```

**تنظیمات لازم:**
1. در Mailtrap.io به بخش Sending Domains بروید
2. دامنه خود را اضافه کنید (مثلاً `pardisania.ir`)
3. DNS Records را اضافه کنید
4. منتظر verify شدن بمانید
5. API Token از بخش Transactional را بگیرید

---

## 💻 استفاده در کد

### Dependency Injection در Controller

```php
<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class MyBotController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function sendVerification(Request $request)
    {
        $email = $request->input('email');
        $code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->emailService->sendVerificationEmail($email, $code);
            
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Email sending failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['success' => false], 500);
        }
    }
}
```

### استفاده در Job

```php
<?php

namespace App\Jobs;

use App\Interfaces\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(EmailService $emailService): void
    {
        $emailService->sendVerificationEmail('user@example.com', '123456');
    }
}
```

### استفاده مستقیم (بدون DI)

```php
use App\Interfaces\Services\EmailService;

$emailService = app(EmailService::class);
$emailService->sendVerificationEmail('user@example.com', '123456');
```

---

## 📧 انواع ایمیل

### 1. ایمیل تایید (Verification)

```php
$this->emailService->sendVerificationEmail(
    $email,      // آدرس ایمیل گیرنده
    $code,       // کد تایید 6 رقمی
    $htmlBody,   // محتوای HTML (اختیاری)
    $textBody    // محتوای متنی (اختیاری)
);
```

**مثال:**
```php
$code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
$this->emailService->sendVerificationEmail('user@example.com', $code);
```

### 2. گزارش هفتگی (Weekly Report)

```php
$this->emailService->sendWeeklyReportEmail(
    $email,              // آدرس ایمیل گیرنده
    $reportData,         // آرایه داده‌های گزارش
    $unsubscribeToken,   // توکن لغو اشتراک
    $templateVersion     // نسخه تمپلیت (1-5)
);
```

**مثال:**
```php
$reportData = [
    'period_start' => '2026-01-01',
    'period_end' => '2026-01-07',
    'total_prayers' => 10,
    'progress_percentage' => 50,
    'prayers_by_type' => [
        'صبح' => 3,
        'ظهر' => 4,
        'عصر' => 2,
        'مغرب' => 1,
    ]
];

$unsubscribeToken = bin2hex(random_bytes(32));
$templateVersion = rand(1, 5);

$this->emailService->sendWeeklyReportEmail(
    'user@example.com',
    $reportData,
    $unsubscribeToken,
    $templateVersion
);
```

### 3. ایمیل انگیزشی (Motivational)

```php
$this->emailService->sendMotivationalEmail(
    $email,      // آدرس ایمیل گیرنده
    $message,    // پیام انگیزشی
    $subject     // موضوع ایمیل (اختیاری)
);
```

**مثال:**
```php
$message = "🌟 عالی کار می‌کنید!\n\n";
$message .= "شما تا الان 10 نماز قضا ثبت کرده‌اید.\n";
$message .= "ادامه دهید! 💪";

$this->emailService->sendMotivationalEmail(
    'user@example.com',
    $message,
    'پیام انگیزشی - ربات شما'
);
```

### 4. ایمیل عمومی (General)

```php
use App\Services\Email\EmailData;

$emailData = new EmailData(
    to: 'user@example.com',
    subject: 'موضوع ایمیل',
    htmlBody: '<h1>محتوای HTML</h1>',
    textBody: 'محتوای متنی',
    category: 'Custom Category',
    customHeaders: [
        'X-Custom-Header' => 'value'
    ]
);

$this->emailService->sendEmail($emailData);
```

---

## 🧪 تست و دیباگ

### تست با Artisan Command

```bash
# 1. تست اتصال
php artisan email:test connection

# 2. تست ایمیل تایید
php artisan email:test verification test@example.com

# 3. تست با کد سفارشی
php artisan email:test verification test@example.com --code=999999

# 4. تست گزارش هفتگی
php artisan email:test weekly-report test@example.com

# 5. تست ایمیل انگیزشی
php artisan email:test motivational test@example.com
```

### تست در Tinker

```bash
php artisan tinker
```

```php
// دریافت EmailService
$service = app(App\Interfaces\Services\EmailService::class);

// تست اتصال
$service->testConnection();

// تست ارسال
$service->sendVerificationEmail('test@example.com', '123456');

exit;
```

### بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های زنده
tail -f storage/logs/laravel.log | grep -i "email\|mailtrap"

# جستجوی خطاها
grep "ERROR.*Email" storage/logs/laravel.log | tail -20

# جستجوی موفقیت‌ها
grep "✅.*Email" storage/logs/laravel.log | tail -20
```

---

## 🔍 عیب‌یابی

### مشکل 1: "API Token is not configured"

**علت:** `MAILTRAP_API_TOKEN` در `.env` تنظیم نشده است.

**راه‌حل:**
```bash
# بررسی .env
cat .env | grep MAILTRAP_API_TOKEN

# اگر خالی است، اضافه کنید
MAILTRAP_API_TOKEN=your_token_here

# پاک کردن Cache
php artisan config:clear
```

### مشکل 2: "MAILTRAP_INBOX_ID برای Sandbox ضروری است"

**علت:** `MAILTRAP_USE_SANDBOX=true` اما `MAILTRAP_INBOX_ID` تنظیم نشده است.

**راه‌حل:**
```env
MAILTRAP_USE_SANDBOX=true
MAILTRAP_INBOX_ID=1439975
```

### مشکل 3: "Unauthorized" یا "401"

**علت:** API Token اشتباه است یا منقضی شده است.

**راه‌حل:**
1. به Mailtrap.io بروید
2. API Token جدید بگیرید
3. در `.env` به‌روزرسانی کنید
4. Cache را پاک کنید

### مشکل 4: ایمیل در Production نمی‌رسد

**علت‌های ممکن:**
- دامنه verify نشده است
- `MAILTRAP_USE_SANDBOX` هنوز `true` است
- API Token از بخش Transactional نیست

**راه‌حل:**
```env
# 1. مطمئن شوید که Sandbox خاموش است
MAILTRAP_USE_SANDBOX=false

# 2. از Transactional API Token استفاده کنید
MAILTRAP_API_TOKEN=transactional_token_here

# 3. دامنه را verify کنید
# در Mailtrap.io > Sending Domains

# 4. Cache را پاک کنید
php artisan config:clear
```

### مشکل 5: "View not found"

**علت:** View های ایمیل وجود ندارند.

**راه‌حل:**
```bash
# بررسی وجود View ها
ls resources/views/emails/

# باید این فایل‌ها وجود داشته باشند:
# - email-verification.blade.php
# - prayer-weekly-report-v1.blade.php (تا v5)
# - motivational.blade.php
```

### مشکل 6: تاخیر در دریافت ایمیل

**علت:** در Production Mode، Mailtrap ممکن است 30-60 ثانیه تاخیر داشته باشد.

**راه‌حل:**
- این طبیعی است و مشکلی نیست
- برای کاهش تاخیر می‌توانید از سرویس‌های دیگر استفاده کنید

---

## 🔄 به‌روزرسانی و نگهداری

### تغییر از Sandbox به Production

1. **Verify کردن دامنه:**
   - به Mailtrap.io > Sending Domains بروید
   - دامنه را اضافه کنید
   - DNS Records را اضافه کنید
   - منتظر verify شدن بمانید

2. **گرفتن Transactional API Token:**
   - به Mailtrap.io > Settings > API Tokens بروید
   - Token از بخش Transactional را کپی کنید

3. **به‌روزرسانی .env:**
   ```env
   MAILTRAP_API_TOKEN=new_transactional_token
   MAILTRAP_USE_SANDBOX=false
   # MAILTRAP_INBOX_ID دیگر لازم نیست
   ```

4. **پاک کردن Cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

5. **تست:**
   ```bash
   php artisan email:test connection
   php artisan email:test verification your-email@example.com
   ```

### اضافه کردن سرویس جدید

اگر می‌خواهید از سرویس دیگری (مثل SendGrid) استفاده کنید:

1. **ایجاد Implementation:**
   ```php
   namespace App\Services;

   use App\Interfaces\Services\EmailService;
   use App\Services\Email\AbstractEmailService;
   use App\Services\Email\EmailData;

   class SendGridEmailServiceImpl extends AbstractEmailService implements EmailService
   {
       public function sendEmail(EmailData $emailData): bool
       {
           // پیاده‌سازی SendGrid
       }

       public function testConnection(): bool
       {
           // تست اتصال
       }
   }
   ```

2. **به‌روزرسانی Service Provider:**
   ```php
   $this->app->bind(EmailService::class, SendGridEmailServiceImpl::class);
   ```

3. **تست:**
   ```bash
   php artisan email:test connection
   ```

---

## 📊 خلاصه تنظیمات

### برای Development (تست)

```env
MAILTRAP_API_TOKEN=sandbox_token
MAILTRAP_USE_SANDBOX=true
MAILTRAP_INBOX_ID=1439975
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="Bots"
```

### برای Production

```env
MAILTRAP_API_TOKEN=transactional_token
MAILTRAP_USE_SANDBOX=false
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="Bots"
```

---

## 📚 منابع و مستندات

- **راهنمای ماژول:** `docs/EMAIL_SERVICE_MODULE_README.md`
- **راهنمای تست:** `docs/EMAIL_SERVICE_TEST_GUIDE.md`
- **راهنمای Mailtrap:** `docs/MAILTRAP_SETUP_GUIDE.md`
- **Mailtrap API Docs:** https://mailtrap.io/api-docs

---

## ✅ چک‌لیست نصب

- [ ] فایل‌های Interface و Abstract ایجاد شده‌اند
- [ ] `MailtrapEmailServiceImpl` ایجاد شده است
- [ ] Service Provider به‌روزرسانی شده است
- [ ] View های ایمیل وجود دارند
- [ ] تنظیمات `.env` کامل است
- [ ] Cache پاک شده است
- [ ] تست Connection موفق است
- [ ] تست ارسال ایمیل موفق است

---

**آخرین به‌روزرسانی:** 2026-01-08  
**نسخه:** 1.0.0
