---
name: PlaceQuran Image Feature
overview: اضافه کردن قابلیت ارسال عکس از placequran.com در انتهای هر متن آیه با دستورات /imagequran_true و /imagequran_false
todos:
  - id: add_command_handling
    content: اضافه کردن پردازش دستورات /imagequran_true و /imagequran_false در QuranWordController
    status: completed
  - id: add_command_to_text
    content: اضافه کردن دستور مخفی در انتهای متن آیه در QuranHelper::getSureAye()
    status: completed
  - id: create_language_helper
    content: ایجاد متد helper برای normalize کردن زبان و بررسی وجود در لیست
    status: completed
  - id: implement_image_sending
    content: پیاده‌سازی ارسال عکس از placequran.com بعد از ارسال متن
    status: completed
    dependencies:
      - add_command_to_text
      - create_language_helper
  - id: test_commands
    content: تست دستورات /imagequran_true و /imagequran_false
    status: completed
    dependencies:
      - add_command_handling
---

# افزودن قابلیت PlaceQuran Image

## خلاصه

اضافه کردن قابلیت ارسال عکس از placequran.com در انتهای هر متن آیه قرآن. این قابلیت مشابه `/mp3_true` و `/mp3_false` با دستورات `/imagequran_true` و `/imagequran_false` کنترل می‌شود.

## جزئیات پیاده‌سازی

### 1. پردازش دستورات `/imagequran_true` و `/imagequran_false`

در `app/Http/Controllers/QuranWordController.php`، در بخش پردازش دستورات با underscore (حدود خط 1979)، یک `else if` جدید برای `placequran` یا `imagequran` اضافه کنید:

- ذخیره `placequran_enable` در settings کاربر
- نمایش پیام فعال/غیرفعال بودن
- نمایش دستور مخالف (اگر فعال است، `/imagequran_false` و برعکس)

### 2. اضافه کردن دستور در انتهای متن

در `app/Helpers/QuranHelper.php`، متد `getSureAye()` (خط 319-416):

- در انتهای متن، قبل از دستور `/scan` و `/help`، دستور `/imagequran_true` یا `/imagequran_false` را اضافه کنید
- دستور را نمایش ندهید (فقط به عنوان متن مخفی)
- اگر `placequran_enable == "true"`، دستور `/imagequran_false` را اضافه کنید
- اگر `placequran_enable != "true"` یا وجود ندارد، دستور `/imagequran_true` را اضافه کنید

### 3. ارسال عکس

بعد از ارسال متن آیه، بررسی کنید:

- اگر `placequran_enable == "true"` باشد، عکس را ارسال کنید
- ساخت URL: 
  - برای زبان‌های موجود در لیست (ar, en, ms, id, tr, ur, hi) - بعد از normalize: `https://placequran.com/s/{sura}/{aya}/ar,en`
  - برای سایر زبان‌ها: `https://placequran.com/s/{sura}/{aya}/ar`
- تشخیص نوع پلتفرم (telegram, bale, gap, ita):
  - برای telegram و bale: از `BotHelper::sendPhoto()` استفاده کنید
  - برای gap و ita: از `BotHelper::sendPhotoGap()` یا متد مشابه استفاده کنید

### 4. Normalize زبان

یک متد helper برای normalize کردن زبان ایجاد کنید:

- `ar-IQ` -> `ar`
- `en-US` -> `en`
- `ms-MY` -> `ms`
- و غیره

### 5. لیست زبان‌های پشتیبانی شده

یک آرایه ثابت برای زبان‌های پشتیبانی شده:

```php
const SUPPORTED_LANGUAGES = ['ar', 'en', 'ms', 'id', 'tr', 'ur', 'hi'];
```

## فایل‌های تغییر یافته

1. `app/Http/Controllers/QuranWordController.php` - پردازش دستورات `/imagequran_true` و `/imagequran_false`
2. `app/Helpers/QuranHelper.php` - اضافه کردن دستور در انتهای متن و متد ارسال عکس

## نکات مهم

- دستور در انتهای متن نمایش داده نمی‌شود (مثل comment)
- فقط اگر کاربر فعال کرده باشد، عکس ارسال می‌شود
- URL بر اساس normalize کردن زبان کاربر ساخته می‌شود
- از همان متدهای موجود برای ارسال عکس استفاده کنید