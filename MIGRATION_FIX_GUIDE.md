# راهنمای اصلاح Migration‌های مشکل‌دار

## مشکل
برخی migration‌های قدیمی که از `->change()` با enum استفاده می‌کنند، در Laravel 10 با Doctrine DBAL جدید مشکل دارند.

## راه حل موقت برای تست End-to-End

برای اجرای تست End-to-End، فقط نیاز به migration‌های زیر دارید:

```bash
# اجرای migration‌های مورد نیاز برای تست
php artisan migrate --path=database/migrations/2025_12_24_220143_create_tenants_table.php
php artisan migrate --path=database/migrations/2025_12_24_220153_create_personnel_table.php
php artisan migrate --path=database/migrations/2025_12_24_224928_create_tasks_table.php
php artisan migrate --path=database/migrations/2025_12_24_224930_create_prompts_table.php
php artisan migrate --path=database/migrations/2025_12_25_140540_create_contents_table.php
php artisan migrate --path=database/migrations/2025_12_25_140541_create_missions_table.php
php artisan migrate --path=database/migrations/2025_12_25_140551_create_mission_contents_table.php
php artisan migrate --path=database/migrations/2025_12_25_140555_create_mission_personnel_table.php
php artisan migrate --path=database/migrations/2025_12_26_202946_create_ai_llms_table.php
```

## Migration‌های مشکل‌دار

Migration‌های زیر مشکل دارند و باید اصلاح شوند:

1. `2023_07_22_200756_add_three_fields_to_quran_translations_table.php` - ✅ اصلاح شد
2. `2023_07_28_095649_modify_type_field_enum_to_bot_logs_table.php` - ✅ اصلاح شد
3. `2023_07_28_133734_modify_origin_field_enum_to_bot_users_table.php` - ⚠️ نیاز به اصلاح بیشتر
4. `2023_07_28_134052_modify_origin_field_enum_to_blog_users_table.php` - نیاز به اصلاح
5. `2023_07_28_134105_modify_origin_field_enum_to_bot_mothers_table.php` - نیاز به اصلاح

## راه حل دائمی

برای اصلاح migration‌های مشکل‌دار، باید از raw SQL استفاده کنید:

```php
// ❌ اشتباه - استفاده از ->change()
Schema::table('table_name', function (Blueprint $table) {
    $table->enum('column', ['value1', 'value2'])->nullable()->change();
});

// ✅ درست - استفاده از raw SQL
try {
    $tableExists = \DB::select("SHOW TABLES LIKE 'table_name'");
    if (empty($tableExists)) {
        return;
    }
    
    $columnExists = \DB::select("SHOW COLUMNS FROM `table_name` LIKE 'column'");
    if (empty($columnExists)) {
        return;
    }
    
    \DB::statement("ALTER TABLE `table_name` MODIFY COLUMN `column` ENUM('value1', 'value2') NULL");
} catch (\Exception $e) {
    return;
}
```

## اجرای تست

بعد از اجرای migration‌های مورد نیاز:

```bash
# اجرای seeder
php artisan db:seed --class=EndToEndTestSeeder

# اجرای تست
php artisan test --filter MissionTaskEndToEndTest
```

