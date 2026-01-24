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
        Schema::create('weather_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->enum('alert_type', ['temperature', 'precipitation', 'wind', 'snow']);
            $table->enum('comparison_type', ['increase', 'decrease', 'absolute']);
            $table->decimal('threshold_value', 10, 2); // مثلاً 5 درجه یا 10 میلی‌متر
            $table->integer('time_hour')->nullable()->comment('ساعت خاص (0-23)');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_user_id', 'is_active']);
            $table->index(['bot_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_alerts');
    }
};
