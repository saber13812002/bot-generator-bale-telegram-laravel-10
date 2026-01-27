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
        Schema::create('book_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->bigInteger('chat_id')->comment('شناسه چت کاربر');
            $table->enum('type', ['cover', 'scan', 'voice'])->comment('نوع draft');
            $table->string('file_id')->nullable()->comment('file_id فایل');
            $table->string('file_unique_id')->nullable()->comment('file_unique_id فایل');
            $table->string('isbn')->nullable()->comment('ISBN/شابک');
            $table->string('book_name')->nullable()->comment('نام کتاب');
            $table->unsignedBigInteger('book_id')->nullable()->comment('شناسه کتاب (اگر وصل شده)');
            $table->integer('page_number')->nullable()->comment('شماره صفحه');
            $table->enum('status', ['draft', 'attached', 'completed'])->default('draft')->comment('وضعیت');
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_id', 'chat_id']);
            $table->index(['bot_id', 'chat_id', 'status']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_drafts');
    }
};
