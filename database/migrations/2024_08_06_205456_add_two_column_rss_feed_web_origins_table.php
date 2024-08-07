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
        Schema::table('rss_feed_web_origins', function (Blueprint $table) {
            $table->string('audio_book_id')->after('media_id')->nullable(); // Store the audioBookId
            $table->json('details')->after('description')->nullable(); // Field to store the JSON response
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropColumns('audio_book_id');
        Schema::dropColumns('details');
    }
};
