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
        Schema::create('translation_publishes', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('rss_post_item_translation_id');
            $table->bigInteger('rss_channel_id');
            $table->bigInteger('chat_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_publishes');
    }
};
