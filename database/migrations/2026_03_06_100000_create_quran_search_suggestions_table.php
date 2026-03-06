<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * پیشنهادات جستجوی قرآن: جستجوهای تک‌نتیجه یا کلیک‌شده برای ارسال روزانه به ادمین‌ها.
     */
    public function up(): void
    {
        Schema::create('quran_search_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('search_phrase', 200)->nullable()->comment('عبارت جستجو نرمال‌شده');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedSmallInteger('sura')->nullable();
            $table->unsignedSmallInteger('aya')->nullable();
            $table->bigInteger('chat_id');
            $table->string('type', 20)->comment('bale|telegram');
            $table->string('source', 20)->comment('single_result|clicked');
            $table->timestamps();

            $table->index(['source', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_search_suggestions');
    }
};
