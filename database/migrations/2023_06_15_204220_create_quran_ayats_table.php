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
        Schema::create('quran_ayats', function (Blueprint $table) {
            $table->id('index');

            $table->unsignedInteger('sura');
            $table->unsignedInteger('aya');
            $table->string('text', 100);
            $table->string('simple', 100);
            $table->unsignedSmallInteger('juz')->nullable();
            $table->unsignedSmallInteger('hezb')->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->unsignedSmallInteger('word')->nullable();
            $table->enum('sajde', ['optional', 'required'])->nullable();
            $table->unsignedInteger('sajde_number')->nullable();
            $table->unsignedInteger('rub')->nullable();
            $table->string('verse_key', 50)->nullable();

            $table->unsignedSmallInteger('theletter')->nullable();
            $table->unsignedSmallInteger('sortnozol')->nullable();
            $table->unsignedSmallInteger('sortalphabet')->nullable();
            $table->enum('meccamedinan', ['mecca', 'medinan'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_ayats');
    }
};
