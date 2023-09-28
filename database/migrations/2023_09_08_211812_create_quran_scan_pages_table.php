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
        Schema::create('quran_scan_pages', function (Blueprint $table) {
            $table->id();

            $table->tinyInteger('hr');
            $table->tinyInteger('page');
            $table->enum('type', ['bale', 'telegram', 'gap', 'soroosh']);
            $table->string('file_id');
            $table->string('file_unique_id');
            $table->integer('width');
            $table->integer('height');
            $table->integer('file_size');
            $table->integer('bot_chat_id');
            $table->integer('bot_id');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_scan_pages');
    }
};
