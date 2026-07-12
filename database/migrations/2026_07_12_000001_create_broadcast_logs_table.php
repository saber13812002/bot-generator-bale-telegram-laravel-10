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
        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->id();
            
            // اطلاعات ارسال
            $table->string('language', 10)->comment('کد زبان ارسال شده');
            $table->text('message')->comment('متن ارسال شده');
            $table->string('admin_chat_id')->comment('شناسه ادمین ارسال کننده');
            
            // آمار
            $table->integer('total_users')->default(0)->comment('تعداد کل کاربران هدف');
            $table->integer('sent_count')->default(0)->comment('تعداد ارسال موفق');
            $table->integer('error_count')->default(0)->comment('تعداد خطا');
            
            // جزئیات ربات‌ها به صورت JSON
            $table->json('bots_report')->nullable()->comment('گزارش تفکیک ربات‌ها');
            
            // زمان
            $table->timestamp('started_at')->nullable()->comment('زمان شروع ارسال');
            $table->timestamp('completed_at')->nullable()->comment('زمان پایان ارسال');
            $table->integer('duration_seconds')->nullable()->comment('مدت زمان ارسال به ثانیه');
            
            // وضعیت
            $table->enum('status', ['pending', 'sending', 'completed', 'failed'])->default('completed');
            
            $table->timestamps();
            
            // ایندکس برای جستجو
            $table->index('language');
            $table->index('admin_chat_id');
            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_logs');
    }
};
