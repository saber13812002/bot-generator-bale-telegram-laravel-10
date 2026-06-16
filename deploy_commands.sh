#!/bin/bash

# دستورات Deploy برای سرور Production
# این فایل را در سرور اجرا کنید

echo "=== شروع Deploy ==="

# 1. افزایش memory limit برای composer
export COMPOSER_MEMORY_LIMIT=-1

# 2. نصب dependencies
echo "📦 نصب dependencies..."
COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"
if [ -z "$COMPOSER_BIN" ] && [ -f composer.phar ]; then
    COMPOSER_BIN="php composer.phar"
elif [ -n "$COMPOSER_BIN" ]; then
    COMPOSER_BIN="php -d memory_limit=-1 $COMPOSER_BIN"
else
    echo "❌ composer پیدا نشد. ابتدا: which composer"
    exit 1
fi
$COMPOSER_BIN install --no-dev --optimize-autoloader

# 3. پاک کردن cache ها
echo "🧹 پاک کردن cache ها..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# 4. اجرای migrations
echo "🗄️  اجرای migrations..."
php artisan migrate --force

# 5. Import webhook endpoints (در صورت نیاز)
php artisan webhook-endpoints:import-default

# 6. Cache کردن config و route (برای production)
# توجه: از optimize:clear استفاده نکنید — bootstrap cache را پاک می‌کند و ممکن است با پکیج‌های نصب‌نشده خطا بدهد
echo "⚡ Cache کردن config و route..."
php artisan config:cache
php artisan route:cache

echo "✅ Deploy با موفقیت انجام شد!"
