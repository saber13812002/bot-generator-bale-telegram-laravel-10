<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_poster_publish_logs')) {
            return;
        }

        Schema::create('channel_poster_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('queue_id')->nullable();
            $table->unsignedBigInteger('destination_id');
            $table->string('platform', 20);
            $table->boolean('success');
            $table->string('message_id', 128)->nullable();
            $table->text('error')->nullable();
            $table->dateTime('published_at');
            $table->timestamps();

            $table->index('bot_id');
            $table->index('queue_id');
            $table->index('destination_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_poster_publish_logs');
    }
};
