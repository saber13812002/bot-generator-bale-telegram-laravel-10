<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_creation_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_id')->comment('شناسه endpoint انتخابی');
            $table->string('channel')->comment('کانال: telegram, bale, web');
            $table->string('user_id')->comment('شناسه کاربر (chat_id یا owner_id)');
            $table->unsignedBigInteger('bot_owner_id')->nullable()->comment('شناسه مالک (پنل وب)');
            $table->string('status')->default('in_progress')->comment('وضعیت: in_progress, completed, abandoned');
            $table->unsignedInteger('current_step')->default(0)->comment('گام فعلی');
            $table->json('collected_data')->nullable()->comment('داده‌های جمع‌آوری شده');
            $table->json('step_results')->nullable()->comment('نتایج هر گام');
            $table->unsignedBigInteger('bot_id')->nullable()->comment('شناسه ربات ساخته شده');
            $table->text('error_message')->nullable()->comment('پیام خطا');
            $table->timestamp('expires_at')->nullable()->comment('زمان انقضا');
            $table->timestamps();

            $table->index(['user_id', 'channel', 'status']);
            $table->index('bot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_creation_sessions');
    }
};
