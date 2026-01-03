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
        Schema::create('bot_mothers', function (Blueprint $table) {
            $table->id();

            $table->string('user_name', 40);
            $table->unsignedBigInteger('bot_chat_id');
            $table->string('token', 60);
            $table->enum('type', ['bale', 'telegram']);
            $table->string('owner_user_name', 40);
            $table->unsignedBigInteger('owner_chat_id');
            $table->string('owner_first_name', 40);
            $table->string('owner_last_name', 40);

            $table->string('algorithm_name', 30);
            $table->unsignedTinyInteger('algorithm_version');
            $table->json('get_me_api_response')->nullable();
            $table->enum('bot_status', ['Active', 'DeActive'])->default('DeActive');
            $table->boolean('webhook_is_set')->default(false);

            $table->enum('block_strategy', ['block_first', 'block_by_admin'])->default('block_first');
            $table->enum('supported_message_types', ['text', 'all'])->default('text');
            $table->string('supported_message_template')->default('https://');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_mothers');
    }
};
