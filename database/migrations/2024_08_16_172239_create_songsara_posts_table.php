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
        Schema::create('songsara_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('media_id')->nullable();
            $table->string('title'); // Post title
            $table->string('audio_link')->nullable(); // Audio link
            $table->string('image_link')->nullable(); // Audio link
            $table->text('description')->nullable(); // Optional description
            $table->string('artist')->nullable();
            $table->string('genre')->nullable();
            $table->date('release_date')->nullable();
            $table->string('url')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songsara_posts');
    }
};
