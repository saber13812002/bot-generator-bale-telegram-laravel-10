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
        Schema::create('book_publishing_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_page_scan_id')->comment('شناسه اسکن');
            $table->unsignedBigInteger('book_page_voice_id')->nullable()->comment('شناسه وویس');
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->unsignedBigInteger('channel_id')->comment('شناسه کانال انتشار');
            $table->timestamp('scheduled_at')->nullable()->comment('زمان برنامه‌ریزی شده (بین 7 شب تا 12 شب)');
            $table->timestamp('published_at')->nullable()->comment('زمان انتشار واقعی');
            $table->enum('status', ['pending', 'published', 'failed'])->default('pending');
            $table->timestamps();
            
            $table->foreign('book_page_scan_id')->references('id')->on('book_page_scans')->onDelete('cascade');
            $table->foreign('book_page_voice_id')->references('id')->on('book_page_voices')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            // $table->foreign('channel_id')->references('id')->on('book_publishing_channels')->onDelete('cascade');
            $table->index(['bot_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_publishing_queue');
    }
};
