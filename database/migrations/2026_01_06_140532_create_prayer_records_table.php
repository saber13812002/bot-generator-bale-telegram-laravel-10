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
        Schema::create('prayer_records', function (Blueprint $table) {
            $table->id();
            
            // ارجاع به کاربر
            $table->unsignedBigInteger('user_id')->nullable()->comment('ارجاع به جدول bot_users');
            $table->bigInteger('chat_id')->index()->comment('شناسه چت کاربر');
            $table->unsignedBigInteger('bot_id')->nullable()->comment('شناسه ربات (مادر یا فرزند)');
            
            // اطلاعات نماز
            $table->integer('rakats')->comment('تعداد رکعات (2، 3، 4)');
            $table->enum('prayer_type', ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha', 'optional'])
                  ->default('optional')
                  ->comment('نوع نماز');
            $table->enum('detection_method', ['auto', 'command', 'keyboard', 'manual'])
                  ->default('auto')
                  ->comment('روش تشخیص نوع نماز');
            
            // اطلاعات پلتفرم
            $table->enum('origin', ['telegram', 'bale', 'gap', 'eitaa', 'soroush'])->comment('پلتفرم');
            $table->bigInteger('message_id')->nullable()->comment('شناسه پیام اصلی (برای reply)');
            
            $table->timestamps();
            
            // Indexes برای بهبود performance
            $table->index(['chat_id', 'origin']);
            $table->index(['chat_id', 'created_at']);
            $table->index('prayer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_records');
    }
};
