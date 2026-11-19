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
        Schema::create('psychology_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psychology_test_bot_id');
            $table->bigInteger('chat_id');
            $table->enum('origin', ['telegram', 'bale']);
            $table->json('result_data'); // شامل امتیازات هر دسته
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('psychology_test_bot_id')->references('id')->on('psychology_test_bots')->onDelete('cascade');
            $table->index(['psychology_test_bot_id', 'chat_id', 'origin'], 'psych_test_results_bot_chat_origin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychology_test_results');
    }
};
