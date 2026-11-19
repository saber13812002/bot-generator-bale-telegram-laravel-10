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
        Schema::create('poem_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->comment('شناسه شعر');
            $table->unsignedBigInteger('bot_user_id')->comment('شناسه کاربر لایک کننده');
            $table->timestamps();
            
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->unique(['poem_id', 'bot_user_id'], 'poem_user_like_unique');
            $table->index('poem_id');
            $table->index('bot_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_likes');
    }
};
