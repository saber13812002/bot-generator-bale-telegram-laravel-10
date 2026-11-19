<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_poster_tag_settings')) {
            return;
        }

        Schema::create('channel_poster_tag_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('tag', 64);
            $table->boolean('signature_enabled')->default(false);
            $table->timestamps();

            $table->unique(['bot_id', 'tag'], 'cp_tag_settings_bot_tag_unique');
            $table->index('bot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_poster_tag_settings');
    }
};
