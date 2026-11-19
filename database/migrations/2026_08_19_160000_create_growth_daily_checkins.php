<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('growth_daily_checkins')) {
            return;
        }

        Schema::create('growth_daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_profile_id');
            $table->string('day_key', 16);
            $table->unsignedTinyInteger('energy')->nullable();
            $table->string('mood', 16)->nullable();
            $table->unsignedTinyInteger('sleep_hours')->nullable();
            $table->boolean('moved')->nullable();
            $table->json('focus_slugs')->nullable();
            $table->text('evening_note')->nullable();
            $table->timestamps();

            $table->index('growth_profile_id');
            $table->unique(['growth_profile_id', 'day_key'], 'gdc_profile_day_unique');
            $table->foreign('growth_profile_id')
                ->references('id')
                ->on('growth_profiles')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_daily_checkins');
    }
};
