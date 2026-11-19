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
        Schema::create('weather_history', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->date('date');
            $table->json('weather_data'); // ذخیره داده‌های کامل آب و هوا
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('precipitation', 5, 2)->nullable();
            $table->decimal('wind_speed', 5, 2)->nullable();
            $table->decimal('wind_gust', 5, 2)->nullable();
            $table->timestamps();
            
            $table->index(['latitude', 'longitude', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_history');
    }
};
