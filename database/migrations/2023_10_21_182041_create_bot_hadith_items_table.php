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
        Schema::create('bot_hadith_items', function (Blueprint $table) {
            $table->id();
            $table->string("_id")->index('_id');
            $table->text("arabic")->nullable();
            $table->text("persian")->nullable();
            $table->text("english")->nullable();
            $table->string("book")->nullable();
            $table->string("number")->nullable();
            $table->string("part")->nullable();
            $table->string("chapter")->nullable();
            $table->string("section")->nullable();
            $table->string("volume")->nullable();
            $table->json("tags")->nullable();
            $table->json("related")->nullable();
            $table->json("history")->nullable();
            $table->json("gradings")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_hadith_items');
    }
};
