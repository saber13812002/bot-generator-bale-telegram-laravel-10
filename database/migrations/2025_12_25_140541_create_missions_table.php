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
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('prompt_id')->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->integer('points')->default(0);
            $table->integer('max_personnel')->default(1);
            $table->integer('current_personnel_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('prompt_id')->references('id')->on('prompts')->onDelete('set null');
            $table->foreign('content_id')->references('id')->on('contents')->onDelete('set null');
            $table->index('tenant_id');
            $table->index('prompt_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
