# مستندات پروژه

به مستندات پروژه Bot Generator Bale Telegram Laravel 10 خوش آمدید.

## 📚 فهرست مستندات

### مستندات کلی

- [README.md اصلی](../README.md) - معرفی پروژه و راهنمای شروع
- [PROJECT_ROLES.md](../PROJECT_ROLES.md) - نقش‌ها و مسئولیت‌های پروژه
- [CHECKLIST.md](../CHECKLIST.md) - چک‌لیست کامل پروژه
- [README-DEVELOP.md](../README-DEVELOP.md) - راهنمای توسعه
- [.cursorrules](../.cursorrules) - قوانین پروژه و SOLID Principles

### راهنماهای تخصصی

- [راهنمای لاگینگ](LOGGING-GUIDE.md) - راهنمای کامل استفاده از سیستم لاگینگ

### مستندات فیچرها

مستندات هر فیچر به صورت جداگانه در پوشه `features/` قرار دارد:

#### فیچرهای موجود

- [📝 ثبت‌نام پرسنل (Personnel Registration)](features/personnel-registration.md)
  - ثبت‌نام پرسنل جدید از طریق ربات‌های پیام‌رسان
  - اعتبارسنجی اطلاعات و ذخیره در دیتابیس

- [📖 قرآن (Quran Bot)](features/quran-bot.md) - *در حال آماده‌سازی*
- [🌤️ هواشناسی (Weather Bot)](features/weather-bot.md) - *در حال آماده‌سازی*
- [📰 RSS Bot](features/rss-bot.md) - *در حال آماده‌سازی*
- [🔗 شبکه‌های اجتماعی (Social Bot)](features/social-bot.md) - *در حال آماده‌سازی*
- [👤 ادمین (Admin Bot)](features/admin-bot.md) - *در حال آماده‌سازی*
- [📜 حدیث (Hadith Bot)](features/hadith-bot.md) - *در حال آماده‌سازی*
- [📚 نهج البلاغه (Nahj Bot)](features/nahj-bot.md) - *در حال آماده‌سازی*

#### Template

- [Template README](features/README-TEMPLATE.md) - Template برای ایجاد مستندات فیچر جدید

## 📖 نحوه استفاده

### برای توسعه‌دهندگان

1. قبل از شروع کار جدید، [PROJECT_ROLES.md](../PROJECT_ROLES.md) و [.cursorrules](../.cursorrules) را مطالعه کنید
2. برای فیچر جدید، از [Template](features/README-TEMPLATE.md) استفاده کنید
3. بعد از تکمیل فیچر، README آن را در `features/` ایجاد کنید
4. README اصلی را با لینک به فیچر جدید بروزرسانی کنید

### برای مدیران پروژه

- از [CHECKLIST.md](../CHECKLIST.md) برای پیگیری پیشرفت پروژه استفاده کنید
- [PROJECT_ROLES.md](../PROJECT_ROLES.md) را برای درک نقش‌ها و مسئولیت‌ها مطالعه کنید

## 🔄 بروزرسانی مستندات

### هنگام اضافه کردن فیچر جدید

1. [ ] مستندات فیچر را در `features/` ایجاد کنید
2. [ ] README اصلی را با لینک به فیچر جدید بروزرسانی کنید
3. [ ] CHECKLIST.md را بروزرسانی کنید
4. [ ] در صورت نیاز، راهنماهای تخصصی را بروزرسانی کنید

### هنگام تغییر در فیچر موجود

1. [ ] مستندات فیچر را بروزرسانی کنید
2. [ ] تاریخ "آخرین بروزرسانی" را تغییر دهید
3. [ ] در صورت تغییرات بزرگ، CHANGELOG.md را بروزرسانی کنید

## 📝 ساختار مستندات

هر مستندات فیچر باید شامل موارد زیر باشد:

- توضیحات کلی
- اهداف
- مسیر فایل‌ها
- Flow کاری
- نحوه استفاده
- ساختار دیتابیس
- Validation Rules
- Logging
- Testing
- Known Issues
- Future Improvements
- لینک به مستندات مرتبط

---

**آخرین بروزرسانی**: تاریخ آخرین تغییر

