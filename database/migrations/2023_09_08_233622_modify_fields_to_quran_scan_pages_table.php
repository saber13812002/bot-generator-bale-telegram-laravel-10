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
        Schema::table('quran_scan_pages', function (Blueprint $table) {
            $table->integer('page')->change();
            $table->bigInteger('bot_chat_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quran_scan_pages', function (Blueprint $table) {
            $table->tinyInteger('page')->change();
            $table->integer('bot_chat_id')->change();
        });
    }
};
