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
        Schema::create('pro_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->enum('status', ['pending', 'active', 'expired'])->default('pending');
            $table->timestamp('purchase_requested_at');
            $table->timestamp('purchase_confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by_admin_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('payment_info')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique(['bot_user_id', 'bot_id'], 'pro_users_unique');
            $table->index(['bot_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pro_users');
    }
};
