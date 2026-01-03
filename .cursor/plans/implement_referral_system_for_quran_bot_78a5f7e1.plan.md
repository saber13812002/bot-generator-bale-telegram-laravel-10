---
name: Implement Referral System for Quran Bot
overview: "پیاده‌سازی سیستم دعوت برای ربات قرآن شامل: ردیابی دعوت‌شدگان، محاسبه آمار روزانه، ساخت لینک دعوت اختصاصی، و نمایش آمار دعوت‌شدگان"
todos: []
---

#پیاده‌سازی سیستم دعوت برای ربات قرآن

## هدف

پیاده‌سازی سیستم دعوت (Referral System) برای ربات قرآن شامل:

1. ردیابی کاربرانی که توسط کاربر دعوت شده‌اند
2. محاسبه و نمایش آمار روزانه (تعداد کاربران و دور کامل قرآن)
3. ساخت لینک دعوت اختصاصی
4. نمایش آمار دعوت‌شدگان
5. دستور دعوت با پیام پیش‌فرض

## تغییرات مورد نیاز

### 1. Migration برای اضافه کردن فیلد `invited_by`

فایل جدید: `database/migrations/YYYY_MM_DD_HHMMSS_add_invited_by_to_bot_users_table.php`

- اضافه کردن فیلد `invited_by` (nullable, bigInteger) به جدول `bot_users`
- اضافه کردن index برای بهبود عملکرد

### 2. به‌روزرسانی Model `BotUsers`

در `app/Models/BotUsers.php`:

- اضافه کردن relationship برای دعوت‌کننده: `inviter()`
- اضافه کردن relationship برای دعوت‌شدگان: `invitees()`
- متد `getInvitationLink()` برای ساخت لینک دعوت

### 3. پردازش `/start` با پارامتر دعوت

در `app/Http/Controllers/QuranWordController.php`:

- بررسی پارامتر `start` در دستور `/start`
- اگر پارامتر وجود داشت، ذخیره `invited_by` در `bot_users`
- استفاده از `BotHelper::getCommandRefferralWhenStart()` برای پارس کردن پارامتر

### 4. محاسبه آمار روزانه

متد جدید در `app/Services/QuranBotUserRankingServiceImpl.php`:

- `getDailyStatistics()`: محاسبه تعداد کاربران و آیات خوانده شده در روز گذشته
- محاسبه تعداد دور کامل: `total_ayahs / 6236`
- کش کردن آمار برای 24 ساعت

### 5. محاسبه آمار دعوت‌شدگان

متد جدید در `app/Services/QuranBotUserRankingServiceImpl.php`:

- `getReferralStatistics($chatId)`: محاسبه آمار دعوت‌شدگان یک کاربر
- تعداد دعوت‌شدگان
- تعداد آیات خوانده شده توسط دعوت‌شدگان در 7 روز گذشته

### 6. ساخت لینک دعوت اختصاصی

متد جدید در `app/Helpers/QuranHelper.php`:

- `getInvitationLink($chatId, $type)`: ساخت لینک دعوت
- برای Bale: `https://ble.ir/[bot_username]?start=[chat_id]`
- برای Telegram: `https://t.me/[bot_username]?start=[chat_id]`
- دریافت bot username از دیتابیس یا getMe API

### 7. دستور دعوت

در `app/Http/Controllers/QuranWordController.php`:

- دستور `/invite` یا `/دعوت`: نمایش پیام دعوت و لینک
- دکمه "رونوشت لینک" برای کپی لینک
- دکمه "لبیک به دعوت" (برای دعوت‌شدگان)

### 8. پیام آماری روزانه

متد جدید در `app/Services/QuranBotUserRankingServiceImpl.php`:

- `getDailyStatisticsMessage()`: ساخت پیام آماری روز گذشته
- فرمت: "در روز گذشته به همراه X نفر، مجموعاً حدود Y دور کامل قرآن رو قرائت کرده‌اید."

### 9. پیام آمار دعوت‌شدگان

متد جدید در `app/Services/QuranBotUserRankingServiceImpl.php`:

- `getReferralStatisticsMessage($chatId)`: ساخت پیام آمار دعوت‌شدگان
- فرمت: "طی ۷ روز گذشته شما و X فرد دعوت‌شده توسط شما، مجموعاً Y آیه رو قرائت کرده‌اید."

### 10. کلیدهای ترجمه

در همه فایل‌های `lang/{locale}/bot.php`:

- `'yesterday with users you read rounds'` - در روز گذشته به همراه X نفر، مجموعاً حدود Y دور کامل قرآن رو قرائت کرده‌اید
- `'referral statistics message'` - طی ۷ روز گذشته شما و X فرد دعوت‌شده توسط شما، مجموعاً Y آیه رو قرائت کرده‌اید
- `'how to invite others'` - چطوری از دیگران دعوت کنم؟
- `'just forward this message'` - کافیه همین پیام رو فوروارد کنین!
- `'invitation message'` - پیام دعوت پیش‌فرض (با آیه و توضیحات)
- `'accept invitation'` - لبیک به دعوت
- `'copy invitation link'` - رونوشت لینک اختصاصی
- `'complete quran rounds'` - دور کامل قرآن
- `'invited users'` - کاربران دعوت‌شده
- `'invited users count'` - تعداد کاربران دعوت‌شده
- `'invited users active last 7 days'` - کاربران دعوت‌شده فعال در 7 روز گذشته

### 11. تست‌های SQL و Shell

#### فایل SQL تست

فایل جدید: `database/sql_tests/referral_system_tests.sql`

- شامل 10 کوئری SQL برای تست و بررسی سیستم دعوت
- قابل اجرا در MySQL/MariaDB
- شامل کوئری‌های بررسی لاگ‌ها، آمار دعوت‌شدگان، آمار روزانه و هفتگی

#### تست‌های Shell Command

1. **بررسی تعداد لاگ‌های `/start` با پارامتر:**
```bash
php artisan tinker --execute="
\$count = \App\Models\BotLog::where('text', 'like', '/start %')
    ->where('is_command', true)
    ->whereWebhookEndpointUri('webhook-quran-word')
    ->count();
echo 'تعداد لاگ‌های /start با پارامتر: ' . \$count . PHP_EOL;
"
```




2. **بررسی تعداد کاربرانی که `invited_by` دارند:**
```bash
php artisan tinker --execute="
\$count = \App\Models\BotUsers::whereNotNull('invited_by')->count();
echo 'تعداد کاربران با invited_by: ' . \$count . PHP_EOL;
"
```




3. **بررسی آمار روز گذشته:**
```bash
php artisan tinker --execute="
\$yesterday = \Carbon\Carbon::yesterday()->startOfDay();
\$stats = \App\Models\BotLog::where('webhook_endpoint_uri', 'webhook-quran-word')
    ->where('is_command', true)
    ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
    ->where('created_at', '>=', \$yesterday)
    ->selectRaw('COUNT(DISTINCT chat_id) as unique_users, COUNT(*) as total_ayahs')
    ->first();
echo 'کاربران منحصر به فرد: ' . \$stats->unique_users . PHP_EOL;
echo 'تعداد کل آیات: ' . \$stats->total_ayahs . PHP_EOL;
echo 'دور کامل: ' . floor(\$stats->total_ayahs / 6236) . PHP_EOL;
"
```




4. **بررسی آمار دعوت‌شدگان یک کاربر خاص:**
```bash
# این را با chat_id واقعی جایگزین کنید
php artisan tinker --execute="
\$chatId = '123456789'; // جایگزین کنید
\$invitees = \App\Models\BotUsers::where('invited_by', \$chatId)->pluck('chat_id');
echo 'تعداد دعوت‌شدگان: ' . \$invitees->count() . PHP_EOL;
\$activeInvitees = \App\Models\BotLog::whereIn('chat_id', \$invitees)
    ->where('created_at', '>=', now()->subDays(7))
    ->whereWebhookEndpointUri('webhook-quran-word')
    ->where('is_command', true)
    ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
    ->distinct('chat_id')
    ->count('chat_id');
echo 'دعوت‌شدگان فعال در 7 روز گذشته: ' . \$activeInvitees . PHP_EOL;
"
```




## فایل‌های مورد تغییر

1. **Migration جدید**: `database/migrations/YYYY_MM_DD_HHMMSS_add_invited_by_to_bot_users_table.php`
2. **`app/Models/BotUsers.php`**: اضافه کردن relationships و متدها
3. **`app/Http/Controllers/QuranWordController.php`**: پردازش `/start` با پارامتر و دستور `/invite`
4. **`app/Services/QuranBotUserRankingServiceImpl.php`**: متدهای محاسبه آمار
5. **`app/Helpers/QuranHelper.php`**: متد ساخت لینک دعوت
6. **همه فایل‌های `lang/{locale}/bot.php`**: اضافه کردن کلیدهای ترجمه

## منطق محاسبه

### تعداد دور کامل قرآن:

```php
$totalAyahs = BotLog::where('created_at', '>=', yesterday)
    ->whereWebhookEndpointUri('webhook-quran-word')
    ->count();
$completeRounds = floor($totalAyahs / 6236);
```



### آمار دعوت‌شدگان:

```php
$invitees = BotUsers::where('invited_by', $chatId)->pluck('chat_id');
$inviteesAyahs = BotLog::whereIn('chat_id', $invitees)
    ->where('created_at', '>=', now()->subDays(7))
    ->whereWebhookEndpointUri('webhook-quran-word')
    ->count();
```



## نکات مهم

1. قرآن 6236 آیه دارد (عدد ثابت)
2. لینک دعوت باید dynamic باشد (بر اساس نوع ربات)
3. bot username باید از دیتابیس (جدول `bots`) یا getMe API گرفته شود
4. آمار روزانه باید کش شود (24 ساعت)
5. همه متن‌ها باید از سیستم ترجمه استفاده کنند