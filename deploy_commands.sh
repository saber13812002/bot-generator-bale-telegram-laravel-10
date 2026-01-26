#!/bin/bash

# دستورات Deploy برای سرور Production
# این فایل را در سرور اجرا کنید

echo "=== شروع Deploy ==="

# 1. افزایش memory limit برای composer
export COMPOSER_MEMORY_LIMIT=-1

# 2. نصب dependencies
echo "📦 نصب dependencies..."
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader

# اگر composer در مسیر دیگری است، از این استفاده کنید:
# php -d memory_limit=-1 /usr/local/bin/composer install --no-dev --optimize-autoloader

# 3. پاک کردن cache ها
echo "🧹 پاک کردن cache ها..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# 4. اجرای migrations
echo "🗄️  اجرای migrations..."
php artisan migrate --force

# 5. Generate Swagger docs
echo "📚 Generate کردن Swagger documentation..."
php artisan l5-swagger:generate

# 6. Cache کردن config و route (برای production)
echo "⚡ Cache کردن config و route..."
php artisan config:cache
php artisan route:cache

echo "✅ Deploy با موفقیت انجام شد!"
