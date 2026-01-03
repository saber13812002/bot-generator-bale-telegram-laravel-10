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
        Schema::create('social_publishes', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_id')->default(1);
            $table->string('title');
            $table->string('description')->nullable();
            $table->enum('origin', ['google.com', 'virgool.io', 'aparat.com', 'youtube.com', 'stackoverflow.com']);
            $table->string('tags')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('approved_for')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_publishes');
    }
};
