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
        Schema::create('personnel_message_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->text('message_content')->nullable();
            $table->enum('status', ['queue', 'sent', 'error'])->default('queue');
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->foreign('personnel_id')->references('id')->on('personnel')->onDelete('cascade');
            $table->index('personnel_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel_message_queues');
    }
};
