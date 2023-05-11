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
        Schema::create('blog_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('blog_token');
            $table->unsignedInteger('blog_user_id');
            $table->unsignedBigInteger('chat_id');
            $table->enum('type', ['bale', 'telegram']);
            $table->string('language', 7)->default('fa');
            $table->string('locale', 7)->default('fa');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_users');
    }
};
