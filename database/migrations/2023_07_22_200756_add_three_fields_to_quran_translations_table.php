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
        Schema::table('quran_translations', function (Blueprint $table) {
            $table->string('language', 7)->after('translation_id')->nullable();
            $table->string('translator_name', 20)->after('language')->nullable();
            $table->string('translate_full_name', 30)->after('translator_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quran_translations', function (Blueprint $table) {
            $table->dropColumn('language');
            $table->dropColumn('translator_name');
            $table->dropColumn('translate_full_name');
        });
    }
};


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
