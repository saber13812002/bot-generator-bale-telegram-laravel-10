# سیستم راهنمای ربات نماز قضا

## 🎯 هدف

ارائه راهنمای کامل و کاربرپسند برای استفاده از ربات نماز قضا

## 📋 ساختار Help

### 1. Help اصلی (`/help`)
نمایش منوی اصلی با دکمه‌های دسترسی سریع

### 2. Help دستورات (`/help_commands`)
لیست کامل دستورات با توضیح مختصر

### 3. Help نحوه استفاده (`/help_usage`)
راهنمای گام به گام استفاده از ربات

### 4. Help تخمین (`/help_estimate`)
توضیح کامل سیستم تخمین

### 5. Help گزارش (`/help_report`)
توضیح سیستم گزارش‌دهی ایمیل

### 6. Help سوالات متداول (`/help_faq`)
پاسخ به سوالات رایج

## 📝 محتوای پیام‌ها

### Help اصلی

```
🕌 راهنمای ربات نماز قضا

به ربات نماز قضا خوش آمدید! این ربات به شما کمک می‌کند تا:

✅ نمازهای قضای خود را ثبت کنید
📊 تخمین نمازهای قضا را مدیریت کنید
📧 گزارش هفتگی دریافت کنید
📈 پیشرفت خود را پیگیری کنید

📚 برای اطلاعات بیشتر، یکی از موارد زیر را انتخاب کنید:

[دستورات] [نحوه استفاده]
[تخمین] [گزارش ایمیل]
[سوالات متداول]

💡 برای شروع سریع: فقط عدد 2، 3 یا 4 ارسال کنید!
```

### Help دستورات

```
📋 لیست دستورات

🔹 دستورات اصلی:
• /start - شروع کار با ربات
• /help - نمایش راهنما
• /stats - مشاهده آمار

🔹 ثبت نماز:
• ارسال عدد 2 - ثبت نماز صبح (2 رکعت)
• ارسال عدد 3 - ثبت نماز مغرب (3 رکعت)
• ارسال عدد 4 - ثبت نماز ظهر/عصر/عشا (4 رکعت)
• /remove_[ID] - حذف نماز ثبت شده

🔹 تخمین:
• /estimate - ثبت تخمین نمازهای قضا
• /estimate_status - مشاهده تخمین فعلی

🔹 گزارش:
• /set_email - تنظیم ایمیل برای گزارش
• /email_settings - تنظیمات ایمیل
• /unsubscribe - لغو اشتراک ایمیل

🔹 راهنما:
• /help_usage - نحوه استفاده
• /help_estimate - راهنمای تخمین
• /help_report - راهنمای گزارش
• /help_faq - سوالات متداول

💡 نکته: می‌توانید به جای ارسال دستور، از دکمه‌های زیر پیام استفاده کنید.
```

### Help نحوه استفاده

```
📖 نحوه استفاده از ربات

🔸 مرحله 1: ثبت نماز
برای ثبت نماز قضا، کافی است تعداد رکعات را ارسال کنید:

• 2 → نماز صبح (2 رکعت)
• 3 → نماز مغرب (3 رکعت)  
• 4 → نماز ظهر، عصر یا عشا (4 رکعت)

مثال:
شما: 4
ربات: ✅ 4 رکعت (نماز ظهر) ثبت شد!
      برای حذف: /remove_123

🔸 مرحله 2: مشاهده آمار
با دستور /stats می‌توانید آمار خود را ببینید:

• تعداد کل رکعات ثبت شده
• تعداد نمازهای ثبت شده
• پیشرفت نسبت به تخمین
• آمار هفته جاری

🔸 مرحله 3: ثبت تخمین (اختیاری)
برای پیگیری بهتر، تخمین نمازهای قضای خود را ثبت کنید:

1. دستور /estimate را ارسال کنید
2. واحد (روز، هفته، ماه، سال، رکعت) را انتخاب کنید
3. عدد را وارد کنید

مثال: 6 ماه = حدود 3060 رکعت

🔸 مرحله 4: دریافت گزارش (اختیاری)
برای دریافت گزارش هفتگی:

1. دستور /set_email را ارسال کنید
2. ایمیل خود را وارد کنید
3. کد تایید را وارد کنید

هر هفته گزارش پیشرفت شما ارسال می‌شود!

💡 نکته: ربات به صورت هوشمند نوع نماز را تشخیص می‌دهد.
```

### Help تخمین

```
📊 راهنمای سیستم تخمین

🎯 تخمین چیست؟
تخمین، مجموع نمازهای قضایی است که فکر می‌کنید دارید.
این عدد به شما کمک می‌کند پیشرفت خود را پیگیری کنید.

📝 نحوه ثبت تخمین:

1️⃣ دستور /estimate را ارسال کنید

2️⃣ واحد مورد نظر را انتخاب کنید:
   • روز: تعداد روزهایی که نماز نخوانده‌اید
   • هفته: تعداد هفته‌ها
   • ماه: تعداد ماه‌ها
   • سال: تعداد سال‌ها
   • رکعت: مستقیم تعداد رکعات

3️⃣ عدد را وارد کنید

مثال:
شما: /estimate
ربات: [نمایش دکمه‌ها]
شما: [کلیک روی "ماه"]
ربات: چند ماه؟
شما: 6
ربات: ✅ 6 ماه = 3060 رکعت ثبت شد

🔢 محاسبات:
• هر روز = 17 رکعت (5 نماز)
• هر هفته = 119 رکعت (7 روز)
• هر ماه = 510 رکعت (30 روز)
• هر سال = 6205 رکعت (365 روز)

📈 نمایش پیشرفت:
بعد از ثبت تخمین، در دستور /stats می‌بینید:

🎯 تخمین کل: 3060 رکعت
✅ ثبت شده: 450 رکعت (15%)
📉 باقیمانده: 2610 رکعت

⏱️ زمان تخمینی: با این سرعت، حدود 8 ماه

💡 نکته: تخمین جدید جایگزین تخمین قبلی می‌شود.
```

### Help گزارش

```
📧 راهنمای سیستم گزارش ایمیل

📬 گزارش هفتگی چیست؟
هر هفته یک ایمیل حاوی:
• تعداد نمازهای ثبت شده در هفته
• پیشرفت نسبت به تخمین
• نمودار پیشرفت
• انگیزه و تشویق

⚙️ نحوه فعال‌سازی:

1️⃣ تنظیم ایمیل:
   /set_email

2️⃣ وارد کردن ایمیل:
   example@gmail.com

3️⃣ تایید ایمیل:
   کد 6 رقمی ارسال شده را وارد کنید

✅ فعال شد! از این پس هر هفته گزارش دریافت می‌کنید.

🔧 تنظیمات:

• مشاهده تنظیمات: /email_settings
• تغییر ایمیل: /set_email (ایمیل جدید)
• لغو اشتراک: /unsubscribe

📅 زمان ارسال:
گزارش‌ها هر یکشنبه صبح ساعت 9 ارسال می‌شوند.

🔒 امنیت:
• ایمیل شما محفوظ است
• فقط برای ارسال گزارش استفاده می‌شود
• هر زمان می‌توانید لغو کنید

💡 نکته: حتی اگر هفته‌ای نماز ثبت نکنید، ایمیل انگیزشی دریافت می‌کنید!
```

### Help سوالات متداول

```
❓ سوالات متداول (FAQ)

🔹 چگونه نماز ثبت کنم؟
فقط عدد 2، 3 یا 4 را ارسال کنید.
2 = صبح، 3 = مغرب، 4 = ظهر/عصر/عشا

🔹 اگر اشتباه ثبت کردم چه کنم؟
از دستور /remove_[ID] استفاده کنید.
ID در پیام تایید ربات نمایش داده می‌شود.

🔹 ربات چطور نوع نماز را تشخیص می‌دهد؟
بر اساس تعداد رکعات و ساعت روز:
• 2 رکعت → صبح
• 3 رکعت → مغرب
• 4 رکعت → ظهر (قبل از ظهر) یا عصر/عشا (بعد از ظهر)

🔹 آیا باید تخمین ثبت کنم؟
خیر، اختیاری است. ولی برای پیگیری بهتر توصیه می‌شود.

🔹 تخمین من دقیق نیست، چه کنم؟
می‌توانید هر زمان با /estimate تخمین جدید ثبت کنید.

🔹 چند بار در روز می‌توانم نماز ثبت کنم؟
نامحدود! هر چند نماز که می‌خوانید ثبت کنید.

🔹 آیا می‌توانم نمازهای قبلی را ببینم؟
بله، با دستور /stats لیست آخرین نمازها را می‌بینید.

🔹 گزارش ایمیل چه زمانی ارسال می‌شود؟
هر یکشنبه صبح ساعت 9

🔹 اگر ایمیل دریافت نکردم؟
• پوشه Spam را چک کنید
• ایمیل را با /email_settings بررسی کنید
• ایمیل جدید با /set_email ثبت کنید

🔹 آیا اطلاعات من محفوظ است؟
بله، تمام اطلاعات شما محرمانه و امن است.

🔹 ربات رایگان است؟
بله، کاملاً رایگان و بدون محدودیت!

🔹 آیا ربات در بله هم کار می‌کند؟
بله، در تلگرام و بله هر دو کار می‌کند.

💡 سوال دیگری دارید؟
با پشتیبانی تماس بگیرید: @support
```

## 🎨 طراحی کیبوردها

### کیبورد Help اصلی

```php
$keyboard = [
    [
        ['text' => '📋 دستورات', 'callback_data' => 'help_commands'],
        ['text' => '📖 نحوه استفاده', 'callback_data' => 'help_usage'],
    ],
    [
        ['text' => '📊 تخمین', 'callback_data' => 'help_estimate'],
        ['text' => '📧 گزارش', 'callback_data' => 'help_report'],
    ],
    [
        ['text' => '❓ سوالات متداول', 'callback_data' => 'help_faq'],
    ],
    [
        ['text' => '🏠 بازگشت به منو', 'callback_data' => 'back_to_menu'],
    ]
];
```

### کیبورد زیر هر Help

```php
$keyboard = [
    [
        ['text' => '◀️ بازگشت به راهنما', 'callback_data' => 'help_main'],
        ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_to_menu'],
    ]
];
```

## 📝 کلیدهای ترجمه

### فارسی (`lang/fa/bot.php`)

```php
// Help اصلی
'help_main_title' => '🕌 راهنمای ربات نماز قضا',
'help_main_welcome' => 'به ربات نماز قضا خوش آمدید! این ربات به شما کمک می‌کند تا:',
'help_main_features' => "✅ نمازهای قضای خود را ثبت کنید\n📊 تخمین نمازهای قضا را مدیریت کنید\n📧 گزارش هفتگی دریافت کنید\n📈 پیشرفت خود را پیگیری کنید",
'help_main_select' => '📚 برای اطلاعات بیشتر، یکی از موارد زیر را انتخاب کنید:',
'help_main_quick_start' => '💡 برای شروع سریع: فقط عدد 2، 3 یا 4 ارسال کنید!',

// دکمه‌ها
'help_btn_commands' => '📋 دستورات',
'help_btn_usage' => '📖 نحوه استفاده',
'help_btn_estimate' => '📊 تخمین',
'help_btn_report' => '📧 گزارش ایمیل',
'help_btn_faq' => '❓ سوالات متداول',
'help_btn_back_to_help' => '◀️ بازگشت به راهنما',
'help_btn_back_to_menu' => '🏠 منوی اصلی',

// Help دستورات
'help_commands_title' => '📋 لیست دستورات',
'help_commands_main' => "🔹 دستورات اصلی:\n• /start - شروع کار با ربات\n• /help - نمایش راهنما\n• /stats - مشاهده آمار",
'help_commands_record' => "🔹 ثبت نماز:\n• ارسال عدد 2 - ثبت نماز صبح (2 رکعت)\n• ارسال عدد 3 - ثبت نماز مغرب (3 رکعت)\n• ارسال عدد 4 - ثبت نماز ظهر/عصر/عشا (4 رکعت)\n• /remove_[ID] - حذف نماز ثبت شده",
'help_commands_estimate' => "🔹 تخمین:\n• /estimate - ثبت تخمین نمازهای قضا\n• /estimate_status - مشاهده تخمین فعلی",
'help_commands_report' => "🔹 گزارش:\n• /set_email - تنظیم ایمیل برای گزارش\n• /email_settings - تنظیمات ایمیل\n• /unsubscribe - لغو اشتراک ایمیل",
'help_commands_help' => "🔹 راهنما:\n• /help_usage - نحوه استفاده\n• /help_estimate - راهنمای تخمین\n• /help_report - راهنمای گزارش\n• /help_faq - سوالات متداول",
'help_commands_tip' => '💡 نکته: می‌توانید به جای ارسال دستور، از دکمه‌های زیر پیام استفاده کنید.',

// Help نحوه استفاده
'help_usage_title' => '📖 نحوه استفاده از ربات',
'help_usage_content' => "🔸 مرحله 1: ثبت نماز\nبرای ثبت نماز قضا، کافی است تعداد رکعات را ارسال کنید:\n\n• 2 → نماز صبح (2 رکعت)\n• 3 → نماز مغرب (3 رکعت)\n• 4 → نماز ظهر، عصر یا عشا (4 رکعت)\n\n🔸 مرحله 2: مشاهده آمار\nبا دستور /stats می‌توانید آمار خود را ببینید.\n\n🔸 مرحله 3: ثبت تخمین (اختیاری)\nبا /estimate تخمین نمازهای قضای خود را ثبت کنید.\n\n🔸 مرحله 4: دریافت گزارش (اختیاری)\nبا /set_email گزارش هفتگی دریافت کنید.\n\n💡 نکته: ربات به صورت هوشمند نوع نماز را تشخیص می‌دهد.",

// Help تخمین
'help_estimate_title' => '📊 راهنمای سیستم تخمین',
'help_estimate_what' => '🎯 تخمین چیست؟\nتخمین، مجموع نمازهای قضایی است که فکر می‌کنید دارید.',
'help_estimate_how' => "📝 نحوه ثبت تخمین:\n\n1️⃣ دستور /estimate را ارسال کنید\n2️⃣ واحد مورد نظر را انتخاب کنید\n3️⃣ عدد را وارد کنید",
'help_estimate_calc' => "🔢 محاسبات:\n• هر روز = 17 رکعت\n• هر هفته = 119 رکعت\n• هر ماه = 510 رکعت\n• هر سال = 6205 رکعت",
'help_estimate_tip' => '💡 نکته: تخمین جدید جایگزین تخمین قبلی می‌شود.',

// Help گزارش
'help_report_title' => '📧 راهنمای سیستم گزارش ایمیل',
'help_report_what' => '📬 گزارش هفتگی چیست؟\nهر هفته یک ایمیل حاوی آمار و پیشرفت شما.',
'help_report_how' => "⚙️ نحوه فعال‌سازی:\n\n1️⃣ /set_email\n2️⃣ وارد کردن ایمیل\n3️⃣ تایید با کد 6 رقمی",
'help_report_settings' => "🔧 تنظیمات:\n• /email_settings - مشاهده\n• /set_email - تغییر\n• /unsubscribe - لغو",
'help_report_time' => '📅 زمان ارسال: هر یکشنبه صبح ساعت 9',
'help_report_security' => '🔒 ایمیل شما محفوظ و امن است.',

// Help FAQ
'help_faq_title' => '❓ سوالات متداول (FAQ)',
'help_faq_content' => "🔹 چگونه نماز ثبت کنم؟\nفقط عدد 2، 3 یا 4 را ارسال کنید.\n\n🔹 اگر اشتباه ثبت کردم؟\nاز /remove_[ID] استفاده کنید.\n\n🔹 آیا باید تخمین ثبت کنم؟\nخیر، اختیاری است.\n\n🔹 گزارش چه زمانی ارسال می‌شود؟\nهر یکشنبه صبح ساعت 9\n\n🔹 ربات رایگان است؟\nبله، کاملاً رایگان!\n\n💡 سوال دیگری دارید؟\nبا پشتیبانی تماس بگیرید.",
```

## 🔧 پیاده‌سازی در Controller

```php
// در PrayerBotController

// دستور /help
if ($text === '/help' || $text === '/start') {
    return $this->showHelpMain($bot, $chatId);
}

// دستورات help خاص
if (str_starts_with($text, '/help_')) {
    $helpType = str_replace('/help_', '', $text);
    return $this->showHelp($bot, $chatId, $helpType);
}

// Callback برای help
if (str_starts_with($callbackData, 'help_')) {
    $helpType = str_replace('help_', '', $callbackData);
    return $this->showHelp($bot, $chatId, $helpType, $messageId);
}

// متدها:

private function showHelpMain($bot, int $chatId, ?int $messageId = null)
{
    $message = trans('bot.help_main_title') . "\n\n";
    $message .= trans('bot.help_main_welcome') . "\n\n";
    $message .= trans('bot.help_main_features') . "\n\n";
    $message .= trans('bot.help_main_select');
    
    $keyboard = [
        [
            ['text' => trans('bot.help_btn_commands'), 'callback_data' => 'help_commands'],
            ['text' => trans('bot.help_btn_usage'), 'callback_data' => 'help_usage'],
        ],
        [
            ['text' => trans('bot.help_btn_estimate'), 'callback_data' => 'help_estimate'],
            ['text' => trans('bot.help_btn_report'), 'callback_data' => 'help_report'],
        ],
        [
            ['text' => trans('bot.help_btn_faq'), 'callback_data' => 'help_faq'],
        ]
    ];
    
    if ($messageId) {
        $bot->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }
    
    return response()->json(['status' => 'ok'], 200);
}

private function showHelp($bot, int $chatId, string $type, ?int $messageId = null)
{
    $message = match($type) {
        'commands' => $this->getHelpCommands(),
        'usage' => $this->getHelpUsage(),
        'estimate' => $this->getHelpEstimate(),
        'report' => $this->getHelpReport(),
        'faq' => $this->getHelpFaq(),
        default => trans('bot.help_main_title')
    };
    
    $keyboard = [
        [
            ['text' => trans('bot.help_btn_back_to_help'), 'callback_data' => 'help_main'],
        ]
    ];
    
    if ($messageId) {
        $bot->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }
    
    return response()->json(['status' => 'ok'], 200);
}

private function getHelpCommands(): string
{
    return trans('bot.help_commands_title') . "\n\n" .
           trans('bot.help_commands_main') . "\n\n" .
           trans('bot.help_commands_record') . "\n\n" .
           trans('bot.help_commands_estimate') . "\n\n" .
           trans('bot.help_commands_report') . "\n\n" .
           trans('bot.help_commands_help') . "\n\n" .
           trans('bot.help_commands_tip');
}

private function getHelpUsage(): string
{
    return trans('bot.help_usage_title') . "\n\n" .
           trans('bot.help_usage_content');
}

private function getHelpEstimate(): string
{
    return trans('bot.help_estimate_title') . "\n\n" .
           trans('bot.help_estimate_what') . "\n\n" .
           trans('bot.help_estimate_how') . "\n\n" .
           trans('bot.help_estimate_calc') . "\n\n" .
           trans('bot.help_estimate_tip');
}

private function getHelpReport(): string
{
    return trans('bot.help_report_title') . "\n\n" .
           trans('bot.help_report_what') . "\n\n" .
           trans('bot.help_report_how') . "\n\n" .
           trans('bot.help_report_settings') . "\n\n" .
           trans('bot.help_report_time') . "\n\n" .
           trans('bot.help_report_security');
}

private function getHelpFaq(): string
{
    return trans('bot.help_faq_title') . "\n\n" .
           trans('bot.help_faq_content');
}
```

---

**تاریخ:** 2026-01-06  
**وضعیت:** طراحی کامل - آماده پیاده‌سازی
