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
        // FULLTEXT indexes are MySQL-only. Skip for SQLite (used in testing).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('quran_ayats', function (Blueprint $table) {
            $table->fullText('simple')->language('arabic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('quran_ayats', function (Blueprint $table) {
            $table->dropFullText('simple');
        });
    }
};
