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
        Schema::create('poem_collaborations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_poem_id')->comment('شناسه شعر اصلی');
            $table->unsignedBigInteger('forked_poem_id')->comment('شناسه شعر فورک شده');
            $table->unsignedBigInteger('forked_by')->comment('شناسه کاربر فورک کننده');
            $table->timestamps();
            
            $table->foreign('original_poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->foreign('forked_poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->index('original_poem_id');
            $table->index('forked_poem_id');
            $table->index('forked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_collaborations');
    }
};
