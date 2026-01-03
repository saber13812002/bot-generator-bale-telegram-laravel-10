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
        // بررسی وجود جدول قبل از تغییر
        if (!Schema::hasTable('quran_translations')) {
            return;
        }

        Schema::table('quran_translations', function (Blueprint $table) {
            // بررسی وجود فیلدها قبل از اضافه کردن
            if (!Schema::hasColumn('quran_translations', 'language')) {
                $table->string('language', 7)->after('translation_id')->nullable();
            }
            if (!Schema::hasColumn('quran_translations', 'translator_name')) {
                $table->string('translator_name', 20)->after('language')->nullable();
            }
            if (!Schema::hasColumn('quran_translations', 'translate_full_name')) {
                $table->string('translate_full_name', 30)->after('translator_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // بررسی وجود جدول قبل از تغییر
        if (!Schema::hasTable('quran_translations')) {
            return;
        }

        Schema::table('quran_translations', function (Blueprint $table) {
            // بررسی وجود فیلدها قبل از حذف
            if (Schema::hasColumn('quran_translations', 'language')) {
                $table->dropColumn('language');
            }
            if (Schema::hasColumn('quran_translations', 'translator_name')) {
                $table->dropColumn('translator_name');
            }
            if (Schema::hasColumn('quran_translations', 'translate_full_name')) {
                $table->dropColumn('translate_full_name');
            }
        });
    }
};


// 0- php artisan migrate : done


// 1- replace
// `fa_ayati` (`
// `quran_translations` (`

// 2- set default values
// fa
// ayati
// fa.ayati

// 3-import

//UPDATE quran_translations SET quran_translations.language = "fa"
//WHERE language IS NULL AND translation_id = 2;
//
//UPDATE quran_translations SET quran_translations.translator_name = "ansarian"
//WHERE translator_name IS NULL AND translation_id = 2;
//
//
//UPDATE quran_translations SET quran_translations.translate_full_name = "fa.ansarian"
//WHERE translate_full_name IS NULL AND translation_id = 2;
//
//
//UPDATE quran_translations SET quran_translations.language = "am"
//WHERE language IS NULL AND translation_id = 1;
//
//UPDATE quran_translations SET quran_translations.translator_name = "sadiq"
//WHERE translator_name IS NULL AND translation_id = 1;
//
//
//UPDATE quran_translations SET quran_translations.translate_full_name = "am.sadiq"
//WHERE translate_full_name IS NULL AND translation_id = 1;
