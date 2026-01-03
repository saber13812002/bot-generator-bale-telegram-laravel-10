<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // بررسی وجود جدول با raw SQL
            $tableExists = \DB::select("SHOW TABLES LIKE 'blog_users'");
            if (empty($tableExists)) {
                return;
            }

            // بررسی وجود فیلد با raw SQL
            $columnExists = \DB::select("SHOW COLUMNS FROM `blog_users` LIKE 'type'");
            if (empty($columnExists)) {
                return;
            }

            // استفاده از raw SQL برای تغییر enum (به دلیل مشکل Doctrine DBAL)
            \DB::statement("ALTER TABLE `blog_users` MODIFY COLUMN `type` ENUM('bale', 'telegram', 'gap', 'soroosh') NULL");
        } catch (\Exception $e) {
            // در صورت خطا، migration را skip می‌کنیم
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // بررسی وجود جدول با raw SQL
            $tableExists = \DB::select("SHOW TABLES LIKE 'blog_users'");
            if (empty($tableExists)) {
                return;
            }

            // بررسی وجود فیلد با raw SQL
            $columnExists = \DB::select("SHOW COLUMNS FROM `blog_users` LIKE 'type'");
            if (empty($columnExists)) {
                return;
            }

            // استفاده از raw SQL برای تغییر enum (به دلیل مشکل Doctrine DBAL)
            \DB::statement("ALTER TABLE `blog_users` MODIFY COLUMN `type` ENUM('bale', 'telegram') NULL");
        } catch (\Exception $e) {
            // در صورت خطا، migration را skip می‌کنیم
            return;
        }
    }
};
