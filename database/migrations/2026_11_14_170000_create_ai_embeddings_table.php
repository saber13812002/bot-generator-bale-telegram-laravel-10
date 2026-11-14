<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-I — RAG & Embedding readiness: embedding store (upserted by embedding jobs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->text('content');
            $table->string('model', 128);
            $table->unsignedSmallInteger('dimensions')->nullable();
            $table->json('vector');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['bot_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }
};
