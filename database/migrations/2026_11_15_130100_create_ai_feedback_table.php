<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — بازخورد customerها در دوره pilot (brief §8).
 *
 * record هر بازخورد (rating 1-5 و/یا comment) با bot/feature/customer.
 * status flow: new → in_progress → resolved (یا ignored).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('feature', 64)->default('general')->index();
            $table->string('category', 32)->default('general'); // general|bug|quality|missing|other
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->string('status', 32)->default('new')->index(); // new|in_progress|resolved|ignored
            $table->string('resolved_by', 128)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
