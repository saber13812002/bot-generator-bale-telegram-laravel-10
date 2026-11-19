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
        Schema::create('psychology_test_bot_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psychology_test_bot_id');
            $table->bigInteger('chat_id');
            $table->enum('origin', ['telegram', 'bale']);
            $table->boolean('is_creator')->default(false);
            $table->timestamps();
            
            $table->foreign('psychology_test_bot_id')->references('id')->on('psychology_test_bots')->onDelete('cascade');
            $table->unique(['psychology_test_bot_id', 'chat_id', 'origin'], 'psych_test_admins_bot_chat_origin_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychology_test_bot_admins');
    }
};
