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
        Schema::create('rss_post_item_translation_queues', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('rss_post_item_translation_id');
            $table->bigInteger('rss_channel_id');

            $table->enum('status', ['queue', 'sent', 'error']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rss_post_item_translation_queues');
    }
};
