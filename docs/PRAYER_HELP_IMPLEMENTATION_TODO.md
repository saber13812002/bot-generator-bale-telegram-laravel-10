# TODO: پیاده‌سازی سیستم Help ربات نماز قضا

## ✅ کارهای انجام شده

1. ✅ مستندسازی کامل: `docs/features/PRAYER_BOT_HELP_SYSTEM.md`
2. ✅ ترجمه‌های فارسی: `lang/fa/bot.php` (60+ کلید ترجمه)

---

## 🚧 کارهای باقیمانده

### مرحله 1: اضافه کردن ترجمه‌ها به سایر زبان‌ها

**فایل‌ها:**
- `lang/en/bot.php`
- `lang/ar-IQ/bot.php`
- `lang/az/bot.php`
- `lang/bs/bot.php`
- `lang/de-DE/bot.php`
- `lang/es/bot.php`
- `lang/fr/bot.php`
- `lang/he/bot.php`
- `lang/pt-BR/bot.php`
- `lang/pt-PT/bot.php`
- `lang/ru/bot.php`
- `lang/tr/bot.php`
- `lang/ur/bot.php`
- `lang/zh-CN/bot.php`

**نکته:** می‌توانید از ترجمه خودکار استفاده کنید یا فقط انگلیسی را اضافه کنید و بقیه را بعداً.

### مرحله 2: پیاده‌سازی در Controller

فایل: `app/Http/Controllers/PrayerBotController.php`

#### 2.1. اضافه کردن دستورات Help

```php
// در متد webhook، بعد از چک کردن /start:

// دستور /help
if ($text === '/help') {
    return $this->showHelpMain($bot, $chatId);
}

// دستورات help خاص
if (str_starts_with($text, '/help_')) {
    $helpType = str_replace('/help_', '', $text);
    return $this->showHelp($bot, $chatId, $helpType);
}
```

#### 2.2. اضافه کردن Callback Handler

```php
// در بخش callback_query:

if (str_starts_with($callbackData, 'help_')) {
    return $this->handleHelpCallback($bot, $callbackQuery, $type);
}
```

#### 2.3. اضافه کردن متدهای Help

```php
/**
 * نمایش Help اصلی
 */
private function showHelpMain($bot, int $chatId, ?int $messageId = null)
{
    $message = trans('bot.help_main_title') . "\n\n";
    $message .= trans('bot.help_main_welcome') . "\n\n";
    $message .= trans('bot.help_main_features') . "\n\n";
    $message .= trans('bot.help_main_select') . "\n\n";
    $message .= trans('bot.help_main_quick_start');
    
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

/**
 * نمایش Help خاص
 */
private function showHelp($bot, int $chatId, string $type, ?int $messageId = null)
{
    $message = match($type) {
        'commands' => $this->getHelpCommands(),
        'usage' => $this->getHelpUsage(),
        'estimate' => $this->getHelpEstimate(),
        'report' => $this->getHelpReport(),
        'faq' => $this->getHelpFaq(),
        'main' => null, // برای بازگشت به منوی اصلی
        default => trans('bot.help_main_title')
    };
    
    // اگر main بود، نمایش Help اصلی
    if ($type === 'main') {
        return $this->showHelpMain($bot, $chatId, $messageId);
    }
    
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

/**
 * Handle Help Callback
 */
private function handleHelpCallback($bot, array $callbackQuery, string $type)
{
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $callbackData = $callbackQuery['data'];
    
    // استخراج نوع help
    $helpType = str_replace('help_', '', $callbackData);
    
    // Answer callback query
    $bot->answerCallbackQuery([
        'callback_query_id' => $callbackQuery['id']
    ]);
    
    // نمایش help
    return $this->showHelp($bot, $chatId, $helpType, $messageId);
}

/**
 * متن Help دستورات
 */
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

/**
 * متن Help نحوه استفاده
 */
private function getHelpUsage(): string
{
    return trans('bot.help_usage_title') . "\n\n" .
           trans('bot.help_usage_content');
}

/**
 * متن Help تخمین
 */
private function getHelpEstimate(): string
{
    return trans('bot.help_estimate_title') . "\n\n" .
           trans('bot.help_estimate_what') . "\n\n" .
           trans('bot.help_estimate_how') . "\n\n" .
           trans('bot.help_estimate_calc') . "\n\n" .
           trans('bot.help_estimate_progress') . "\n\n" .
           trans('bot.help_estimate_tip');
}

/**
 * متن Help گزارش
 */
private function getHelpReport(): string
{
    return trans('bot.help_report_title') . "\n\n" .
           trans('bot.help_report_what') . "\n\n" .
           trans('bot.help_report_how') . "\n\n" .
           trans('bot.help_report_settings') . "\n\n" .
           trans('bot.help_report_time') . "\n\n" .
           trans('bot.help_report_security') . "\n\n" .
           trans('bot.help_report_tip');
}

/**
 * متن Help سوالات متداول
 */
private function getHelpFaq(): string
{
    return trans('bot.help_faq_title') . "\n\n" .
           trans('bot.help_faq_content');
}
```

### مرحله 3: تست

```bash
# 1. Cache
php artisan cache:clear
php artisan config:clear

# 2. تست دستورات
/help
/help_commands
/help_usage
/help_estimate
/help_report
/help_faq

# 3. تست Callback ها
# کلیک روی دکمه‌های Help
```

---

## 📋 چک‌لیست

- [ ] ترجمه‌های فارسی اضافه شده ✅
- [ ] ترجمه‌های انگلیسی اضافه شده
- [ ] متدهای Help به Controller اضافه شده
- [ ] دستورات /help و /help_* پیاده‌سازی شده
- [ ] Callback handler برای help_* پیاده‌سازی شده
- [ ] تست با ربات انجام شده
- [ ] دکمه‌ها کار می‌کنند
- [ ] بازگشت به منوی اصلی کار می‌کند
- [ ] لاگ بررسی شده

---

## 🎯 نکات مهم

1. **Callback Query**: حتماً `answerCallbackQuery` را صدا بزنید
2. **Edit vs Send**: برای callback از `editMessageText` استفاده کنید
3. **Keyboard**: همیشه دکمه بازگشت داشته باشید
4. **Translations**: ابتدا فقط فارسی و انگلیسی کافی است
5. **Testing**: همه دکمه‌ها و دستورات را تست کنید

---

## 📚 ترجمه انگلیسی (نمونه)

فایل: `lang/en/bot.php`

```php
// Help System
'help_main_title' => '🕌 Prayer Qadha Bot Help',
'help_main_welcome' => 'Welcome to Prayer Qadha Bot! This bot helps you to:',
'help_main_features' => "✅ Record your Qadha prayers\n📊 Manage prayer estimates\n📧 Receive weekly reports\n📈 Track your progress",
'help_main_select' => '📚 For more information, select one of the following:',
'help_main_quick_start' => '💡 Quick start: Just send 2, 3 or 4!',

'help_btn_commands' => '📋 Commands',
'help_btn_usage' => '📖 How to Use',
'help_btn_estimate' => '📊 Estimate',
'help_btn_report' => '📧 Email Report',
'help_btn_faq' => '❓ FAQ',
'help_btn_back_to_help' => '◀️ Back to Help',
'help_btn_back_to_menu' => '🏠 Main Menu',

// ... بقیه ترجمه‌ها
```

---

## 🚀 اولویت‌بندی

1. **فوری**: پیاده‌سازی در Controller (مرحله 2)
2. **مهم**: ترجمه انگلیسی (مرحله 1 - فقط en)
3. **عادی**: ترجمه سایر زبان‌ها (مرحله 1 - بقیه)

---

**تاریخ:** 2026-01-06  
**وضعیت:** آماده پیاده‌سازی  
**زمان تخمینی:** 2-3 ساعت
