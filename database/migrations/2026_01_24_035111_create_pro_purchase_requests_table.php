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
        Schema::create('pro_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('user_identifier'); // شناسه کاربر در پیام‌رسان
            $table->enum('payment_method', ['card', 'crypto', 'other'])->nullable();
            $table->text('payment_info')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['status', 'bot_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pro_purchase_requests');
    }
};
