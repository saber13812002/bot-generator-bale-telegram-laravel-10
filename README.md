<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# About this project


1- This project use php and laravel to have one base code for all messengers like Bale Telegram Gap Eitaa 

<p dir="rtl">
1- این پروژه از پی اچ پی و لاراول استفاده شده. هدف اول این است که یک سورس کد برای ارتباط با همه پیام رسان ها داشته باشیم
یک سورس برای ارتباط با پیام رسان بله سروش ایتا تلگرام گپ.
</p>

2- Messenger bots is most valuable and important for people for learn and educate

<p dir="rtl">
2- هدف دوم استفاده از روبات های تعاملی پیام رسانی ها با هدف آموزش و ارتقای دانش است
</p>

3- Bot Mother is Bot Generator that help you to create many bots with BotFather and get token. then send this token for my bot father and create your bots.

list of Bot Types that you can clone it, is in next block of this document

<p dir="rtl">
3- روبات مادر میتواند برای کاربران ما هزاران روبات بسازد. در حقیقت این پلت فرم یک روبات ساز است که شما میتوانید از روبات های ما برای ساخت روبات برای خودتان استفاده کنید. کافیست از بات فادر در تلگرام و بله و سروش و گپ و ایتا توکن بگیرید و به روبات ما بدهید و نوع روبات خود را انتخاب کنید

لیست روبات ها در بخش بعدی آورده شده است
</p>

4- Collaboration Content Generate Platform  

<p dir="rtl">
4- هدف چهارم تولید محتوا با استفاده از کاربران است.

تولید محتوا در زمینه هایی که در جامعه نیاز به تفکر و تدبر و آموزش است.

سوالات مردم و پرسش و پاسخ و درست کردن یک شبه دانشگاه مجازی در بستر روبات
</p>

5- one platform that connect to all other users need

<p dir="rtl">
5- یک بستر برای مراجعات کاربر برای یادآوری و پیگیری و مدیریت همه ابزار هایی که باید در طول روز به آنها سر بزند
</p>


# 📚 مستندات توسعه

## ⚠️ مهم: قبل از ساخت ربات جدید

**حتماً** این سند را مطالعه کنید: [**راهنمای کامل ساخت ربات جدید**](./docs/BOT_CREATION_GUIDE.md)

این سند شامل:
- ✅ تمام اصول و قوانین ساخت ربات
- ✅ خطاهای رایج و راه حل آن‌ها
- ✅ چک‌لیست کامل قبل از deploy
- ✅ الگوهای استاندارد Controller, Service, Repository
- ✅ نحوه صحیح کار با Webhook و Token
- ✅ راهنمای مستندسازی

برای اطلاعات بیشتر در مورد نحوه توسعه، به [README-DEVELOP.md](README-DEVELOP.md) مراجعه کنید.

# 🚀 فیچرهای مهم پروژه

## 🤖 سیستم ربات مادر (Bot Mother)
- ساخت و مدیریت هزاران ربات برای کاربران مختلف
- پشتیبانی از چندین پیام‌رسان: تلگرام، بله، گپ، ایتا، سروش
- سیستم مدیریت توکن و وب‌هوک خودکار
- رابط یکپارچه برای تمام پیام‌رسان‌ها

## 📖 ربات قرآن (Quran Bot)
- مطالعه قرآن به صورت آیه به آیه، کلمه به کلمه، صفحه به صفحه
- جستجوی پیشرفته در کل قرآن با فول‌تکست
- ترجمه به زبان‌های مختلف (فارسی، انگلیسی، فرانسوی، اسپانیایی، ترکی و...)
- فایل‌های صوتی قرائت عربی و فارسی
- سیستم حفظ قرآن و ختم قرآن
- نمایش صفحات اسکن شده قرآن
- مدیریت جزو و سوره‌ها
- **سیستم مدیریت فایل‌های آپلود شده:** جلوگیری از آپلود مجدد فایل‌های یکسان و بهینه‌سازی سرعت

## 🌤️ ربات هواشناسی (Weather Bot)
- دریافت اطلاعات هواشناسی از API های مختلف (OpenWeatherMap, Tomorrow.io)
- تنظیم هشدار برای تغییرات دما و سرعت باد
- ارسال خودکار اطلاعیه‌ها در گروه‌ها
- پیش‌بینی آب و هوا برای 16 ساعت آینده

## 📰 سیستم RSS و انتشار محتوا
- دریافت و پردازش فیدهای RSS از منابع مختلف
- ترجمه خودکار محتوا به زبان‌های مختلف
- انتشار خودکار در پیام‌رسان‌ها
- مدیریت کانال‌های RSS و دسته‌بندی محتوا
- سیستم صف برای ترجمه و انتشار

## 📱 ربات شبکه‌های اجتماعی (Social Bot)
- انتشار خودکار محتوا در توییتر، فیس‌بوک، لینکدین، اینستاگرام
- مدیریت یکپارچه انتشار در تمام پلتفرم‌ها
- پشتیبانی از Chrome Extension برای ارسال محتوا

## 📚 ربات حدیث (Hadith Bot)
- جستجوی پیشرفته در کتب حدیث شیعه
- نمایش تاریخچه جستجوهای کاربران
- ارسال حدیث تصادفی

## 📜 ربات نهج البلاغه (Nahj Bot)
- جستجو در کل متن نهج البلاغه
- نمایش فهرست و آیتم‌های مختلف

## 🕌 ربات نماز قضا (Prayer Qadha Bot)
- ثبت آسان رکعات نماز قضا (2، 3، 4)
- تشخیص هوشمند نوع نماز (صبح، ظهر، عصر، مغرب، عشا)
- مدیریت و حذف رکعات ثبت شده
- ثبت تخمین نمازهای قضا و ردیابی پیشرفت
- گزارش‌دهی خودکار هفتگی از طریق ایمیل
- پشتیبانی کامل از تلگرام و بله
- **صفحه گزارش وب** با نمایش تاریخ شمسی و قمری
- **پشتیبانی چندزبانه** برای ایمیل‌های گزارش
- **دکمه راهنما** برای دریافت لینک گزارش از طریق ربات
- **[📖 راهنمای کامل و تنظیمات](./docs/features/PRAYER_BOT_README.md)** - شامل تنظیمات Gmail، Laravel، دستورات و نحوه کار
- [مستندات کامل](./docs/features/PRAYER_QADHA_BOT.md)
- [بهبودهای صفحه گزارش وب و ایمیل](./docs/features/PRAYER_BOT_WEB_REPORT_ENHANCEMENT.md) - تاریخ شمسی/قمری، چندزبانه، دکمه راهنما

## 🧠 ربات تست روانشناسی (Psychology Test Bot)
- ایجاد و برگزاری تست‌های روانشناسی
- سوالات 5 گزینه‌ای با دسته‌بندی و وزن
- محاسبه دقیق امتیازات

## 📝 ربات محتوای متنی / عکس / فیلم (Content Submission Bot)
- دریافت محتوا (متن، عکس، ویدیو) از کاربران در خصوصی
- ارسال به گروه تایید و تایید با ریپلای «۱» (پشتیبانی از تایید یک یا دو نفره)
- انتشار در کانال و گزارش به فرستنده
- تنظیم کانال و گروه از طریق ویزارد ربات مادر (فوروارد پیام از کانال/گروه)
- **[راهنمای تنظیم و تست ربات محتوای متنی](./docs/features/CONTENT_SUBMISSION_BOT.md)**

## 📊 آمار و مدیریت ربات مادر
- مشاهده آمار کامل ربات مادر و ربات‌های ساخته شده
- آمار ویژه برای ربات‌های قرآنی (کاربران، آیات، تعاملات)
- ارسال پیام همگانی به کاربران بر اساس زبان
- پشتیبانی از 18 زبان برای ربات قرآن

## 🌍 زبان‌های پشتیبانی شده
- ربات قرآن از 18 زبان پشتیبانی می‌کند شامل: فارسی، انگلیسی، عربی، ایتالیایی، اندونزیایی، سواحیلی و...
- [مستندات زبان‌های اضافی](./docs/features/ADDITIONAL_LANGUAGES_QURAN_BOT.md)

## 📊 آمار و مدیریت ربات مادر
- مشاهده آمار کامل ربات مادر و ربات‌های ساخته شده
- آمار ویژه برای ربات‌های قرآنی (کاربران، آیات، تعاملات)
- ارسال پیام همگانی به کاربران بر اساس زبان
- [مستندات آمار ربات مادر](./docs/features/BOT_MOTHER_STATISTICS.md)
- [مستندات ارسال پیام همگانی](./docs/features/MESSAGE_BROADCAST.md)
- [مستندات ایمپورت ترجمه‌های قرآن](./docs/features/QURAN_TRANSLATION_IMPORT.md)
- [چک لیست تست](./docs/features/TEST_CHECKLIST.md)

## 🎵 سیستم Song Sara
- مدیریت موسیقی و پلی‌لیست‌ها
- دسته‌بندی بر اساس هنرمند، کشور، ژانر، ساز، حال و هوا
- انتشار محتوا در RSS

## 📚 کتاب‌های صوتی (Audio Books)
- مدیریت و انتشار کتاب‌های صوتی
- سیستم شناسه‌گذاری خودکار

## 🔧 سیستم‌های پشتیبان
- **Translation Service**: ترجمه خودکار با پشتیبانی از چندین زبان
- **Rocket Chat Integration**: اتصال به Rocket Chat
- **Queue System**: سیستم صف برای پردازش کارهای سنگین
- **Activity Logging**: ثبت لاگ فعالیت‌های کاربران
- **Report System**: سیستم گزارش‌گیری و آمارگیری
- **Admin Panel**: پنل مدیریت با Laravel Nova

## 🛠️ تکنولوژی‌های استفاده شده
- **Framework**: Laravel 10
- **PHP**: 8.1+
- **Database**: MySQL
- **Admin Panel**: Laravel Nova
- **Full-Text Search**: Laravel Fulltext
- **Queue**: Laravel Queue
- **Translation**: OneAPI Translation Service
- **Weather APIs**: OpenWeatherMap, Tomorrow.io

# How to start Development

## انواع ربات‌های قابل ساخت

<p dir="rtl">
انواع روبات هایی که شما میتوانید با روبات ساز ما بسازید به شرح زیر است
</p>


1- Weather bot

<p dir="rtl">
1- روبات هواشناسی

میتوانید دمای هوا یا سرعت باد را تنظیم کنید که اگر تغییراتش زیاد بود یا از حد و کف نیاز شما بالاتر رفت یا پایین تر رفت به شما اطلاع دهد یا در گروهی که هستید پیام بگذارد.

</p>


2- Quran bot

[more info](https://saber-tabatabaee.medium.com/holy-book-project-quran-telegram-bot-english-french-spanish-turkish-persian-dutch-urdu-chinese-etc-957adfd3daf2)

<p dir="rtl">
2- روبات قرآن
که میتوانید با آن قرآن را مطالعه کنید ختم کنید

- حفظ کنید

- در کل سال با قرآن مانوس باشید

- درخواست آیه به آیه بدهید و هر آیه را با ترجمه و فایل صوتی قرائت عربی و فارسی آن مطالعه کنید
- درخواست کلمه به کلمه بدهید و کلمات را برای فرزند خود بخوانید و جلو بروید.
- درخواست صفحه به صفحه بدهید و هر صفحه را به صورت فایل اسکن شده مشاهده و فایل صوتی معادل آن را ببینید
- درخواست جستجو در کل قرآن بدهید و جستجو کنید
- درخواست نمایش جزو به جز و فهرست 114 تایی سوره ها را بدهید و به سوره مربوط بروید

[اطلاعات بیشتر](https://vrgl.ir/hp4xr)

</p>




3- Admin bot

<p dir="rtl">
3- روبات ادمین 


که با ارسال یک مطلب به روبات در تمام پیام رسان های شما مطالب شما منتشر میشود و نیازی به مراجعات مکرر به آن پیام رسان ها نمیباشد

</p>



4- Social bot

<p dir="rtl">
4- روبات انتشار مطالب در شبکه های اجتماعی

مطالب که برای روبات میفرستید در توییتر و فیس بوک و لینکدین و اینستاگرام به صورت اتوماتیک قرار میگیرد

</p>


5- Pray bot

<p dir="rtl">
5- روبات نماز و عبادات و نماز های مستحبی و رکعت شمار
</p>

6- Hadith bot

<p dir="rtl">
6- روبات جستجوی حدیث
جستجو در کتب حدیث شیعه و نمایش تاریخچه جستجوها
</p>

7- Nahj bot

<p dir="rtl">
7- روبات نهج البلاغه
جستجو و مطالعه در متن نهج البلاغه
</p>

8- RSS bot

<p dir="rtl">
8- روبات RSS
دریافت و انتشار خودکار محتوا از فیدهای RSS
</p>

9- Audio Book bot

<p dir="rtl">
9- روبات کتاب‌های صوتی
مدیریت و انتشار کتاب‌های صوتی
</p>

10- Song Sara bot

<p dir="rtl">
10- روبات موسیقی Song Sara
مدیریت موسیقی و پلی‌لیست‌ها
</p>

مشاهده لیست کامل انواع روبات هایی که میتوانید برای خودتان هم بسازید در لینک زیر است

[http://bots.pardisania.ir](http://bots.pardisania.ir)


## 📚 مستندات پروژه

### مستندات کلی
- [🚀 راهنمای شروع کار](docs/GETTING-STARTED.md) - راهنمای کامل نصب و راه‌اندازی پروژه از صفر
- [PROJECT_ROLES.md](PROJECT_ROLES.md) - نقش‌ها و مسئولیت‌های پروژه
- [CHECKLIST.md](CHECKLIST.md) - چک‌لیست کامل پروژه
- [README-DEVELOP.md](README-DEVELOP.md) - راهنمای توسعه
- [.cursorrules](.cursorrules) - قوانین پروژه و SOLID
- [راهنمای لاگینگ](docs/LOGGING-GUIDE.md) - راهنمای کامل استفاده از سیستم لاگینگ

### مستندات فیچرها

- [آمار ربات مادر](./docs/features/BOT_MOTHER_STATISTICS.md) - مشاهده آمار کامل ربات مادر و ربات‌های ساخته شده
- [ارسال پیام همگانی](./docs/features/MESSAGE_BROADCAST.md) - ارسال پیام به کاربران بر اساس زبان
- [زبان‌های اضافی ربات قرآن](./docs/features/ADDITIONAL_LANGUAGES_QURAN_BOT.md) - زبان‌های جدید و تغییرات endpoint
- [چک لیست تست](./docs/features/TEST_CHECKLIST.md) - چک لیست کامل برای تست فیچرهای جدید
- [ایمپورت ترجمه‌های قرآن](./docs/features/QURAN_TRANSLATION_IMPORT.md) - راهنمای کامل ایمپورت ترجمه‌های قرآن از فایل‌های SQL dump (شامل دستورات و مثال‌ها)
- [ردیابی bot_id و bot_mother_id در لاگ‌ها](./docs/features/BOT_LOGS_BOT_ID_TRACKING.md) - ردیابی کامل bot_id و bot_mother_id در تمام لاگ‌های سیستم و امکان به‌روزرسانی لاگ‌های قدیمی
- [سیستم مدیریت فایل‌های آپلود شده](./docs/features/BOT_FILE_UPLOAD_MANAGEMENT.md) - مدیریت فایل‌های آپلود شده برای جلوگیری از آپلود مجدد و بهینه‌سازی سرعت

هر فیچر دارای مستندات جداگانه است که شامل توضیحات، نحوه استفاده، ساختار فایل‌ها و ... می‌شود.

#### فیچرهای موجود:

- [📝 ثبت‌نام پرسنل (Personnel Registration)](docs/features/personnel-registration.md)
  - ثبت‌نام پرسنل جدید از طریق ربات‌های پیام‌رسان
  - اعتبارسنجی اطلاعات و ذخیره در دیتابیس
  - ارسال لینک ربات‌های اختصاصی

- [🕌 بهبودهای صفحه گزارش وب ربات نماز قضا](./docs/features/PRAYER_BOT_WEB_REPORT_ENHANCEMENT.md)
  - نمایش تاریخ شمسی و قمری در صفحه گزارش
  - پشتیبانی چندزبانه برای ایمیل‌های گزارش
  - امکان تست زبان از طریق کامند لاین
  - دکمه راهنما برای دریافت لینک گزارش از طریق ربات

- [📖 قرآن (Quran Bot)](docs/features/quran-bot.md) - *در حال آماده‌سازی*
  - مطالعه قرآن به صورت آیه به آیه
  - جستجو در قرآن
  - فایل‌های صوتی و ترجمه

- [🌤️ هواشناسی (Weather Bot)](docs/features/weather-bot.md) - *در حال آماده‌سازی*
  - اطلاع‌رسانی تغییرات آب و هوا
  - تنظیم حد و کف دما و سرعت باد

- [📰 RSS Bot](docs/features/rss-bot.md) - *در حال آماده‌سازی*
  - دریافت و انتشار مطالب RSS
  - ترجمه خودکار مطالب

- [🔗 شبکه‌های اجتماعی (Social Bot)](docs/features/social-bot.md) - *در حال آماده‌سازی*
  - انتشار مطالب در شبکه‌های اجتماعی
  - یکپارچه‌سازی با توییتر، فیس‌بوک، لینکدین و اینستاگرام

- [👤 ادمین (Admin Bot)](docs/features/admin-bot.md) - *در حال آماده‌سازی*
  - مدیریت و انتشار مطالب در تمام پیام‌رسان‌ها
  - مدیریت یکپارچه

- [📜 حدیث (Hadith Bot)](docs/features/hadith-bot.md) - *در حال آماده‌سازی*
  - جستجو و مطالعه احادیث

- [📚 نهج البلاغه (Nahj Bot)](docs/features/nahj-bot.md) - *در حال آماده‌سازی*
  - مطالعه نهج البلاغه
  - جستجو در نهج البلاغه

> **نکته**: برای ایجاد مستندات برای فیچر جدید، می‌توانید از [Template موجود](docs/features/README-TEMPLATE.md) استفاده کنید.

## Donate this project

https://hamibash.com/quran_hefz_bale_telegram_bot

## 🚀 راهنمای شروع کار

این بخش شامل مراحل کامل نصب و راه‌اندازی پروژه از صفر تا اجرا است.

### 1️⃣ نصب PHP یا XAMPP

برای اجرای این پروژه نیاز به PHP 8.1 یا بالاتر دارید. می‌توانید یکی از روش‌های زیر را انتخاب کنید:

#### روش 1: نصب XAMPP (پیشنهادی برای مبتدیان)

1. از [وب‌سایت رسمی XAMPP](https://www.apachefriends.org/) آخرین نسخه را دانلود کنید
2. XAMPP را نصب کنید (توصیه می‌شود در مسیر `C:\xampp` نصب شود)
3. XAMPP Control Panel را باز کنید
4. Apache و MySQL را Start کنید
5. PHP به صورت خودکار با XAMPP نصب می‌شود

#### روش 2: نصب PHP به صورت مستقل

1. از [وب‌سایت رسمی PHP](https://www.php.net/downloads.php) نسخه 8.1 یا بالاتر را دانلود کنید
2. PHP را در مسیری مانند `C:\php` استخراج کنید
3. مسیر PHP را به متغیر محیطی PATH اضافه کنید
4. فایل `php.ini` را ویرایش کنید و extension های زیر را فعال کنید:
   - `extension=mbstring`
   - `extension=zip`
   - `extension=pdo_mysql`
   - `extension=curl`
   - `extension=openssl`

#### بررسی نصب PHP

برای اطمینان از نصب صحیح PHP، در Command Prompt یا PowerShell دستور زیر را اجرا کنید:

```bash
php -v
```

باید نسخه PHP 8.1 یا بالاتر نمایش داده شود.

### 2️⃣ ریستور دیتابیس

1. فایل بکاپ دیتابیس (`.sql` یا `.dump`) را آماده کنید
2. XAMPP Control Panel را باز کنید و MySQL را Start کنید
3. به phpMyAdmin بروید: `http://localhost/phpmyadmin`
4. یک دیتابیس جدید ایجاد کنید (مثلاً `bot_platform`)
5. دیتابیس را انتخاب کنید و به تب Import بروید
6. فایل بکاپ را انتخاب کرده و Import را بزنید

**یا از طریق Command Line:**

```bash
mysql -u root -p bot_platform < database_backup.sql
```

**نکته:** اگر از XAMPP استفاده می‌کنید، ممکن است رمز عبور root خالی باشد. در این صورت:

```bash
mysql -u root bot_platform < database_backup.sql
```

### 3️⃣ نصب Composer

Composer یک ابزار مدیریت وابستگی‌ها برای PHP است که برای این پروژه ضروری است.

1. از [وب‌سایت رسمی Composer](https://getcomposer.org/download/) آخرین نسخه را دانلود کنید
2. فایل `Composer-Setup.exe` را اجرا کنید
3. در حین نصب، مسیر PHP را مشخص کنید (معمولاً `C:\xampp\php\php.exe`)
4. نصب را تکمیل کنید

#### بررسی نصب Composer

```bash
composer --version
```

### 4️⃣ شروع کار

پس از نصب PHP و Composer، مراحل زیر را انجام دهید:

#### مرحله 1: کلون کردن پروژه (اگر از Git استفاده می‌کنید)

```bash
git clone <repository-url>
cd bot-rad-git
```

#### مرحله 2: نصب وابستگی‌ها با Composer

```bash
composer install
```

یا اگر می‌خواهید وابستگی‌های development را هم نصب کنید:

```bash
composer install --no-dev
```

#### مرحله 3: تنظیم فایل محیطی

```bash
copy .env.example .env
```

سپس فایل `.env` را ویرایش کنید و اطلاعات دیتابیس را تنظیم کنید:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bot_platform
DB_USERNAME=root
DB_PASSWORD=
```

#### مرحله 4: تولید کلید اپلیکیشن

```bash
php artisan key:generate
```

#### مرحله 5: اجرای Migration ها

```bash
php artisan migrate
```

#### مرحله 6: Seed کردن دیتابیس (اختیاری)

```bash
php artisan db:seed
```

#### مرحله 7: ایجاد لینک Symbolic برای Storage

```bash
php artisan storage:link
```

#### مرحله 8: اجرای سرور توسعه

```bash
php artisan serve
```

پروژه شما در آدرس `http://localhost:8000` در دسترس خواهد بود.

### ✅ بررسی نهایی

برای اطمینان از نصب صحیح، موارد زیر را بررسی کنید:

- ✅ PHP 8.1+ نصب شده است
- ✅ Composer نصب شده است
- ✅ دیتابیس ریستور شده است
- ✅ فایل `.env` تنظیم شده است
- ✅ Migration ها اجرا شده‌اند
- ✅ سرور Laravel در حال اجرا است

### 📝 نکات مهم

- اگر از XAMPP استفاده می‌کنید، مطمئن شوید که Apache و MySQL در XAMPP Control Panel در حال اجرا هستند
- در صورت بروز خطا، فایل `storage/logs/laravel.log` را بررسی کنید
- برای محیط Production، حتماً `APP_DEBUG=false` را در فایل `.env` تنظیم کنید

## 🛠️ راه‌اندازی پروژه

- composer i
 - composer u
 - cp .env.example to .env
 - php artisan ke:ge
 - php artisan migrate
 - php artisan db:seed
 - extension=mbstring in php.ini
 - extension=zip in php.ini

### For Nova Admin Panel
 - npm i
 - npm run dev in dev mode and npm run build in server
 - php artisan nova:user to create a new user as admin.
 - i dont know need php artisan nova:install or nova:publish or not

run index on ayat
php artisan laravel-fulltext:all \\App\\Models\\QuranAyat

### for translate:

php artisan translation:sync



# 📝 نکات مهم

## نسخه جدید
 - migrate
 - seed rss channel origin RssChannelOriginsTableSeeder RssChannelsTableSeeder
 - set tags
 - test

## دستورات تست

```bash
php artisan app:test-sendch
```

## هنگام بازگردانی بکاپ از سرور به لوکال

```sql
UPDATE `taggables` SET `taggable_id` = 3 WHERE `taggable_type` = 'App\\Models\\RssChannel' AND `taggable_id` = 1
```

# ⏰ Cron Jobs




Minute	Hour	Day	Month	Weekday	Command	Actions

*/15	22	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan queue:work >> /dev/null 2>&1	    

*/45	23	*	*	*	cd /home/pardisa2/blog && /usr/local/bin/php artisan queue:work >> /dev/null 2>&1	    

0	0	*	*	0	cd /home/pardisa2/blog && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1	    

59	23	10	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1	    

*/20	*	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:rss_ >> /dev/null 2>&1	    

*/15	*	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:rss-post >> /dev/null 2>&1	    

58	*	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:boo >> /dev/null 2>&1	    

57	*	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:gen >> /dev/null 2>&1	    

*/19	*	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:get-all >> /dev/null 2>&1	    

*/40	18	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:add_so >> /dev/null 2>&1	    

4	3	*	*	*	rm ./bots/storage/logs/laravel.log && rm ./blog/storage/logs/laravel.log && rm -R ./bots.pardisania.ir/logs/ && rm -R ./bots/logs/ && rm -R ./logs/	    

rm ./bots/storage/logs/laravel.log && rm ./blog/storage/logs/laravel.log && rm -R ./bots.pardisania.ir/logs/ && rm -R ./bots/logs/ && rm -R ./logs/ && rm -R ./bots/storage/app/public/images && rm -R ./tmp

46	2	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:add_mp >> /dev/null 2>&1

20	1	*	*	*	cd /home/pardisa2/bots && /usr/local/bin/php artisan app:update_bal >> /dev/null 2>&1

### دستوراتی که با `schedule:run` اجرا می‌شوند

با تنظیم یک کران‌جاب برای `php artisan schedule:run` (مثلاً هر دقیقه یا طبق یکی از ردیف‌های بالا)، این دستورات به‌صورت خودکار طبق زمان‌بندی زیر اجرا می‌شوند (مربوط به `app/Console/Kernel.php`):

| فرکانس | دستور / Job |
|--------|--------------|
| هر پنج‌شنبه ۲۰:۲۷ | TaskReminderCommand |
| هر روز ۲۰:۲۹ | UsersRankingCommand |
| هر ۱۵ دقیقه | RssReadTranslate |
| هر ۱۰ دقیقه | SendPrayerWeeklyReports (batch 5, interval 10) |
| هر ۳۰ دقیقه | SendPrayerWeeklyReports (batch 10, interval 30) |
| هر ساعت | SendPrayerWeeklyReports (batch 10, interval 60) |
| هر ساعت | CheckWeatherAlertsJob |
| هر ساعت ۱۹:۰۰–۲۳:۵۹ | ScheduleBookPublishing |
| روزانه ۰۷:۰۰ (در صورت فعال بودن env) | TestScheduleDailyIntoSlack |
| هر روز ۰۸:۰۰ | SendDailyQuranSuggestionToAdmins |
| هر روز ۰۹:۰۰ | PostDailyVerseToChannels |

#### ارسال روزانه به کانال (تک‌آیه / حدیث / نهج / شراب / ترکیبی رندوم / ترکیبی ترتیبی)

این قابلیت خودکار اجرا نمی‌شود مگر اینکه روی سرور **کران‌جاب** تنظیم کنید. یکی از دو روش زیر را استفاده کنید (مسیر پروژه را با مسیر واقعی روی سرور عوض کنید):

**روش ۱ (پیشنهادی):** یک بار در دقیقه (یا حداقل یک بار در روز قبل از ساعت ۰۹:۰۰) `schedule:run` را اجرا کنید تا طبق `Kernel.php` هر روز ۰۹:۰۰ دستور `PostDailyVerseToChannels` اجرا شود:

```cron
*/1	*	*	*	*	cd /مسیر/پروژه && php artisan schedule:run >> /dev/null 2>&1
```

**روش ۲:** فقط همان دستور ارسال روزانه را هر روز ساعت ۹ صبح اجرا کنید:

```cron
0	9	*	*	*	cd /مسیر/پروژه && php artisan daily-channel:post >> /dev/null 2>&1
```

در ویندوز (Task Scheduler) معادل دستور: `php artisan daily-channel:post` با working directory مسیر پروژه و trigger روزانه ساعت ۰۹:۰۰.

---

### ستون `last_sent_content_type` (گزینه ترتیبی)

برای گزینه **۶ = ترکیبی ترتیبی** باید ستون `last_sent_content_type` در جدول `admin_daily_channel_configs` وجود داشته باشد. اگر مایگریشن اجرا نکردید، این کوئری را خودتان روی دیتابیس (MSSQL) اجرا کنید:

```sql
ALTER TABLE admin_daily_channel_configs
ADD last_sent_content_type NVARCHAR(30) NULL;
```

# 📋 دستورات مهم

```bash
# ترجمه RSS (فقط تحلیل RSS امروز)
php artisan app:rss_read_translate --switch

# کار با صف
php artisan queue:work

# اجرای Schedule
php artisan schedule:run

# ایندکس کردن آیات قرآن
php artisan laravel-fulltext:all \\App\\Models\\QuranAyat

# همگام‌سازی ترجمه‌ها
php artisan translation:sync
```

---

**📖 برای اطلاعات بیشتر در مورد توسعه، به [README-DEVELOP.md](README-DEVELOP.md) مراجعه کنید.**
