<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_poster_queue')) {
            return;
        }

        Schema::create('channel_poster_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('tag', 64)->nullable();
            $table->string('content_type', 20);
            $table->text('text')->nullable();
            $table->string('file_id', 512)->nullable();
            $table->boolean('signature_enabled')->default(false);
            $table->dateTime('scheduled_at');
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('owner_chat_id', 64)->nullable();
            $table->string('owner_origin', 20)->default('bale');
            $table->timestamps();

            $table->index('bot_id');
            $table->index(['status', 'scheduled_at'], 'cp_queue_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_poster_queue');
    }
};
