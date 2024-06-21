<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_uploaded_bank_files', function (Blueprint $table) {
            $table->id();

            $table->integer('bot_id');
            $table->enum('bot_type', ['telegram', 'bale', 'gap', 'etc']);
            $table->integer('chat_id');
            $table->string('file_url', 2500);
            $table->enum('file_type', ['etc', 'photo', 'video', 'file', 'audio', 'music'])->default('etc');
            $table->enum('file_extension', ['etc', 'zip', 'png', 'jpg', 'pdf', 'mp3', 'mp4', 'gif', 'ogg'])->default('etc');
            $table->string('photo_id', 3000);
            $table->json('meta_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_uploaded_bank_files');
    }
};
