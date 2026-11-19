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
        Schema::create('book_user_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->unsignedBigInteger('user_id')->comment('شناسه کاربر (bot_users)');
            $table->integer('total_points')->default(0)->comment('مجموع امتیازات');
            $table->integer('scans_count')->default(0)->comment('تعداد اسکن‌های تایید شده');
            $table->integer('voices_count')->default(0)->comment('تعداد وویس‌های تایید شده');
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique(['bot_id', 'user_id']);
            $table->index('bot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_user_scores');
    }
};
