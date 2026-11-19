# TODO: Packages برای نصب بعدی

## L5-Swagger
بعد از حل مشکل memory سرور، این package را به composer.json اضافه کنید:

```json
"darkaonline/l5-swagger": "^8.6"
```

سپس:
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
php artisan l5-swagger:generate
```

## فایل‌های مرتبط که آماده هستند:
- ✅ `config/l5-swagger.php` - تنظیمات آماده است
- ✅ `swagger.yaml` - مستندات آماده است
- ✅ `app/Http/Controllers/Api/QuranApiController.php` - با Swagger annotations آماده است
- ✅ Route ها در `routes/api.php` آماده هستند

فقط نیاز به نصب package و generate کردن docs است.
