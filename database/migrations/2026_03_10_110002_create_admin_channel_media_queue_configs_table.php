<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تنظیمات ادمین کانال با صف رسانه: کدام صف به کدام کانال‌ها ارسال شود.
     */
    public function up(): void
    {
        Schema::create('admin_channel_media_queue_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_chat_id');
            $table->foreignId('media_queue_id')->constrained('media_queues')->cascadeOnDelete();
            $table->bigInteger('bale_channel_chat_id')->nullable();
            $table->bigInteger('telegram_channel_chat_id')->nullable();
            $table->string('eitaa_channel_chat_id', 50)->nullable();
            $table->unsignedInteger('last_sent_item_index')->default(0)->comment('ایندکس آخرین آیتم ارسال‌شده برای نوبت‌دهی');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['admin_chat_id', 'media_queue_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_media_queue_configs');
    }
};
