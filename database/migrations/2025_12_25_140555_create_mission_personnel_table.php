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
        Schema::create('mission_personnel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('personnel_id');
            $table->enum('status', ['reserved', 'in_progress', 'pending_approval', 'approved', 'rejected', 'cancelled'])->default('reserved');
            $table->text('result_link')->nullable();
            $table->bigInteger('approval_message_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('approved_by_chat_id')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->datetime('rejected_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('mission_id')->references('id')->on('missions')->onDelete('cascade');
            $table->foreign('personnel_id')->references('id')->on('personnel')->onDelete('cascade');
            $table->index('mission_id');
            $table->index('personnel_id');
            $table->index('status');
            $table->index('approval_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_personnel');
    }
};
