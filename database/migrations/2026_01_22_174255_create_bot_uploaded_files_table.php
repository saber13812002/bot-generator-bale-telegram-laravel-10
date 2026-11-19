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
        Schema::create('bot_uploaded_files', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('bot_id');
            $table->enum('bot_type', ['telegram', 'bale']);
            $table->string('file_unique_key')->comment('کلید یونیک برای شناسایی فایل');
            $table->enum('file_type', [
                'scan_page',
                'audio_recitation',
                'audio_translation',
                'audio_page',
                'photo',
                'document',
                'video',
                'audio'
            ]);
            $table->string('file_id')->comment('file_id برگشتی از تلگرام/بله');
            $table->string('file_unique_id')->nullable()->comment('file_unique_id از API');
            $table->integer('file_size')->nullable();
            $table->integer('width')->nullable()->comment('برای عکس');
            $table->integer('height')->nullable()->comment('برای عکس');
            $table->json('metadata')->nullable()->comment('اطلاعات اضافی (مثلاً sura, aya, page, hr, reciter, cdn)');
            $table->json('upload_response')->nullable()->comment('پاسخ کامل API آپلود');
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['bot_id', 'bot_type', 'file_unique_key'], 'bot_uploaded_files_unique');
            $table->index('file_unique_key', 'bot_uploaded_files_file_unique_key_index');
            $table->index(['bot_id', 'bot_type', 'file_type'], 'bot_uploaded_files_bot_type_index');
            
            // Foreign key
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_uploaded_files');
    }
};
