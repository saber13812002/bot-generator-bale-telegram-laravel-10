# ربات موکب‌یاب (Mawkib Finder)

ربات یافتن موکب برای طلاب و افراد ثبت‌شده در سامانه پیشخوان حوزه علمیه.

## مشخصات فنی

| مورد | مقدار |
|------|--------|
| `endpoint_id` | `mawkib-finder` |
| Route | `POST /api/webhook-mawkib-finder` |
| Seeder | `MawkibFinderWebhookEndpointSeeder` |

## ساخت ربات از Bot Mother

1. Seeder را اجرا کنید (یک‌بار):
   ```bash
   php artisan db:seed --class=MawkibFinderWebhookEndpointSeeder
   ```
2. از Bot Mother ربات جدید با endpoint **موکب یاب** بسازید.
3. Webhook باید شامل `origin`, `bot_mother_id`, `bot_id`, `token` باشد.

## تنظیمات `.env`

```env
MAWKIB_FINDER_VERIFY_URL=https://example.com/api/verify
MAWKIB_FINDER_AVAILABILITY_URL=https://example.com/api/availability
MAWKIB_FINDER_REGISTRATION_URL=https://example.com/register
MAWKIB_FINDER_HTTP_TIMEOUT=30
MAWKIB_FINDER_VERIFY_SSL=true
```

اگر `VERIFY_URL` یا `AVAILABILITY_URL` خالی باشد، پاسخ **mock** برگردانده می‌شود (برای تست).

---

## جریان کاربر (User Flow)

```
/start
  ↓
دریافت شماره موبایل (دکمه «ارسال شماره» یا تایپ)
  ↓
ارسال OTP از طریق API بله (Safir) → کاربر کد را در ربات وارد می‌کند
  ↓
دریافت کد ملی (۱۰ رقم)
  ↓
POST به وب‌سرویس ۱ (احراز هویت) → true/false
  ├─ false → پیام خطا + درخواست بررسی شماره در سامانه پیشخوان
  └─ true  → «شما احراز شدید»
        ↓
انتخاب استان (۳۱ استان، ۲ دکمه در هر ردیف)
        ↓
POST به وب‌سervیس ۲ (بدون تاریخ) → نمایش شهرها و ظرفیت
        ↓
نمایش لینک ثبت‌نام سامانه + هشدار پر شدن ظرفیت
        ↓
انتخاب تاریخ ورود (۱۴ روز: امروز تا ۱۳ روز بعد — برچسب شمسی)
        ↓
انتخاب مدت اقامت (۱ تا ۱۴ روز)
        ↓
خلاصه + دکمه «تأیید و جستجو» / «از اول»
        ↓
POST به وب‌سervیس ۲ با query string from/to → نمایش نتایج + لینک ثبت‌نام
        ↓
دکمه «از اول» برای شروع مجدد
```

---

## وب‌سرویس‌ها (برای برنامه‌نویس سمت سرور)

### ۱. احراز هویت (Verify)

**Method:** `POST`  
**URL:** مقدار `MAWKIB_FINDER_VERIFY_URL`

**Request Body (JSON):**

```json
{
  "mobile": "+989123456789",
  "national_code": "1234567890"
}
```

| فیلد | نوع | توضیح |
|------|-----|--------|
| `mobile` | string | شماره با پیش‌شماره `+98` |
| `national_code` | string | کد ملی ۱۰ رقمی |

**Response (یکی از فرمت‌های زیر):**

```json
true
```

```json
false
```

```json
{
  "result": true
}
```

```json
{
  "success": true,
  "verified": true
}
```

- `true` → کاربر در سامانه پیشخوان حوزه ثبت شده
- `false` → ثبت نشده؛ ربات پیام خطا نشان می‌دهد

---

### ۲. جستجوی ظرفیت موکب (Availability)

**Method:** `POST`  
**URL:** مقدار `MAWKIB_FINDER_AVAILABILITY_URL`

#### حالت الف: جستجوی لحظه‌ای (بدون تاریخ)

```
POST https://example.com/api/availability
Content-Type: application/json

{
  "province": "قم"
}
```

#### حالت ب: جستجو با بازه تاریخ

```
POST https://example.com/api/availability?from=2026/12/12&to=2026/12/15
Content-Type: application/json

{
  "province": "قم"
}
```

| پارامتر | محل | فرمت | توضیح |
|---------|-----|------|--------|
| `province` | Body | string | نام استان فارسی (مثلاً `قم`) |
| `from` | Query | `Y/m/d` | تاریخ ورود میلادی (مثلاً `2026/12/12`) |
| `to` | Query | `Y/m/d` | تاریخ خروج میلادی |

**محاسبه `to` در ربات:**

- کاربر تاریخ ورود را انتخاب می‌کند.
- مدت اقامت = N روز.
- `from` = تاریخ ورود
- `to` = تاریخ ورود + N روز (مثال: ورود ۱۲/۱۲ و ۱ روز اقامت → `to=2026/12/13`)

**Response (JSON):**

```json
[
  { "city": "قم", "vacant_count": 5 },
  { "city": "کهک", "vacant_count": 2 },
  { "city": "قنوات", "vacant_count": 0 }
]
```

یا با wrapper:

```json
{
  "data": [
    { "city": "قم", "vacant_count": 5 },
    { "city": "کهک", "vacant_count": 2 }
  ]
}
```

**فیلدهای قابل قبول برای هر شهر:**

| فیلد شهر | نام‌های جایگزین |
|----------|-----------------|
| نام شهر | `city`, `name`, `city_name` |
| تعداد خالی | `vacant_count`, `count`, `available`, `vacant` |

---

### ۳. لینک ثبت‌نام سامانه (ثابت — وب‌سرویس نیست)

**تنظیم:** `MAWKIB_FINDER_REGISTRATION_URL`

این URL در دو مرحله به کاربر نمایش داده می‌شود:
1. بعد از جستجوی اولیه (بدون تاریخ)
2. بعد از جستجوی نهایی (با from/to)

---

## Mock (تست بدون URL)

| بخش | رفتار |
|-----|--------|
| Verify | رقم آخر کد ملی **زوج** → `true`، **فرد** → `false` |
| Availability | استان **قم** → قم=۱، کهک=۲، قنوات=۳؛ سایر استان‌ها → یک شهر با ۰ |

---

## کامندهای تست CLI

```bash
# 1. احراز هویت
php artisan mawkib-finder:test-verify-api 09123456789 1234567890

# 2. ظرفیت بدون تاریخ
php artisan mawkib-finder:test-availability-api قم

# 3. ظرفیت با from/to
php artisan mawkib-finder:test-availability-with-date-api قم --days=2 --stay=3
php artisan mawkib-finder:test-availability-with-date-api قم --days=0 --stay=1
```

---

## فایل‌های پروژه

```
app/Http/Controllers/MawkibFinderController.php
app/Services/MawkibFinderServiceImpl.php
app/Services/MawkibFinderOtpService.php
app/Helpers/IranProvincesHelper.php
config/mawkib_finder.php
database/seeders/MawkibFinderWebhookEndpointSeeder.php
app/Console/Commands/TestMawkibFinder*.php
```

---

## OTP بله

احراز موبایل از **Bale Safir OTP** (`BaleOtpSendService`) استفاده می‌کند.  
تنظیمات در `config/bale-otp.php` و `.env` مربوط به Safir باید فعال باشد.

---

**آخرین به‌روزرسانی:** 2026-07-02
