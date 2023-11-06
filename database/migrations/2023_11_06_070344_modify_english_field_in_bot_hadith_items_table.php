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
        Schema::table('bot_hadith_items', function (Blueprint $table) {
            $table->longText("english")->nullable()->change();
            $table->longText("arabic")->nullable()->change();
            $table->longText("persian")->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_hadith_items', function (Blueprint $table) {
            $table->text("english")->nullable()->change();
            $table->text("arabic")->nullable()->change();
            $table->text("persian")->nullable()->change();
        });
    }
};
