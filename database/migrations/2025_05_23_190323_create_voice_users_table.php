<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_users', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->enum('status', ['suspend', 'active'])->default('suspend');
            $table->enum('origin', ['bale', 'telegram', 'gap', 'soroosh'])->nullable();
            $table->string('alias_name')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_users');
    }
};
