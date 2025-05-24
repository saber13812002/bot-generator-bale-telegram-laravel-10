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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('need_registration')->default(0);
            $table->string('default_bots_text')->nullable();
            $table->date('start_date')->default(now());
            $table->string('description')->nullable();
            $table->string('brief')->nullable();
            // $table->string('image_caption')->nullable();
            // $table->string('image_title')->nullable();
            // $table->string('image_alt')->nullable();
            // $table->string('image_description')->nullable();
            // $table->string('image_url')->nullable();
            // $table->string('image_url_caption')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('class', ['A', 'B', 'C'])->nullable();
            
            $table->integer('points_per_month')->default(0);
            $table->integer('points_per_year')->default(0);
            $table->integer('points_per_lifetime')->default(0);

            $table->integer('points_per_month_for_referral')->default(0);
            $table->integer('points_per_year_for_referral')->default(0);
            $table->integer('points_per_lifetime_for_referral')->default(0);
            

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
