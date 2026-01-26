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
        Schema::create('book_page_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_page_id')->comment('شناسه صفحه');
            $table->unsignedBigInteger('user_id')->nullable()->comment('شناسه کاربر (bot_users)');
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->integer('page_number')->comment('شماره صفحه (برای دسترسی سریع)');
            $table->string('file_id')->comment('file_id اسکن');
            $table->string('file_unique_id')->nullable()->comment('file_unique_id اسکن');
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
            $table->bigInteger('approval_message_id')->nullable()->comment('شناسه پیام در گروه نظارت');
            $table->bigInteger('approved_by_chat_id')->nullable()->comment('شناسه تاییدکننده');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('points_awarded')->default(0)->comment('امتیاز داده شده (100 برای اسکن)');
            $table->timestamps();
            
            $table->foreign('book_page_id')->references('id')->on('book_pages')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index('bot_id');
            $table->index('status');
            $table->index(['bot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_page_scans');
    }
};
