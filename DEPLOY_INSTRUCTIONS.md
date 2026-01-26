# دستورالعمل Deploy در سرور Production

## مشکلات و راه حل‌ها

### 1. خطای `L5Swagger\Generator` در config
✅ **حل شد**: فایل `config/l5-swagger.php` خط 166 اصلاح شد

### 2. مشکل Memory در Composer
برای حل مشکل "Killed" در composer install:

```bash
# روش 1: افزایش memory limit
export COMPOSER_MEMORY_LIMIT=-1
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader

# روش 2: اگر composer در مسیر دیگری است
php -d memory_limit=512M /usr/local/bin/composer install --no-dev --optimize-autoloader

# روش 3: استفاده از swap (اگر memory خیلی کم است)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
```

## دستورات Deploy

```bash
cd /path/to/bots

# 1. Pull آخرین تغییرات
git pull

# 2. نصب dependencies با memory limit
export COMPOSER_MEMORY_LIMIT=-1
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader

# 3. پاک کردن cache ها
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# 4. اجرای migrations
php artisan migrate --force

# 5. Generate Swagger docs
php artisan l5-swagger:generate

# 6. Cache کردن برای production
php artisan config:cache
php artisan route:cache
```

## تست API ها

بعد از deploy، می‌توانید با دستورات curl از فایل `test_quran_api_final.txt` تست کنید:

```bash
# تست اولیه
curl -X GET "https://bots.pardisania.ir/api/v1/quran/languages" -H "Accept: application/json"
```

## نکات مهم

1. ✅ فایل `config/l5-swagger.php` اصلاح شد
2. ⚠️ اگر composer install هنوز "Killed" می‌دهد، memory limit را افزایش دهید
3. ⚠️ بعد از deploy، route cache را پاک کنید تا route های جدید ثبت شوند
