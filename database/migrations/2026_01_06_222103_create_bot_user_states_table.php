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
        Schema::create('bot_user_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->integer('bot_mother_id');
            $table->string('state', 50);
            $table->json('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('bot_user_id')
                  ->references('id')
                  ->on('bot_users')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index(['bot_user_id', 'state']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_user_states');
    }
};
