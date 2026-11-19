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
        Schema::create('prayer_estimates', function (Blueprint $table) {
            $table->id();
            
            // ارجاع به کاربر
            $table->unsignedBigInteger('user_id')->nullable()->comment('ارجاع به جدول bot_users');
            $table->bigInteger('chat_id')->unique()->comment('شناسه چت کاربر');
            
            // تخمین نماز قضا
            $table->integer('total_missed_prayers')->default(0)->comment('تعداد کل نمازهای قضا (تخمینی)');
            $table->integer('total_missed_rakats')->default(0)->comment('تعداد کل رکعات قضا (محاسبه شده)');
            
            // اطلاعات اضافی
            $table->date('start_date')->nullable()->comment('تاریخ شروع محاسبه');
            $table->text('notes')->nullable()->comment('یادداشت کاربر');
            
            $table->timestamps();
            
            // Index
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_estimates');
    }
};
