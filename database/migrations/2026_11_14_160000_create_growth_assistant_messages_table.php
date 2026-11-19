<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-H — Growth Companion AI: assistant conversation log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_profile_id');
            $table->unsignedBigInteger('bot_user_id');
            $table->string('role', 16);
            $table->text('content');
            $table->json('usage')->nullable();
            $table->string('error', 64)->nullable();
            $table->unsignedBigInteger('ai_job_id')->nullable();
            $table->timestamps();
            $table->index(['growth_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_assistant_messages');
    }
};
