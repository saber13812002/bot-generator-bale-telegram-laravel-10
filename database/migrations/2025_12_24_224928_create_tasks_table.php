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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->unsignedBigInteger('assigned_user_id'); // personnel_id
            $table->enum('task_status', ['reserved', 'in_progress', 'pending_approval', 'approved', 'rejected'])->default('reserved');
            $table->datetime('task_time')->nullable(); // زمان انجام تسک
            $table->datetime('assigned_time')->nullable(); // زمان اختصاص تسک
            $table->datetime('reserved_time')->nullable(); // زمان رزرو (2 ساعت آینده)
            $table->integer('points')->default(0); // امتیاز تسک
            $table->text('final_link')->nullable(); // لینک نهایی ارسال شده توسط کاربر
            $table->text('rejection_reason')->nullable(); // دلیل رد (در صورت رد شدن)
            $table->unsignedBigInteger('approved_by_chat_id')->nullable(); // chat_id فرد تایید کننده
            $table->datetime('approved_at')->nullable(); // زمان تایید
            $table->datetime('rejected_at')->nullable(); // زمان رد
            $table->timestamps();

            $table->foreign('assigned_user_id')->references('id')->on('personnel')->onDelete('cascade');
            $table->index('assigned_user_id');
            $table->index('task_status');
            $table->index('reserved_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
