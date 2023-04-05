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
        Schema::create('quran_words', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('aya');
            $table->unsignedInteger('sura');
            $table->unsignedInteger('position');
            $table->string('verse_key', 10);
            $table->string('text', 100);
            $table->string('simple', 100);
            $table->unsignedInteger('juz')->nullable();
            $table->unsignedInteger('hezb')->nullable();
            $table->unsignedInteger('rub')->nullable();
            $table->unsignedInteger('page');
            $table->string('class_name', 10);
            $table->unsignedInteger('line');
            $table->string('code', 20);
            $table->string('code_v3', 20);
            $table->string('char_type', 20);
            $table->string('audio', 50);
            $table->string('translation', 200);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_words');
    }
};
