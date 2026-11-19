---
name: تبدیل ربات قرآن به RESTful API با Swagger
overview: تبدیل تمام منطق‌های موجود در QuranWordController به RESTful API با مستندسازی کامل Swagger/OpenAPI و اضافه کردن وب‌سرویس‌های جدید برای اپلیکیشن موبایل
todos:
  - id: install_l5_swagger
    content: نصب و تنظیم L5-Swagger package
    status: completed
  - id: create_quran_api_controller
    content: ایجاد QuranApiController با تمام متدهای اصلی
    status: completed
    dependencies:
      - install_l5_swagger
  - id: create_request_classes
    content: ایجاد Request classes برای validation
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: create_resource_classes
    content: ایجاد Resource classes برای formatting responses
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: add_swagger_annotations
    content: اضافه کردن Swagger Annotations به تمام متدها
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: update_swagger_yaml
    content: به‌روزرسانی swagger.yaml با schemas جدید
    status: completed
    dependencies:
      - add_swagger_annotations
  - id: add_api_routes
    content: اضافه کردن Route ها در routes/api.php
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: implement_trending_endpoint
    content: پیاده‌سازی endpoint برای trending ayahs
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: implement_feed_endpoint
    content: پیاده‌سازی endpoint برای feed با pagination
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: implement_audio_endpoint
    content: پیاده‌سازی endpoint برای دریافت لینک فایل صوتی
    status: completed
    dependencies:
      - create_quran_api_controller
  - id: test_endpoints
    content: تست تمام endpoints و بررسی response formats
    status: completed
    dependencies:
      - add_api_routes
      - add_swagger_annotations
  - id: generate_swagger_docs
    content: Generate کردن Swagger documentation
    status: completed
    dependencies:
      - update_swagger_yaml
      - test_endpoints
---

# تبدیل ربات قرآن به RESTful API با Swagger

## بررسی وضعیت فعلی

- پروژه Laravel 10 با ساختار Bot Generator
- QuranWordController شامل منطق کامل ربات قرآن (انتخاب زبان، مترجم، جستجو، آیه‌ها و غیره)
- L5-Swagger نصب نشده (باید نصب شود)
- API Token Authentication موجود است
- Helper های QuranHelper و QuranBotUserRankingService موجود هستند

## مراحل پیاده‌سازی

### 1. نصب و تنظیم L5-Swagger

**فایل:** `composer.json`

- اضافه کردن `darkaonline/l5-swagger` به dependencies
- اجرای `composer require darkaonline/l5-swagger`
- Publish کردن config با `php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"`

**فایل:** `config/l5-swagger.php`

- تنظیم مسیر annotations به `app/Http/Controllers/Api`
- تنظیم مسیر docs به `storage/api-docs`

### 2. ایجاد QuranApiController

**فایل جدید:** `app/Http/Controllers/Api/QuranApiController.php`

این Controller شامل متدهای زیر خواهد بود:

#### 2.1. متدهای زبان و ترجمه

- `GET /api/v1/quran/languages` - لیست زبان‌های موجود
- `GET /api/v1/quran/translations` - لیست ترجمه‌های یک زبان
- `GET /api/v1/quran/translations/{language}` - ترجمه‌های زبان خاص
- `POST /api/v1/quran/user/settings/translation` - تغییر ترجمه کاربر

#### 2.2. متدهای محتوای قرآن

- `GET /api/v1/quran/surahs` - لیست 114 سوره
- `GET /api/v1/quran/surahs/{sura}/ayahs/{ayah}` - دریافت یک آیه با ترجمه
- `GET /api/v1/quran/words/{wordId}` - دریافت کلمه به کلمه
- `GET /api/v1/quran/juz` - لیست 30 جزء
- `GET /api/v1/quran/juz/{juz}` - محتوای یک جزء

#### 2.3. متدهای جستجو

- `GET /api/v1/quran/search` - جستجوی پیشرفته در قرآن و ترجمه‌ها
- `POST /api/v1/quran/search` - جستجو با pagination

#### 2.4. متدهای کاربر

- `GET /api/v1/quran/user/settings` - دریافت تنظیمات کاربر
- `POST /api/v1/quran/user/settings` - بروزرسانی تنظیمات (قاری، ترجمه، transliteration)
- `GET /api/v1/quran/user/report` - گزارش فعالیت کاربر
- `GET /api/v1/quran/user/referral-stats` - آمار دعوت‌شدگان

#### 2.5. متدهای جدید برای اپلیکیشن موبایل

- `GET /api/v1/quran/trending/{period}` - آیه‌های ترند (day/week/month)
- `GET /api/v1/quran/feed` - Feed صفحه‌ای آیه‌ها (Pagination)
- `GET /api/v1/quran/audio/{sura}/{ayah}` - لینک فایل صوتی آیه

### 3. ایجاد Request Classes برای Validation

**فایل‌های جدید:**

- `app/Http/Requests/Api/Quran/UpdateTranslationRequest.php`
- `app/Http/Requests/Api/Quran/UpdateUserSettingsRequest.php`
- `app/Http/Requests/Api/Quran/SearchRequest.php`
- `app/Http/Requests/Api/Quran/GetAyahRequest.php`

### 4. ایجاد Resource Classes برای Response Formatting

**فایل‌های جدید:**

- `app/Http/Resources/Api/Quran/AyahResource.php`
- `app/Http/Resources/Api/Quran/SurahResource.php`
- `app/Http/Resources/Api/Quran/TranslationResource.php`
- `app/Http/Resources/Api/Quran/UserSettingsResource.php`
- `app/Http/Resources/Api/Quran/TrendingAyahResource.php`

### 5. اضافه کردن Swagger Annotations

برای هر متد در QuranApiController:

- `@OA\Get` یا `@OA\Post` با path و summary
- `@OA\Parameter` برای query/path parameters
- `@OA\RequestBody` برای POST requests
- `@OA\Response` برای 200, 400, 401, 404, 422
- `@OA\SecurityScheme` برای Bearer Token و API Token
- `@OA\Schema` برای Request/Response models

### 6. به‌روزرسانی swagger.yaml

**فایل:** `swagger.yaml`

- اضافه کردن tag جدید: `Quran`
- اضافه کردن schemas جدید:
  - `AyahResponse`
  - `SurahResponse`
  - `TranslationResponse`
  - `UserSettingsResponse`
  - `TrendingResponse`
  - `SearchResponse`
  - `UpdateTranslationRequest`
  - `UpdateUserSettingsRequest`

### 7. اضافه کردن Route ها

**فایل:** `routes/api.php`

- اضافه کردن route group برای `/api/v1/quran`
- استفاده از middleware `api.token` برای endpoints محافظت شده
- برخی endpoints (مثل languages, surahs) بدون نیاز به authentication

### 8. پیاده‌سازی منطق normalizeLanguageCodeForDatabase

استفاده از متد موجود در QuranWordController برای normalize کردن کد زبان در تمام API endpoints

### 9. ایجاد Service Layer (اختیاری)

**فایل جدید:** `app/Services/QuranApiService.php`

- جداسازی منطق business از Controller
- استفاده مجدد از QuranHelper و QuranBotUserRankingService

### 10. تست و مستندسازی

- تست تمام endpoints با Postman/Swagger UI
- بررسی Response format ها
- اطمینان از سازگاری با JSON standards

## ساختار فایل‌های جدید

```
app/Http/Controllers/Api/
  └── QuranApiController.php

app/Http/Requests/Api/Quran/
  ├── UpdateTranslationRequest.php
  ├── UpdateUserSettingsRequest.php
  ├── SearchRequest.php
  └── GetAyahRequest.php

app/Http/Resources/Api/Quran/
  ├── AyahResource.php
  ├── SurahResource.php
  ├── TranslationResource.php
  ├── UserSettingsResource.php
  └── TrendingAyahResource.php

app/Services/
  └── QuranApiService.php (اختیاری)
```

## نکات مهم

1. **Authentication:** استفاده از هر دو روش API Token و Sanctum (بر اساس پاسخ کاربر)
2. **User Identification:** پشتیبانی از chat_id (برای ربات) و user_id (برای موبایل)
3. **Language Normalization:** استفاده از `normalizeLanguageCodeForDatabase` در تمام endpoints
4. **Response Format:** همه responses باید به صورت JSON استاندارد باشند
5. **Error Handling:** استفاده از try-catch و return error responses یکسان
6. **Pagination:** برای endpoints لیستی (surahs, search results) استفاده از Laravel Pagination

## مراحل اجرا

1. نصب L5-Swagger
2. ایجاد QuranApiController با متدهای اصلی
3. اضافه کردن Swagger Annotations
4. ایجاد Request و Resource classes
5. اضافه کردن Route ها
6. تست endpoints
7. به‌روزرسانی swagger.yaml
8. Generate Swagger documentation