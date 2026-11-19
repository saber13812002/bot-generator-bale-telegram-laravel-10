<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تنظیمات ادمین کانال با صف رسانه: کدام صف به کدام کانال‌ها ارسال شود.
     * اگر جدول از قبل وجود دارد (اجرای ناموفق قبلی)، فقط ایندکسها اضافه می‌شوند.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admin_channel_media_queue_configs')) {
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

                $table->index(['admin_chat_id', 'media_queue_id'], 'acmq_configs_admin_queue_idx');
                $table->index('is_active', 'acmq_configs_active_idx');
            });
            return;
        }

        // جدول از قبل وجود دارد؛ فقط ایندکسها را اضافه کن (اگر نباشند)
        try {
            Schema::table('admin_channel_media_queue_configs', function (Blueprint $table) {
                $table->index(['admin_chat_id', 'media_queue_id'], 'acmq_configs_admin_queue_idx');
            });
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
        try {
            Schema::table('admin_channel_media_queue_configs', function (Blueprint $table) {
                $table->index('is_active', 'acmq_configs_active_idx');
            });
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_media_queue_configs');
    }
};
