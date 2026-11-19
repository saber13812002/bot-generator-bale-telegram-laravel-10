<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_bot_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rating_bot_id');
            $table->string('chat_id');
            $table->string('origin', 20);
            $table->unsignedInteger('item_index');
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->timestamps();

            $table->foreign('rating_bot_id')->references('id')->on('rating_bots')->onDelete('cascade');
            $table->unique(['rating_bot_id', 'chat_id', 'origin', 'item_index'], 'rating_resp_unique');
            $table->index(['rating_bot_id', 'origin'], 'rating_resp_ratingbot_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_bot_responses');
    }
};

