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
        if (!Schema::hasTable('quran_translations')) {
            Schema::create('quran_translations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('translation_id')->nullable();
                $table->string('language', 7)->nullable();
                $table->string('translator_name', 20)->nullable();
                $table->string('translate_full_name', 30)->nullable();
                $table->unsignedInteger('index')->nullable();
                $table->unsignedInteger('sura');
                $table->unsignedInteger('aya');
                $table->text('text');
                $table->timestamps();
            });
        } else {
            // اگر جدول وجود دارد، بررسی فیلدها و اضافه کردن در صورت نیاز
            Schema::table('quran_translations', function (Blueprint $table) {
                if (!Schema::hasColumn('quran_translations', 'translation_id')) {
                    $table->unsignedInteger('translation_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('quran_translations', 'language')) {
                    $table->string('language', 7)->nullable()->after('translation_id');
                }
                if (!Schema::hasColumn('quran_translations', 'translator_name')) {
                    $table->string('translator_name', 20)->nullable()->after('language');
                }
                if (!Schema::hasColumn('quran_translations', 'translate_full_name')) {
                    $table->string('translate_full_name', 30)->nullable()->after('translator_name');
                }
                if (!Schema::hasColumn('quran_translations', 'index')) {
                    $table->unsignedInteger('index')->nullable()->after('translate_full_name');
                }
                if (!Schema::hasColumn('quran_translations', 'sura')) {
                    $table->unsignedInteger('sura')->after('index');
                }
                if (!Schema::hasColumn('quran_translations', 'aya')) {
                    $table->unsignedInteger('aya')->after('sura');
                }
                if (!Schema::hasColumn('quran_translations', 'text')) {
                    $table->text('text')->after('aya');
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
        if (Schema::hasTable('quran_translations')) {
            Schema::dropIfExists('quran_translations');
        }
    }
};
