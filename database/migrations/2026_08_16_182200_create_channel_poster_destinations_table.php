<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('channel_poster_destinations');
        Schema::enableForeignKeyConstraints();

        Schema::create('channel_poster_destinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('platform', 20);
            $table->string('channel_chat_id', 64);
            $table->string('channel_title')->nullable();
            $table->string('bot_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('bot_id');
            $table->unique(['bot_id', 'platform'], 'channel_poster_destinations_bot_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_poster_destinations');
    }
};
