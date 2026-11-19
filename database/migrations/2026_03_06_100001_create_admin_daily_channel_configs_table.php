<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تنظیمات کانال‌های ربات ادمین کانال روزانه (تک‌آیه/حدیث/نهج/شراب بهشتی).
     */
    public function up(): void
    {
        Schema::create('admin_daily_channel_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_chat_id')->comment('ادمینی که در ربات مادر تنظیم کرده');
            $table->string('content_type', 30)->comment('verse|hadith|nahj|sharabe_beheshti');
            $table->bigInteger('bale_channel_chat_id')->nullable();
            $table->bigInteger('telegram_channel_chat_id')->nullable();
            $table->string('eitaa_channel_chat_id', 50)->nullable()->comment('شناسه ارسال در ایتا');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'content_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_daily_channel_configs');
    }
};
