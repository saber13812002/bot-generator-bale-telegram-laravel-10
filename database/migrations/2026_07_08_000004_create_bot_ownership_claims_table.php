<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_ownership_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_owner_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('verification_code', 32)->unique();
            $table->enum('claim_type', ['owner', 'admin'])->default('owner');
            $table->enum('status', ['pending', 'verified', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('verification_code');
            $table->index(['bot_owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_ownership_claims');
    }
};
