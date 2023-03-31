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
        Schema::create('bots', function (Blueprint $table) {
            $table->id();

            // tm_owner	tm_name	tm_token	tm_getme	tm_status
            // block_first
            // ble_owner	ble_name	ble_getme	ble_token	ble_status
            // message_type	message_template	backlists
            $table->bigInteger('telegram_owner_chat_id');
            $table->string('telegram_bot_name')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->json('telegram_get_me_api_response')->nullable();
            $table->enum('telegram_bot_status', ['Active', 'DeActive'])->default('Active');

            $table->bigInteger('bale_owner_chat_id')->nullable();
            $table->string('bale_bot_name')->nullable();
            $table->string('bale_bot_token')->nullable();
            $table->json('bale_get_me_api_response')->nullable();
            $table->enum('bale_bot_status', ['Active', 'DeActive'])->default('Active');

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
        Schema::dropIfExists('bots');
    }
};
