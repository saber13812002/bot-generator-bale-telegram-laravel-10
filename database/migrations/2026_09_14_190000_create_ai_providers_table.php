<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_providers');

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                     // نام نمایشی (مثلاً: ISMC Server)
            $table->string('base_url');                                 // آدرس پایه API
            $table->text('api_key');                                    // توکن (رمزنگاری خودکار در مدل)
            $table->string('model_name')->nullable();                   // مدل پیش‌فرض
            $table->text('default_prompt')->nullable();                 // پرامپت پیش‌فرض
            $table->string('provider_type')->default('openai_compatible'); // نوع سرویس‌دهنده
            $table->boolean('is_active')->default(true);                // وضعیت فعال

            // نوتیفیکیشن اختصاصی هر سرویس‌دهنده
            $table->string('notify_bot_token')->nullable();             // توکن ربات (bale/telegram)
            $table->string('notify_chat_id')->nullable();               // چت آیدی گیرنده
            $table->string('notify_platform')->nullable()->default('bale'); // bale|telegram

            // وضعیت آخرین تست
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();             // success|failed
            $table->text('last_test_error')->nullable();
            $table->integer('last_ping_ms')->nullable();                // پینگ (میلی‌ثانیه)
            $table->json('available_models')->nullable();               // لیست مدل‌ها (cache)
            $table->json('settings')->nullable();                       // تنظیمات اضافی

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
