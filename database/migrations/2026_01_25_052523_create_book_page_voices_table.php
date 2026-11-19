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
        Schema::create('book_page_voices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_page_scan_id')->comment('شناسه اسکن مرتبط');
            $table->unsignedBigInteger('user_id')->nullable()->comment('شناسه کاربر');
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->string('file_id')->comment('file_id وویس');
            $table->string('file_unique_id')->nullable()->comment('file_unique_id وویس');
            $table->integer('points_awarded')->default(0)->comment('امتیاز داده شده (200 برای وویس)');
            $table->timestamps();
            
            $table->foreign('book_page_scan_id')->references('id')->on('book_page_scans')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index('bot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_page_voices');
    }
};
