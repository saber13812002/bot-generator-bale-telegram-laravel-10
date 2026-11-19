<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_read_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('destination_id');
            $table->unsignedTinyInteger('day_of_week')->default(5); // 0=شنبه .. 6=جمعه (پیش‌فرض: جمعه)
            $table->unsignedInteger('max_post_id');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_invited_at')->nullable();
            $table->timestamps();

            $table->index('destination_id');
            $table->index(['enabled', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_read_invitations');
    }
};
