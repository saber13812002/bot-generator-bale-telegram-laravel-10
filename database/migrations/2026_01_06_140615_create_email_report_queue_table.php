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
        Schema::create('email_report_queue', function (Blueprint $table) {
            $table->id();
            
            // ارجاع به کاربر
            $table->unsignedBigInteger('user_id')->nullable()->comment('ارجاع به جدول bot_users');
            $table->bigInteger('chat_id')->comment('شناسه چت کاربر');
            $table->string('email')->comment('ایمیل مقصد');
            
            // دوره گزارش
            $table->date('report_period_start')->comment('شروع دوره گزارش');
            $table->date('report_period_end')->comment('پایان دوره گزارش');
            
            // وضعیت ارسال
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->comment('وضعیت ارسال');
            $table->timestamp('sent_at')->nullable()->comment('زمان ارسال');
            $table->text('failed_reason')->nullable()->comment('دلیل خطا در صورت شکست');
            $table->integer('retry_count')->default(0)->comment('تعداد تلاش مجدد');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_report_queue');
    }
};
