<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_admin_kie_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->bigInteger('chat_id');
            $table->string('origin', 20);
            $table->unsignedBigInteger('bot_user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('alias_name')->nullable();
            $table->string('email')->nullable();
            $table->string('webhook_endpoint')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('bot_id');
            $table->index(['chat_id', 'origin']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_admin_kie_requests');
    }
};
