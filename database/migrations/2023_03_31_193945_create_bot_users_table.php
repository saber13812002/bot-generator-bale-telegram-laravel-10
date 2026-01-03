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
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();

            // chat_id	bot_id	status	origin	alias name
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->enum('status', ['suspend', 'active'])->default('suspend');
            $table->enum('origin', ['telegram', 'bale']);
            $table->string('alias_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_users');
    }
};
