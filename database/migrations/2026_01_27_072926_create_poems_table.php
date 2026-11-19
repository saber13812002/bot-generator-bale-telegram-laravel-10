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
        Schema::create('poems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id')->comment('شناسه کاربر ربات');
            $table->integer('bot_mother_id')->comment('شناسه Bot Mother');
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->string('title')->nullable()->comment('عنوان شعر');
            $table->enum('poem_type', ['classic', 'novel'])->default('classic')->comment('نوع شعر: کلاسیک یا نو');
            $table->enum('status', ['draft', 'published'])->default('draft')->comment('وضعیت: پیش‌نویس یا منتشر شده');
            $table->unsignedInteger('likes_count')->default(0)->comment('تعداد لایک‌ها');
            $table->timestamps();
            
            $table->index(['bot_user_id', 'bot_mother_id']);
            $table->index('bot_id');
            $table->index('status');
            $table->index('poem_type');
            $table->index('likes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poems');
    }
};
