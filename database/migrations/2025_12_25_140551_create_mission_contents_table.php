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
        Schema::create('mission_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('content_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('mission_id')->references('id')->on('missions')->onDelete('cascade');
            $table->foreign('content_id')->references('id')->on('contents')->onDelete('cascade');
            $table->index('mission_id');
            $table->index('content_id');
            $table->index('sort_order');
            $table->unique(['mission_id', 'content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_contents');
    }
};
