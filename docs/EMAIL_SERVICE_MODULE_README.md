# ماژول Email Service - راهنمای استفاده

این ماژول یک سیستم قابل استفاده مجدد برای ارسال ایمیل در ربات‌های مختلف است که از الگوی **Interface** و **Abstract Class** استفاده می‌کند.

## 📋 فهرست مطالب

1. [معماری](#معماری)
2. [نصب و تنظیمات](#نصب-و-تنظیمات)
3. [استفاده در ربات‌ها](#استفاده-در-رباتها)
4. [انواع ایمیل](#انواع-ایمیل)
5. [پیاده‌سازی سرویس جدید](#پیاده‌سازی-سرویس-جدید)
6. [تست](#تست)

---

## 🏗️ معماری

### ساختار فایل‌ها

```
app/
├── Interfaces/
│   └── Services/
│       └── EmailService.php          # Interface اصلی
├── Services/
│   ├── Email/
│   │   ├── AbstractEmailService.php  # کلاس Abstract
│   │   └── EmailData.php            # DTO برای داده‌های ایمیل
│   ├── MailtrapEmailServiceImpl.php  # پیاده‌سازی Mailtrap
│   └── MailtrapEmailService.php      # Wrapper برای سازگاری
└── Providers/
    └── AppServiceProvider.php        # Dependency Injection
```

### الگوی طراحی

- **Interface**: `EmailService` - قرارداد اصلی
- **Abstract Class**: `AbstractEmailService` - پیاده‌سازی مشترک
- **Implementation**: `MailtrapEmailServiceImpl` - پیاده‌سازی خاص Mailtrap
- **DTO**: `EmailData` - انتقال داده‌های ایمیل

---

## ⚙️ نصب و تنظیمات

### 1. تنظیمات .env

```env
# Mailtrap API Configuration
MAILTRAP_API_TOKEN=your_api_token_here
MAILTRAP_INBOX_ID=your_inbox_id_here
MAILTRAP_USE_SANDBOX=true

# Email From Address
MAIL_FROM_ADDRESS=hello@yourdomain.com
MAIL_FROM_NAME="Your Bot Name"
```

### 2. Service Provider

در `app/Providers/AppServiceProvider.php`:

```php
use App\Interfaces\Services\EmailService;
use App\Services\MailtrapEmailServiceImpl;

public function register(): void
{
    // Email Service
    $this->app->bind(EmailService::class, MailtrapEmailServiceImpl::class);
}
```

### 3. Views

مطمئن شوید که view های زیر وجود دارند:

- `resources/views/emails/email-verification.blade.php`
- `resources/views/emails/prayer-weekly-report-v1.blade.php` (تا v5)
- `resources/views/emails/motivational.blade.php`

---

## 🤖 استفاده در ربات‌ها

### Dependency Injection

```php
use App\Interfaces\Services\EmailService;

class MyBotController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
}
```

### ارسال ایمیل تایید

```php
try {
    $code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $this->emailService->sendVerificationEmail($userEmail, $code);
    
    Log::info('Verification email sent', ['email' => $userEmail]);
} catch (Exception $e) {
    Log::error('Failed to send verification email', [
        'email' => $userEmail,
        'error' => $e->getMessage()
    ]);
    // Handle error
}
```

### ارسال گزارش هفتگی

```php
try {
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
    $templateVersion = rand(1, 5); // برای جلوگیری از spam
    
    $this->emailService->sendWeeklyReportEmail(
        $userEmail,
        $reportData,
        $unsubscribeToken,
        $templateVersion
    );
} catch (Exception $e) {
    Log::error('Failed to send weekly report', [
        'email' => $userEmail,
        'error' => $e->getMessage()
    ]);
}
```

### ارسال ایمیل انگیزشی

```php
try {
    $message = "🌟 عالی کار می‌کنید!\n\n";
    $message .= "شما تا الان 10 نماز قضا ثبت کرده‌اید.\n";
    $message .= "ادامه دهید! 💪";
    
    $this->emailService->sendMotivationalEmail(
        $userEmail,
        $message,
        'پیام انگیزشی - ربات شما'
    );
} catch (Exception $e) {
    Log::error('Failed to send motivational email', [
        'email' => $userEmail,
        'error' => $e->getMessage()
    ]);
}
```

### ارسال ایمیل عمومی

```php
use App\Services\Email\EmailData;

try {
    $emailData = new EmailData(
        to: $userEmail,
        subject: 'موضوع ایمیل',
        htmlBody: '<h1>محتوای HTML</h1>',
        textBody: 'محتوای متنی',
        category: 'Custom Category',
        customHeaders: [
            'X-Custom-Header' => 'value'
        ]
    );
    
    $this->emailService->sendEmail($emailData);
} catch (Exception $e) {
    Log::error('Failed to send email', [
        'email' => $userEmail,
        'error' => $e->getMessage()
    ]);
}
```

---

## 📧 انواع ایمیل

### 1. ایمیل تایید (Verification)

```php
$emailService->sendVerificationEmail($to, $code);
```

**پارامترها:**
- `$to`: آدرس ایمیل گیرنده
- `$code`: کد تایید 6 رقمی
- `$htmlBody`: محتوای HTML (اختیاری)
- `$textBody`: محتوای متنی (اختیاری)

### 2. گزارش هفتگی (Weekly Report)

```php
$emailService->sendWeeklyReportEmail($to, $reportData, $unsubscribeToken, $templateVersion);
```

**پارامترها:**
- `$to`: آدرس ایمیل گیرنده
- `$reportData`: آرایه داده‌های گزارش
- `$unsubscribeToken`: توکن لغو اشتراک
- `$templateVersion`: نسخه تمپلیت (1-5)

### 3. ایمیل انگیزشی (Motivational)

```php
$emailService->sendMotivationalEmail($to, $message, $subject);
```

**پارامترها:**
- `$to`: آدرس ایمیل گیرنده
- `$message`: پیام انگیزشی
- `$subject`: موضوع ایمیل (اختیاری)

### 4. ایمیل عمومی (General)

```php
$emailService->sendEmail($emailData);
```

**پارامترها:**
- `$emailData`: شیء `EmailData`

---

## 🔧 پیاده‌سازی سرویس جدید

اگر می‌خواهید از سرویس دیگری (مثل SendGrid, Mailgun) استفاده کنید:

### 1. ایجاد Implementation

```php
namespace App\Services;

use App\Interfaces\Services\EmailService;
use App\Services\Email\AbstractEmailService;
use App\Services\Email\EmailData;
use Exception;

class SendGridEmailServiceImpl extends AbstractEmailService implements EmailService
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = env('SENDGRID_API_KEY');
    }

    public function sendEmail(EmailData $emailData): bool
    {
        $this->validateEmailData($emailData);
        
        // پیاده‌سازی ارسال با SendGrid API
        // ...
        
        return true;
    }

    public function testConnection(): bool
    {
        // تست اتصال به SendGrid
        // ...
        return true;
    }
}
```

### 2. به‌روزرسانی Service Provider

```php
use App\Interfaces\Services\EmailService;
use App\Services\SendGridEmailServiceImpl;

public function register(): void
{
    // تغییر Implementation
    $this->app->bind(EmailService::class, SendGridEmailServiceImpl::class);
}
```

---

## 🧪 تست

### تست با Artisan Command

```bash
# تست اتصال
php artisan email:test-connection

# تست ارسال ایمیل تایید
php artisan email:test-verification test@example.com

# تست ارسال گزارش هفتگی
php artisan email:test-weekly-report test@example.com

# تست ارسال ایمیل انگیزشی
php artisan email:test-motivational test@example.com
```

### تست در کد

```php
use App\Interfaces\Services\EmailService;

// در Controller یا Service
public function testEmail(EmailService $emailService)
{
    try {
        // تست اتصال
        $emailService->testConnection();
        
        // تست ارسال
        $emailService->sendVerificationEmail('test@example.com', '123456');
        
        return 'Email sent successfully!';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}
```

---

## 📝 مثال کامل در Controller

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

    public function handleEmailVerification(Request $request)
    {
        $email = $request->input('email');
        $code = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->emailService->sendVerificationEmail($email, $code);
            
            return response()->json([
                'success' => true,
                'message' => 'Verification email sent'
            ]);
        } catch (Exception $e) {
            Log::error('Email verification failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email'
            ], 500);
        }
    }
}
```

---

## 🎯 مزایای این معماری

1. **قابل استفاده مجدد**: می‌توانید در هر رباتی استفاده کنید
2. **قابل توسعه**: به راحتی سرویس جدید اضافه می‌کنید
3. **تست‌پذیر**: Interface ها تست را آسان می‌کنند
4. **انعطاف‌پذیر**: می‌توانید Implementation را تغییر دهید
5. **Type Safe**: از Type Hints استفاده می‌کند

---

## 🔍 دیباگ

### بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های ایمیل
tail -f storage/logs/laravel.log | grep -i "email"

# جستجوی خطاها
grep "ERROR.*Email" storage/logs/laravel.log
```

### بررسی تنظیمات

```php
php artisan tinker

>>> app(App\Interfaces\Services\EmailService::class)->testConnection();
```

---

## 📚 منابع

- [Mailtrap API Documentation](https://mailtrap.io/api-docs)
- [Laravel Service Container](https://laravel.com/docs/container)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)

---

**آخرین به‌روزرسانی:** 2026-01-08
