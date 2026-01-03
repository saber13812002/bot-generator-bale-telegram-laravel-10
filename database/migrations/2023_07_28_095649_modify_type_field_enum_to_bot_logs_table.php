<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // بررسی وجود جدول قبل از تغییر
        if (!Schema::hasTable('bot_logs')) {
            return;
        }

        // بررسی وجود فیلد قبل از تغییر
        if (!Schema::hasColumn('bot_logs', 'type')) {
            return;
        }

        // استفاده از raw SQL برای تغییر enum (به دلیل مشکل Doctrine DBAL)
        \DB::statement("ALTER TABLE `bot_logs` MODIFY COLUMN `type` ENUM('bale', 'telegram', 'gap', 'soroosh') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // بررسی وجود جدول قبل از تغییر
        if (!Schema::hasTable('bot_logs')) {
            return;
        }

        // بررسی وجود فیلد قبل از تغییر
        if (!Schema::hasColumn('bot_logs', 'type')) {
            return;
        }

        // استفاده از raw SQL برای تغییر enum (به دلیل مشکل Doctrine DBAL)
        \DB::statement("ALTER TABLE `bot_logs` MODIFY COLUMN `type` ENUM('bale', 'telegram') NULL");
    }
};
