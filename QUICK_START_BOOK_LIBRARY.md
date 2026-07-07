# راهنمای شروع سریع — ربات کتابخانه (Smart Book Library)

> بر اساس وضعیت فعلی: شما ربات reader (Bot ID: 55) را ساخته‌اید.

---

## مرحله ۱ — بررسی کنید چه ربات‌هایی دارید

```bash
# در سرور اجرا کنید
php artisan tinker
App\Models\Bot::where('endpoint_id', 'book-library')->orWhere('endpoint_id', 'book-library-reader')->get(['id', 'endpoint_id', 'bale_bot_name', 'bale_owner_chat_id']);
```

اگر **book-library اصلی** را ندارید، باید اول آن را بسازید. اگر دارید، به مرحله ۲ بروید.

> ⚠️ **نکته مهم**: ربات book-library (اصلی) برای انتخاب دسته و ارسال محتواست. ربات reader فقط برای دریافت محتواست. دستورات مدیریتی مثل `/manage`, `/addcategory`, `/broadcast` فقط روی ربات **اصلی** کار می‌کند.

---

## مرحله ۲ — ربات اصلی book-library را بسازید (اگر ندارید)

از Bot Mother:
1. `/start`
2. گزینه **13. Smart Book Library / AI Book Coach** را انتخاب کنید
3. یک ربات در بله بسازید
4. پس از ساخت، Bot ID آن را یادداشت کنید

سپس از Bot Mother دوباره ربات **Book Library Reader** را بسازید:
1. این بار endpoint **Book Library Reader** را انتخاب کنید
2. توکن ربات دوم را بدهید
3. Bot ID ربات اصلی book-library را وارد کنید

✅ حالا `LibraryBotConfig` با `bot_id` و `reader_bot_id` تنظیم می‌شود.

---

## مرحله ۳ — دسته‌بندی (تگ) در Nova بسازید

1. برید به: `http://bots.pardisania.ir/nova/resources/content-categories`
2. کلیک **Create 🏷 Content Category**
3. **Bot**: ربات book-library خود را انتخاب کنید
4. **Title**: مثلاً `کتاب‌های صوتی`
5. **Sort Order**: `1`
6. **Active**: فعال
7. کلیک **Create**

---

## مرحله ۴ — آیتم محتوا با فایل صوتی بسازید

### روش ۱ — از طریق Nova:

1. برید به: `http://bots.pardisania.ir/nova/resources/content-items`
2. کلیک **Create 📦 Content Item**
3. **Bot**: ربات book-library
4. **Category**: دسته‌ای که ساختید
5. **Title**: مثلاً `آموزش برنامه‌نویسی — قسمت ۱`
6. **Queue Order**: `1`
7. کلیک **Create**

سپس:
1. از صفحه آیتم، کلیک **Create 🎬 Content Asset**
2. **Type**: `🎵 Audio`
3. اگر فایل در سرور دارید → **Content URL** را پر کنید
4. اگر file_id از قبل دارید → **Telegram File ID** یا **Bale File ID** را پر کنید
5. کلیک **Create**

### روش ۲ — از طریق ربات (بعد از ادمین شدن):

1. در ربات **book-library اصلی** (نه reader) دستور `/manage` را بزنید
2. یک فایل صوتی بفرستید
3. ربات می‌گوید فایل دریافت شد و یک Pending ID می‌دهد
4. دستور `/addFileToCategory ID` را بزنید (مثلاً `/addFileToCategory 1`)
5. روی دسته مورد نظر کلیک کنید

---

## مرحله ۵ — تنظیم Caption Footer

1. برید به: `http://bots.pardisania.ir/nova/resources/bots`
2. ربات book-library خود را باز کنید
3. فیلد **Caption Footer** را پر کنید:
   ```
   📚 کتابخانه هوشمند
   @{bot_name}
   ```
4. کلیک **Update**

---

## مرحله ۶ — تست تحویل محتوا

### از ربات اصلی:
1. ربات book-library را استارت کنید `/start`
2. ✅ پیام خوش‌آمدگویی می‌بینید
3. روی یک دسته کلیک کنید
4. ✅ فایل صوتی ارسال می‌شود
5. ✅ Caption Footer در انتها نمایش داده می‌شود

### تست ارسال ساعتی:
```bash
# خشک (فقط نمایش)
php artisan content:deliver-hourly --dry-run

# واقعی
php artisan content:deliver-hourly
```

### تست اتمام صف:
بعد از مصرف تمام آیتم‌های یک دسته:
```bash
php artisan content:deliver-hourly
```
✅ به کاربر می‌گوید محتوایی موجود نیست
✅ به ادمین مادر نوتیف می‌دهد

---

## مرحله ۷ — تست `/adminkie`

> ⚠️ **توجه**: دستور `/adminkie` روی ربات **book-library اصلی** کار می‌کند (نه reader). reader bot پارامترهای لازم را در webhook ندارد.

1. ربات **book-library اصلی** را استارت کنید
2. دستور `/adminkie` را بزنید (دقیقاً به همین شکل)
3. ✅ پیام "درخواست شما ثبت شد"
4. ✅ ادمین مادر پیام اعلان با `/adminbot_confirm ID BotID` دریافت می‌کند

---

## مرحله ۸ — تست `/messagetothischatid`

1. ادمین مادر در ربات book-library اصلی دستور بزند:
   ```
   /messagetothischatid CHAT_ID سلام پیام تست
   ```
2. ✅ کاربر مقصد پیام را دریافت می‌کند

---

## مرحله ۹ — تست Broadcast

فقط در ربات **book-library اصلی** و فقط توسط ادمین ربات:

1. دستور `/broadcast`
2. متن پیام را بفرستید
3. فیلتر را انتخاب کنید
4. تأیید کنید

---

## عیب‌یابی

| مشکل | راه‌حل |
|------|--------|
| reader bot دستورات را نمی‌شناسد | reader bot فقط `/start` و `/help` و `/messagetothischatid` را می‌شناسد. دستورات مدیریتی را در ربات اصلی بزنید. |
| `/adminkie` کار نمی‌کند | حتماً در ربات book-library اصلی تست کنید، نه reader |
| پیام از Bot Mother نمی‌آید | بررسی کنید env متغیر `BOT_MOTHER_TOKEN_BALE` و `ADMIN_BOTS_TOKEN_BALE` تنظیم شده باشند |
| فایل ذخیره نمی‌شود | اول دسته‌بندی در Nova بسازید، سپس فایل بفرستید |
| Schedule کار نمی‌کند | کرون‌جاب باید روی سرور تنظیم شده باشد: `* * * * * php artisan schedule:run` |
