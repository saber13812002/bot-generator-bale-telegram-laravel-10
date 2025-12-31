---
name: Improve Message Formatting with Icons and Translations
overview: بهبود فرمت پیام‌ها در QuranWordController و QuranBotUserRankingServiceImpl با اضافه کردن آیکون‌ها و تبدیل متن‌های hard-coded به سیستم ترجمه Laravel
todos: []
---

# بهبود فرمت پیام‌ها با آیکون و ترجمه

## هدف

بهبود فرمت پیام‌های ارسالی در ربات قرآن با:

1. تبدیل متن‌های hard-coded به سیستم ترجمه Laravel
2. اضافه کردن آیکون‌های مناسب به پیام‌ها
3. بهبود ساختار و خوانایی پیام‌ها

## تغییرات مورد نیاز

### 1. اضافه کردن کلیدهای ترجمه جدید

در همه فایل‌های `lang/{locale}/bot.php`:

- `'this command not work in telegram'` - این دستور در تلگرام کار نمی‌کند
- `'you are not admin'` - شما ادمین نیستید
- `'statistics report'` - گزارش آمار
- `'daily statistics'` - آمار روزانه
- `'weekly statistics'` - آمار هفتگی
- `'monthly statistics'` - آمار ماهانه
- `'yearly statistics'` - آمار سالانه
- `'total ayah'` - تعداد کل آیه
- `'unique users'` - کاربران منحصر به فرد

### 2. تغییر `QuranWordController.php`

در خطوط 304-314:

- تبدیل `"this command not work in telegram"` به `trans("bot.this command not work in telegram")` با آیکون
- تبدیل `"you are not admin"` به `trans("bot.you are not admin")` با آیکون

### 3. تغییر `QuranBotUserRankingServiceImpl.php`

در متد `allUsersReportDailyWeeklyMonthly`:

- بهبود فرمت پیام آماری با آیکون‌ها
- استفاده از ترجمه برای همه متن‌ها
- ساختار بهتر با جداکننده‌ها و emoji

## فایل‌های مورد تغییر

1. `app/Http/Controllers/QuranWordController.php`

- خطوط 304-314: تبدیل پیام‌های hard-coded به ترجمه با آیکون

2. `app/Services/QuranBotUserRankingServiceImpl.php`

- متد `allUsersReportDailyWeeklyMonthly`: بهبود فرمت پیام آماری

3. همه فایل‌های `lang/{locale}/bot.php` (15 زبان)

- اضافه کردن کلیدهای ترجمه جدید

## فرمت پیشنهادی پیام‌ها

### پیام خطا در تلگرام:

```javascript
❌ این دستور در تلگرام کار نمی‌کند
```



### پیام عدم دسترسی:

```javascript
🚫 شما ادمین نیستید
```



### گزارش آماری:

```javascript
📊 گزارش آمار ربات قرآن

📅 آمار روزانه:
📖 تعداد کل آیه: 150
👥 کاربران منحصر به فرد: 25

📆 آمار هفتگی:
📖 تعداد کل آیه: 1050
👥 کاربران منحصر به فرد: 180

📆 آمار ماهانه:
📖 تعداد کل آیه: 4500
👥 کاربران منحصر به فرد: 320

📆 آمار سالانه:
📖 تعداد کل آیه: 54000
👥 کاربران منحصر به فرد: 500
```



## نکات مهم

1. همه متن‌ها باید از سیستم ترجمه استفاده کنند
2. آیکون‌ها باید مناسب و معنادار باشند
3. فرمت باید خوانا و منظم باشد