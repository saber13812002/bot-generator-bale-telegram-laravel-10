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
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // Hashed token
            $table->text('plain_token')->nullable(); // Encrypted plain token (for first time display)
            $table->string('name')->nullable(); // نام توکن (اختیاری)
            $table->enum('type', ['tenant', 'super_admin'])->default('tenant');
            $table->unsignedBigInteger('tenant_id')->nullable(); // برای tenant token
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('token');
            $table->index('type');
            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
