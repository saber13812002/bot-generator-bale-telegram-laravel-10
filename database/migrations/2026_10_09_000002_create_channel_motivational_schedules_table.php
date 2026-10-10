<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_motivational_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('destination_id');
            $table->unsignedTinyInteger('day_of_week')->default(5); // 0=Sunday..6=Saturday (پیش‌فرض: جمعه)
            $table->string('frequency', 16)->default('weekly'); // weekly|biweekly|daily
            $table->text('prompt')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('sent_texts')->nullable();
            $table->timestamps();

            $table->index('destination_id');
            $table->index(['enabled', 'day_of_week', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_motivational_schedules');
    }
};
