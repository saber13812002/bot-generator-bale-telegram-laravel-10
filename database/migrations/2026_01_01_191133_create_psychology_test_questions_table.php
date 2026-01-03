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
        Schema::create('psychology_test_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psychology_test_bot_id');
            $table->unsignedBigInteger('psychology_test_category_id');
            $table->text('question_text');
            $table->decimal('weight', 3, 2)->default(1.00); // وزن سوال (مثلاً 0.50 تا 1.00)
            $table->tinyInteger('direction')->default(1); // 0 = خیلی کم به سمت دسته، 1 = خیلی زیاد به سمت دسته
            $table->integer('order')->default(0); // ترتیب نمایش (برای رندوم کردن بعداً)
            $table->timestamps();
            
            $table->foreign('psychology_test_bot_id')->references('id')->on('psychology_test_bots')->onDelete('cascade');
            $table->foreign('psychology_test_category_id')->references('id')->on('psychology_test_categories')->onDelete('cascade');
            $table->index(['psychology_test_bot_id', 'order'], 'psych_test_questions_bot_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychology_test_questions');
    }
};
