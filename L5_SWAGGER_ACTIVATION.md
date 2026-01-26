# راهنمای فعال‌سازی L5-Swagger (بعد از حل مشکل Memory)

## وضعیت فعلی
- ✅ Package از `composer.json` حذف شد
- ✅ فایل `config/l5-swagger.php` به `config/l5-swagger.php.disabled` تغییر نام یافت
- ✅ Swagger Annotations در `QuranApiController` باقی مانده‌اند (برای استفاده بعدی)
- ✅ فایل `swagger.yaml` آماده است

## مراحل فعال‌سازی

### 1. نصب Package
```bash
composer require darkaonline/l5-swagger
```

### 2. فعال کردن Config
```bash
# در Windows
Rename-Item config/l5-swagger.php.disabled config/l5-swagger.php

# در Linux
mv config/l5-swagger.php.disabled config/l5-swagger.php
```

### 3. Publish Assets (اگر نیاز بود)
```bash
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### 4. Generate Documentation
```bash
php artisan l5-swagger:generate
```

### 5. دسترسی به Swagger UI
بعد از generate، به آدرس زیر دسترسی دارید:
```
https://bots.pardisania.ir/api/documentation
```

## نکات مهم

1. **Memory Limit**: اگر هنوز مشکل memory دارید:
   ```bash
   export COMPOSER_MEMORY_LIMIT=-1
   php -d memory_limit=1024M artisan l5-swagger:generate
   ```

2. **Swagger Annotations**: تمام annotations در `QuranApiController` آماده هستند و بعد از نصب package کار می‌کنند.

3. **Config File**: فایل config اصلاح شده است و خطای `L5Swagger\Generator` برطرف شده.
