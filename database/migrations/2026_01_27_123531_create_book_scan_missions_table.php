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
        Schema::create('book_scan_missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->unsignedBigInteger('book_page_scan_id')->comment('شناسه اسکن');
            $table->bigInteger('assigned_to_chat_id')->nullable()->comment('شناسه چت کاربری که ماموریت به او اختصاص داده شده');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending')->comment('وضعیت ماموریت');
            $table->bigInteger('approved_by_chat_id')->nullable()->comment('شناسه چت تاییدکننده');
            $table->timestamp('assigned_at')->nullable()->comment('زمان اختصاص');
            $table->timestamp('completed_at')->nullable()->comment('زمان تکمیل');
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->foreign('book_page_scan_id')->references('id')->on('book_page_scans')->onDelete('cascade');
            $table->index(['bot_id', 'status']);
            $table->index('assigned_to_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_scan_missions');
    }
};
