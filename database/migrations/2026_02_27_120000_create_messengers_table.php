<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table for blog-bot channel broadcast: one row per user with tokens and channel chat_ids for Bale, Telegram, Eitaa.
     */
    public function up(): void
    {
        Schema::create('messengers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->unsignedBigInteger('bale_channel_chat_id')->nullable();
            $table->unsignedBigInteger('bale_admin_chat_id')->nullable();
            $table->string('bale_bot_token', 60)->nullable();
            $table->string('bale_channel_invite_link', 200)->nullable();

            $table->unsignedBigInteger('telegram_channel_chat_id')->nullable();
            $table->unsignedBigInteger('telegram_admin_chat_id')->nullable();
            $table->string('telegram_bot_token', 60)->nullable();
            $table->string('telegram_channel_invite_link', 200)->nullable();

            $table->unsignedBigInteger('eitaa_channel_chat_id')->nullable();
            $table->unsignedBigInteger('eitaa_admin_chat_id')->nullable();
            $table->string('eitaa_bot_token', 60)->nullable();
            $table->string('eitaa_channel_invite_link', 200)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messengers');
    }
};
