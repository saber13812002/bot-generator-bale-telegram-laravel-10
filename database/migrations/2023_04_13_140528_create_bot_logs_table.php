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
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();

            $table->string('webhook_endpoint_uri', 30);
            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('language', 7)->nullable();
            $table->string('locale', 7)->nullable();
            $table->enum('type', ['bale', 'telegram'])->nullable();
            $table->string('text', 20);
            $table->boolean('is_command', 1)->nullable();
            $table->bigInteger('channel_group_type')->nullable();
            $table->unsignedInteger('bot_id')->nullable();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->bigInteger('from_id')->nullable();
            $table->bigInteger('from_chat_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
