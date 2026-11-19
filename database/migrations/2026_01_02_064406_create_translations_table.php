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
        // بررسی وجود جدول قبل از ایجاد
        if (!Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('language', 100);
                $table->string('name', 255);
                $table->string('translator', 255);
                $table->string('filename', 500);
                $table->timestamps();
            });
        } else {
            // اگر جدول وجود دارد، بررسی فیلدها و اضافه کردن در صورت نیاز
            Schema::table('translations', function (Blueprint $table) {
                if (!Schema::hasColumn('translations', 'language')) {
                    $table->string('language', 100)->after('id');
                }
                if (!Schema::hasColumn('translations', 'name')) {
                    $table->string('name', 255)->after('language');
                }
                if (!Schema::hasColumn('translations', 'translator')) {
                    $table->string('translator', 255)->after('name');
                }
                if (!Schema::hasColumn('translations', 'filename')) {
                    $table->string('filename', 500)->after('translator');
                }
                if (!Schema::hasColumn('translations', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // فقط در صورت وجود جدول، آن را حذف می‌کنیم
        if (Schema::hasTable('translations')) {
            Schema::dropIfExists('translations');
        }
    }
};
