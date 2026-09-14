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
        Schema::create('ai_provider_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_provider_id')->index();
            $table->string('check_type');        // connection|chat|ping
            $table->string('status');            // success|failed
            $table->integer('ping_ms')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_logs');
    }
};
