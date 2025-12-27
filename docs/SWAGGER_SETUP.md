# راهنمای نصب و راه‌اندازی Swagger/OpenAPI

این راهنما نحوه نصب و راه‌اندازی Swagger برای مستندات API را توضیح می‌دهد.

## 📦 نصب Package

```bash
composer require darkaonline/l5-swagger
```

## ⚙️ Publish Configuration

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

این دستور فایل `config/l5-swagger.php` را ایجاد می‌کند.

## 📝 تنظیمات

فایل `config/l5-swagger.php` را باز کنید و تنظیمات زیر را انجام دهید:

```php
'paths' => [
    'docs' => storage_path('api-docs'),
    'annotations' => [
        base_path('app'),
    ],
],

'constants' => [
    'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'https://your-domain.com'),
],
```

## 📄 ایجاد فایل OpenAPI Specification

فایل `swagger.yaml` در ریشه پروژه قرار دارد. می‌توانید آن را ویرایش کنید یا از annotations استفاده کنید.

### روش 1: استفاده از فایل YAML

فایل `swagger.yaml` را در `storage/api-docs/` کپی کنید:

```bash
cp swagger.yaml storage/api-docs/swagger.yaml
```

### روش 2: استفاده از Annotations

می‌توانید از PHPDoc annotations در Controller ها استفاده کنید:

```php
/**
 * @OA\Get(
 *     path="/api/v1/metadata",
 *     summary="دریافت اطلاعات اولیه",
 *     tags={"Metadata"},
 *     @OA\Response(
 *         response=200,
 *         description="موفق"
 *     )
 * )
 */
public function getMetadata(Request $request)
{
    // ...
}
```

## 🔄 Generate Documentation

بعد از تنظیمات، documentation را generate کنید:

```bash
php artisan l5-swagger:generate
```

## 🌐 دسترسی به Swagger UI

بعد از generate، می‌توانید به آدرس زیر دسترسی داشته باشید:

```
http://your-domain.com/api/documentation
```

یا در محیط local:

```
http://localhost:8000/api/documentation
```

## 🔧 تنظیمات پیشرفته

### تغییر مسیر Documentation

در `config/l5-swagger.php`:

```php
'routes' => [
    'api' => 'api/documentation',
],
```

### اضافه کردن Security Schemes

در `swagger.yaml` یا annotations:

```yaml
components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
```

### اضافه کردن Examples

```yaml
paths:
  /api/v1/metadata:
    get:
      responses:
        '200':
          content:
            application/json:
              example:
                success: true
                data:
                  tenants: []
```

## 🚀 Auto-generate در Development

می‌توانید یک command برای auto-generate در development اضافه کنید:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    if (app()->environment('local')) {
        $schedule->command('l5-swagger:generate')->everyMinute();
    }
}
```

## 📚 منابع بیشتر

- [L5-Swagger Documentation](https://github.com/DarkaOnLine/L5-Swagger)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

## ✅ Checklist

- [ ] Package نصب شده
- [ ] Configuration publish شده
- [ ] فایل `swagger.yaml` در `storage/api-docs/` قرار دارد
- [ ] Documentation generate شده
- [ ] Swagger UI در دسترس است
- [ ] تمام endpoints در Swagger نمایش داده می‌شوند
- [ ] Examples و descriptions اضافه شده‌اند

---

**نسخه:** 1.0.0  
**تاریخ:** 2025-12-26

