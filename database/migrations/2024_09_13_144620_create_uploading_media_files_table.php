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
        Schema::create('uploading_media_files', function (Blueprint $table) {
            $table->id();

            $table->string('title')->nullable();
            $table->morphs('model'); // This adds model_type and model_id fields
            $table->string('media_url');
            $table->enum('media_type', ['etc', 'mp3', 'mp4', 'jpg']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploading_media_files');
    }
};
