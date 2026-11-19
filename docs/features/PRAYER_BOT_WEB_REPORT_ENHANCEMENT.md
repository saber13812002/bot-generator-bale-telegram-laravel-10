# بهبودهای صفحه گزارش وب و سیستم ایمیل ربات نماز قضا

## 📋 خلاصه

این مستندات شامل بهبودهای اعمال شده بر روی سیستم گزارش‌دهی ربات نماز قضا است که شامل:
- نمایش تاریخ شمسی و قمری در صفحه گزارش وب
- پشتیبانی چندزبانه برای ایمیل‌های گزارش
- امکان تست زبان از طریق کامند لاین
- دکمه راهنما برای دریافت لینک گزارش از طریق ربات

---

## 🗓️ تاریخ شمسی و قمری در صفحه گزارش

### ویژگی‌ها

صفحه گزارش وب (`/namaz-ghaza/{token}`) اکنون تاریخ را در سه فرمت نمایش می‌دهد:
- **میلادی**: تاریخ استاندارد میلادی
- **شمسی**: تاریخ شمسی (جلالی) با استفاده از کتابخانه Verta
- **قمری**: تاریخ هجری قمری با الگوریتم تبدیل خودکار

### پیاده‌سازی

#### Helper Class

کلاس `DateHelper` در `app/Helpers/DateHelper.php` ایجاد شده است که شامل متدهای زیر است:

```php
// تبدیل به تاریخ شمسی
DateHelper::toShamsi($date, 'Y/m/d');

// تبدیل به تاریخ قمری
DateHelper::toHijri($date, 'Y/m/d');

// دریافت نام ماه شمسی
DateHelper::getShamsiMonthName($month);

// دریافت نام ماه قمری
DateHelper::getHijriMonthName($month);
```

#### استفاده در Controller

در `PrayerReportWebController`، تاریخ‌ها به صورت خودکار محاسبه و به view پاس داده می‌شوند:

```php
$reportData['dates'] = [
    'gregorian' => [
        'start' => $periodStart->format('Y-m-d'),
        'end' => $periodEnd->format('Y-m-d'),
    ],
    'shamsi' => [
        'start' => DateHelper::toShamsi($periodStart, 'Y/m/d'),
        'end' => DateHelper::toShamsi($periodEnd, 'Y/m/d'),
    ],
    'hijri' => [
        'start' => DateHelper::toHijri($periodStart, 'Y/m/d'),
        'end' => DateHelper::toHijri($periodEnd, 'Y/m/d'),
    ],
];
```

#### نمایش در View

در `resources/views/prayer-report-web.blade.php`:

```blade
<div style="margin-top: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
    <p><strong>میلادی:</strong> {{ $reportData['dates']['gregorian']['start'] }} تا {{ $reportData['dates']['gregorian']['end'] }}</p>
    <p><strong>شمسی:</strong> {{ $reportData['dates']['shamsi']['start'] }} تا {{ $reportData['dates']['shamsi']['end'] }}</p>
    <p><strong>قمری:</strong> {{ $reportData['dates']['hijri']['start'] }} تا {{ $reportData['dates']['hijri']['end'] }}</p>
</div>
```

---

## 🌐 پشتیبانی چندزبانه برای ایمیل‌ها

### ویژگی‌ها

سیستم ایمیل گزارش هفتگی اکنون از چندزبانه بودن پشتیبانی می‌کند:
- زبان ایمیل بر اساس زبان ربات کاربر تعیین می‌شود
- امکان تست زبان از طریق کامند لاین
- پشتیبانی از تمام زبان‌های موجود در سیستم

### نحوه کار

#### 1. تعیین زبان خودکار

زبان ایمیل به ترتیب اولویت از منابع زیر تعیین می‌شود:
1. **کامند لاین** (برای تست): `--lang=en`
2. **زبان ربات کاربر**: از جدول `bots` و فیلد `language_code`
3. **پیش‌فرض**: `fa` (فارسی)

#### 2. در کامند `SendUserWeeklyReport`

```bash
php artisan email:send-user-report --email=user@example.com --lang=en
```

زبان از کامند لاین خوانده می‌شود:

```php
$lang = $this->option('lang');
if (!$lang && $user->bot_id) {
    $bot = \App\Models\Bot::find($user->bot_id);
    if ($bot && $bot->language_code) {
        $lang = $bot->language_code;
    }
}
if (!$lang) {
    $lang = 'fa';
}
app()->setLocale($lang);
```

#### 3. در کامند `SendPrayerWeeklyReports`

زبان به صورت خودکار از ربات کاربر گرفته می‌شود:

```php
$lang = null;
if ($user->bot_id) {
    $bot = \App\Models\Bot::find($user->bot_id);
    if ($bot && $bot->language_code) {
        $lang = $bot->language_code;
    }
}
if (!$lang) {
    $lang = 'fa';
}
$reportData['lang'] = $lang;
```

#### 4. در EmailData Service

زبان به view پاس داده می‌شود:

```php
public static function weeklyReport(
    string $to,
    array $reportData,
    string $unsubscribeToken,
    int $templateVersion = 1,
    ?string $lang = null
): self {
    if ($lang) {
        app()->setLocale($lang);
    }
    // ...
    $htmlBody = view($view, [
        // ...
        'lang' => $lang ?? app()->getLocale(),
    ])->render();
}
```

### تست زبان

برای تست ایمیل با زبان خاص:

```bash
# انگلیسی
php artisan email:send-user-report --email=user@example.com --lang=en --preview

# عربی
php artisan email:send-user-report --email=user@example.com --lang=ar --preview

# فارسی (پیش‌فرض)
php artisan email:send-user-report --email=user@example.com --lang=fa --preview
```

---

## 🔗 دکمه راهنما برای دریافت لینک گزارش

### ویژگی‌ها

کاربران می‌توانند از طریق ربات لینک گزارش وب خود را دریافت کنند:
- دکمه در بخش راهنمای گزارش (`/help_report`)
- فقط برای کاربرانی که تخمین زده‌اند
- ارسال لینک به صورت مستقیم در ربات

### پیاده‌سازی

#### 1. نمایش دکمه در Help Report

در `PrayerBotController::showHelpReport()`:

```php
protected function showHelpReport(Telegram $bot, int $chatId, ?int $messageId = null): void
{
    $botUser = BotUsers::where('chat_id', $chatId)->first();
    $hasEstimate = $botUser && \App\Models\PrayerEstimate::where('chat_id', $chatId)->exists();
    
    $message = $this->getHelpReport();
    
    $keyboard = [
        [
            ['text' => trans('bot.help_btn_back_to_help'), 'callback_data' => 'help_main'],
        ]
    ];
    
    if ($hasEstimate && $botUser && $botUser->web_report_token) {
        $reportUrl = url("/namaz-ghaza/{$botUser->web_report_token}");
        $message .= "\n\n" . trans('bot.help_report_link_available') . "\n";
        $message .= $reportUrl;
        
        // دکمه دریافت لینک
        array_unshift($keyboard, [
            ['text' => trans('bot.help_btn_get_report_link'), 'callback_data' => 'help_get_report_link'],
        ]);
    } elseif (!$hasEstimate) {
        $message .= "\n\n" . trans('bot.help_report_need_estimate');
    }
    
    // ارسال پیام با کیبورد
}
```

#### 2. پردازش کلیک روی دکمه

در `PrayerBotController::handleGetReportLink()`:

```php
protected function handleGetReportLink(Telegram $bot, array $callbackQuery, int $chatId, string $type): void
{
    $botUser = BotUsers::where('chat_id', $chatId)->first();
    
    if (!$botUser) {
        BotHelper::sendMessage($bot, trans('bot.error_user_not_found'));
        return;
    }
    
    $hasEstimate = \App\Models\PrayerEstimate::where('chat_id', $chatId)->exists();
    
    if (!$hasEstimate) {
        $message = trans('bot.help_report_need_estimate_message');
        BotHelper::sendMessage($bot, $message);
        return;
    }
    
    // ایجاد توکن اگر وجود ندارد
    if (!$botUser->web_report_token) {
        $botUser->web_report_token = bin2hex(random_bytes(32));
        $botUser->save();
    }
    
    $reportUrl = url("/namaz-ghaza/{$botUser->web_report_token}");
    
    $message = trans('bot.help_report_link_sent') . "\n\n";
    $message .= "🔗 " . $reportUrl . "\n\n";
    $message .= trans('bot.help_report_link_instruction');
    
    BotHelper::sendMessage($bot, $message);
}
```

#### 3. ترجمه‌های جدید

در `lang/fa/bot.php`:

```php
'help_btn_get_report_link' => '🔗 دریافت لینک گزارش',
'help_report_link_available' => '✅ لینک گزارش شما:',
'help_report_need_estimate' => '⚠️ برای دریافت لینک گزارش، ابتدا باید تخمین نماز قضای خود را ثبت کنید.\n\nاز دستور /estimate استفاده کنید.',
'help_report_need_estimate_message' => '⚠️ برای دریافت لینک گزارش، ابتدا باید تخمین نماز قضای خود را ثبت کنید.\n\nاز دستور /estimate استفاده کنید.',
'help_report_link_sent' => '✅ لینک گزارش شما:',
'help_report_link_instruction' => '💡 می‌توانید این لینک را در مرورگر باز کنید و گزارش کامل با نمودارها را مشاهده کنید.',
```

### نحوه استفاده

1. کاربر دستور `/help` را در ربات می‌زند
2. روی دکمه "📧 راهنمای گزارش" کلیک می‌کند
3. اگر تخمین زده باشد، دکمه "🔗 دریافت لینک گزارش" نمایش داده می‌شود
4. با کلیک روی دکمه، لینک گزارش به صورت مستقیم ارسال می‌شود
5. کاربر می‌تواند لینک را در مرورگر باز کند و گزارش کامل را مشاهده کند

---

## 📁 فایل‌های تغییر یافته

### فایل‌های جدید
- `app/Helpers/DateHelper.php` - Helper برای تبدیل تاریخ‌ها

### فایل‌های تغییر یافته
- `app/Http/Controllers/PrayerReportWebController.php` - اضافه کردن تاریخ‌های شمسی و قمری
- `app/Http/Controllers/PrayerBotController.php` - اضافه کردن دکمه دریافت لینک گزارش
- `app/Console/Commands/SendUserWeeklyReport.php` - پشتیبانی از کامند `--lang`
- `app/Console/Commands/SendPrayerWeeklyReports.php` - تعیین زبان از ربات کاربر
- `app/Services/Email/EmailData.php` - پشتیبانی از پارامتر `lang`
- `app/Services/Email/AbstractEmailService.php` - اضافه کردن پارامتر `lang`
- `app/Jobs/SendPrayerReportEmailJob.php` - پاس دادن زبان به EmailService
- `resources/views/prayer-report-web.blade.php` - نمایش تاریخ‌های شمسی و قمری
- `lang/fa/bot.php` - ترجمه‌های جدید برای دکمه راهنما

---

## 🧪 تست

### تست تاریخ شمسی و قمری

1. به صفحه گزارش وب بروید: `https://bots.pardisania.ir/namaz-ghaza/{token}`
2. بررسی کنید که تاریخ‌های میلادی، شمسی و قمری نمایش داده می‌شوند
3. بررسی کنید که تاریخ‌ها صحیح هستند

### تست چندزبانه بودن ایمیل

```bash
# تست با زبان انگلیسی
php artisan email:send-user-report --email=test@example.com --lang=en --preview

# تست با زبان عربی
php artisan email:send-user-report --email=test@example.com --lang=ar --preview

# تست با زبان فارسی
php artisan email:send-user-report --email=test@example.com --lang=fa --preview
```

### تست دکمه دریافت لینک

1. در ربات دستور `/help` را بزنید
2. روی "📧 راهنمای گزارش" کلیک کنید
3. اگر تخمین زده باشید، دکمه "🔗 دریافت لینک گزارش" را ببینید
4. روی دکمه کلیک کنید و لینک را دریافت کنید
5. لینک را در مرورگر باز کنید و گزارش را مشاهده کنید

---

## 📝 نکات مهم

1. **تاریخ قمری**: الگوریتم تبدیل تاریخ قمری یک الگوریتم تقریبی است و ممکن است در برخی موارد نیاز به تصحیح داشته باشد.

2. **زبان ایمیل**: اگر زبان ربات کاربر تنظیم نشده باشد، پیش‌فرض فارسی استفاده می‌شود.

3. **لینک گزارش**: فقط کاربرانی که تخمین زده‌اند می‌توانند لینک گزارش را دریافت کنند.

4. **امنیت**: لینک گزارش با یک توکن یکتا محافظت می‌شود و فقط برای کاربر مربوطه قابل دسترسی است.

---

## 🔗 لینک‌های مرتبط

- [راهنمای کامل ربات نماز قضا](./PRAYER_BOT_README.md)
- [راهنمای سیستم Help](./PRAYER_BOT_HELP_SYSTEM.md)
- [راهنمای تخمین نماز قضا](./PRAYER_ESTIMATE_CONVERSATION.md)

---

**آخرین به‌روزرسانی**: 2026-01-22
